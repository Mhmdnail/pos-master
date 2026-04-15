<?php
namespace App\Libraries;

use App\Models\DiscountModel;
use App\Models\DiscountRuleModel;
use App\Models\DiscountTargetModel;
use App\Models\CustomerModel;
use App\Models\OrderDiscountLogModel;

class DiscountEngine
{
    private string  $outletId;
    private ?string $customerId;
    private array   $appliedDiscountIds = [];
    private DiscountModel $discountModel;

    public function __construct(string $outletId, ?string $customerId = null)
    {
        $this->outletId      = $outletId;
        $this->customerId    = $customerId;
        $this->discountModel = new DiscountModel();
    }

    // ============================================================
    // MAIN — hitung semua diskon untuk order ini
    // ============================================================
    public function calculate(array $items, float $subtotal, ?string $voucherCode = null, string $paymentMethod = 'cash'): array
    {
        $ruleModel   = new DiscountRuleModel();
        $customer    = $this->customerId
            ? (new CustomerModel())->withTier()->find($this->customerId)
            : null;

        // 1. Kumpulkan kandidat diskon aktif
        $candidates = $this->discountModel->getActive($this->outletId);

        // 2. Jika ada voucher code, cari dan tambahkan ke kandidat
        if ($voucherCode) {
            $voucher = $this->discountModel->findByCode($voucherCode, $this->outletId);
            // Hanya tambahkan kalau belum ada di kandidat
            if ($voucher && ! in_array($voucher['id'], array_column($candidates, 'id'))) {
                $candidates[] = $voucher;
            }
        }

        // 3. Filter eligible — semua syarat harus terpenuhi
        $eligible = [];
        foreach ($candidates as $disc) {
            $rules = $ruleModel->getByDiscount($disc['id']);
            if ($this->passesAllRules($disc, $rules, $subtotal, $customer, $paymentMethod, $items, $voucherCode)) {
                $eligible[] = $disc;
            }
        }

        // 4. Sort by priority DESC — prioritas tertinggi diproses duluan
        usort($eligible, fn($a, $b) => (int)$b['priority'] <=> (int)$a['priority']);

        // 5. Resolve stackable vs non-stackable
        $toApply = $this->resolveConflicts($eligible);

        // 6. Hitung nilai diskon
        $discountTotal = 0;
        $logs          = [];
        $itemDiscounts = [];

        foreach ($toApply as $disc) {
            $targets = (new DiscountTargetModel())->where('discount_id', $disc['id'])->findAll();
            $amount  = $this->computeAmount($disc, $items, $subtotal, $targets);

            // Terapkan hard cap jika ada
            if ($disc['max_cap'] && $amount > (float)$disc['max_cap']) {
                $amount = (float)$disc['max_cap'];
            }

            // Jangan tambahkan diskon yang nilainya 0
            if ($amount <= 0) continue;

            $discountTotal += $amount;
            $this->appliedDiscountIds[] = $disc['id'];

            $logs[] = [
                'discount_id'   => $disc['id'],
                'discount_name' => $disc['name'],
                'discount_code' => $disc['code'] ?? null,
                'applied_to'    => empty($targets) || $targets[0]['target_type'] === 'order' ? 'order' : 'item',
                'amount'        => round($amount, 2),
            ];

            // Distribusi ke item untuk tracking per-item
            if (!empty($items)) {
                $perItem = $amount / count($items);
                foreach ($items as $item) {
                    $itemDiscounts[$item['product_id']] = ($itemDiscounts[$item['product_id']] ?? 0) + $perItem;
                }
            }
        }

        return [
            'discount_total' => round($discountTotal, 2),
            'item_discounts' => $itemDiscounts,
            'logs'           => $logs,
        ];
    }

    // Increment usage counter setelah order berhasil di-commit
    public function commitUsage(): void
    {
        foreach ($this->appliedDiscountIds as $id) {
            $this->discountModel->incrementUsage($id);
        }
    }

    // ============================================================
    // PRIVATE HELPERS
    // ============================================================

    private function passesAllRules(
        array   $disc,
        array   $rules,
        float   $subtotal,
        ?array  $customer,
        string  $paymentMethod,
        array   $items,
        ?string $requestedVoucherCode
    ): bool {
        // ── Validasi usage limit global ──────────────────────────
        if ($disc['usage_limit'] !== null && (int)$disc['usage_count'] >= (int)$disc['usage_limit']) {
            return false;
        }

        // ── Validasi per-customer limit ──────────────────────────
        // Untuk diskon VOUCHER (punya code): cek berapa kali customer ini
        // sudah pakai voucher yang sama. Default limit = 1x per customer.
        if ($disc['code']) {
            $usedByCustomer = $this->countUsageByCustomer($disc['id'], $this->customerId);
            $limit = $disc['per_customer_limit'] ?? 1;  // default 1x per customer
            if ($usedByCustomer >= $limit) {
                return false;
            }

            // Kalau diskon ini punya code tapi code yang diminta berbeda, skip
            if ($requestedVoucherCode && strtoupper($disc['code']) !== strtoupper($requestedVoucherCode)) {
                // Diskon ini voucher tapi bukan yang diminta — skip untuk daftar auto
                // Hanya allow kalau memang tidak ada voucher yang diminta (auto discount)
                return false;
            }
        }

        // ── Validasi member requirement ───────────────────────────
        if ((int)$disc['require_member'] === 1 && !$customer) {
            return false;
        }

        // ── Validasi min member tier ─────────────────────────────
        if ($disc['min_member_tier'] && (!$customer || (int)($customer['tier_id'] ?? 0) < (int)$disc['min_member_tier'])) {
            return false;
        }

        // ── Validasi rules (AND logic) ───────────────────────────
        foreach ($rules as $rule) {
            $val = json_decode($rule['rule_value'], true);
            if (!$this->evaluateRule($rule['rule_type'], $val, $subtotal, $customer, $paymentMethod, $items)) {
                return false;
            }
        }

        return true;
    }

    private function evaluateRule(string $type, array $val, float $subtotal, ?array $customer, string $paymentMethod, array $items): bool
    {
        switch ($type) {
            case 'min_amount':
                return $subtotal >= (float)($val['amount'] ?? 0);

            case 'min_qty':
                $totalQty = array_sum(array_column($items, 'qty'));
                return $totalQty >= (int)($val['qty'] ?? 0);

            case 'time_range':
                $now   = date('H:i');
                $start = $val['start'] ?? '00:00';
                $end   = $val['end']   ?? '23:59';
                return $now >= $start && $now <= $end;

            case 'day_of_week':
                $today = (int)date('w'); // 0=Sun, 6=Sat
                return in_array($today, $val['days'] ?? [], true);

            case 'date_range':
                $today = date('Y-m-d');
                $from  = $val['from'] ?? '1970-01-01';
                $until = $val['until'] ?? '2099-12-31';
                return $today >= $from && $today <= $until;

            case 'payment_method':
                return in_array($paymentMethod, $val['methods'] ?? [], true);

            case 'customer_type':
                $required = $val['type'] ?? 'member';
                return $required === 'member' ? (bool)$customer : !(bool)$customer;

            default:
                return true;
        }
    }

    private function resolveConflicts(array $eligible): array
    {
        $stackable    = array_filter($eligible, fn($d) => (int)$d['is_stackable'] === 1);
        $nonStackable = array_filter($eligible, fn($d) => (int)$d['is_stackable'] === 0);

        // Dari yang non-stackable, pilih yang nilainya terbesar
        $bestNonStack = null;
        $bestVal      = -1;
        foreach ($nonStackable as $d) {
            $val = (float)$d['value'];
            if ($val > $bestVal) {
                $bestVal      = $val;
                $bestNonStack = $d;
            }
        }

        $result = array_values($stackable);
        if ($bestNonStack) $result[] = $bestNonStack;

        return $result;
    }

    private function computeAmount(array $disc, array $items, float $subtotal, array $targets): float
    {
        // Kalau ada target spesifik (produk/kategori tertentu), hitung dari item yang relevan
        $base = $subtotal;
        if (!empty($targets) && $targets[0]['target_type'] !== 'order') {
            $targetIds = array_column($targets, 'target_id');
            $base = 0;
            foreach ($items as $item) {
                if (in_array($item['product_id'], $targetIds, true)) {
                    $base += (float)$item['unit_price'] * (int)$item['qty'];
                }
            }
        }

        return match($disc['type']) {
            'percentage'  => round($base * ((float)$disc['value'] / 100), 2),
            'nominal'     => min((float)$disc['value'], $base),
            'buy_x_get_y' => $this->computeBuyXGetY($disc, $items),
            'free_item'   => 0, // implementasi nanti
            default       => 0,
        };
    }

    private function computeBuyXGetY(array $disc, array $items): float
    {
        $totalQty = array_sum(array_column($items, 'qty'));
        if ($totalQty < (int)$disc['value']) return 0;
        // Gratis 1 item dengan harga termurah
        $prices = array_map(fn($i) => (float)$i['unit_price'], $items);
        return $prices ? min($prices) : 0;
    }

    /**
     * Hitung berapa kali customer tertentu sudah pakai diskon ini
     * di transaksi-transaksi sebelumnya yang sudah paid.
     * Jika customerId null (walk-in), tidak ada pembatasan per-customer.
     */
    private function countUsageByCustomer(string $discountId, ?string $customerId): int
    {
        if (!$customerId) return 0;

        $db = \Config\Database::connect();
        $result = $db->query(
            'SELECT COUNT(*) as cnt
             FROM order_discount_log odl
             JOIN orders o ON o.id = odl.order_id
             WHERE odl.discount_id = ?
               AND o.customer_id   = ?
               AND o.payment_status = "paid"',
            [$discountId, $customerId]
        )->getRowArray();

        return (int)($result['cnt'] ?? 0);
    }
}
