<?php
namespace App\Libraries;

use App\Models\DiscountModel;
use App\Models\DiscountRuleModel;
use App\Models\DiscountTargetModel;
use App\Models\CustomerModel;

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
        $customer    = $this->customerId ? (new CustomerModel())->withTier()->find($this->customerId) : null;

        // 1. Kumpulkan kandidat diskon
        $candidates = $this->discountModel->getActive($this->outletId);

        // Tambahkan voucher jika ada
        if ($voucherCode) {
            $voucher = $this->discountModel->findByCode($voucherCode, $this->outletId);
            if ($voucher && !in_array($voucher['id'], array_column($candidates,'id'))) {
                $candidates[] = $voucher;
            }
        }

        // 2. Filter eligible
        $eligible = [];
        foreach ($candidates as $disc) {
            $rules = $ruleModel->getByDiscount($disc['id']);
            if ($this->passesAllRules($disc, $rules, $subtotal, $customer, $paymentMethod, $items)) {
                $eligible[] = $disc;
            }
        }

        // 3. Sort by priority DESC
        usort($eligible, fn($a,$b) => $b['priority'] <=> $a['priority']);

        // 4. Resolve stackable vs non-stackable
        $toApply = $this->resolveConflicts($eligible);

        // 5. Hitung nilai diskon
        $discountTotal  = 0;
        $logs           = [];
        $itemDiscounts  = [];

        foreach ($toApply as $disc) {
            $targets = (new DiscountTargetModel())->where('discount_id',$disc['id'])->findAll();
            $amount  = $this->computeAmount($disc, $items, $subtotal, $targets);

            // Hard cap
            if ($disc['max_cap'] && $amount > (float)$disc['max_cap']) {
                $amount = (float)$disc['max_cap'];
            }

            $discountTotal += $amount;
            $this->appliedDiscountIds[] = $disc['id'];

            $logs[] = [
                'discount_id'   => $disc['id'],
                'discount_name' => $disc['name'],
                'discount_code' => $disc['code'] ?? null,
                'applied_to'    => empty($targets) || $targets[0]['target_type'] === 'order' ? 'order' : 'item',
                'amount'        => $amount,
            ];

            // Distribusi ke item untuk tracking
            foreach ($items as $item) {
                $itemDiscounts[$item['product_id']] = ($itemDiscounts[$item['product_id']] ?? 0) + ($amount / count($items));
            }
        }

        return [
            'discount_total' => round($discountTotal, 2),
            'item_discounts' => $itemDiscounts,
            'logs'           => $logs,
        ];
    }

    public function commitUsage(): void
    {
        foreach ($this->appliedDiscountIds as $id) {
            $this->discountModel->incrementUsage($id);
        }
    }

    // ============================================================
    // PRIVATE HELPERS
    // ============================================================

    private function passesAllRules(array $disc, array $rules, float $subtotal, ?array $customer, string $paymentMethod, array $items): bool
    {
        // Cek usage limit
        if ($disc['usage_limit'] && $disc['usage_count'] >= $disc['usage_limit']) return false;
        // Cek member required
        if ($disc['require_member'] && !$customer) return false;
        // Cek min tier
        if ($disc['min_member_tier'] && (!$customer || ($customer['tier_id'] ?? 0) < $disc['min_member_tier'])) return false;

        foreach ($rules as $rule) {
            $val = json_decode($rule['rule_value'], true);
            switch ($rule['rule_type']) {
                case 'min_amount':
                    if ($subtotal < (float)($val['amount'] ?? 0)) return false;
                    break;
                case 'min_qty':
                    $totalQty = array_sum(array_column($items,'qty'));
                    if ($totalQty < (int)($val['qty'] ?? 0)) return false;
                    break;
                case 'time_range':
                    $now   = date('H:i');
                    $start = $val['start'] ?? '00:00';
                    $end   = $val['end']   ?? '23:59';
                    if ($now < $start || $now > $end) return false;
                    break;
                case 'day_of_week':
                    $today = (int)date('w'); // 0=Sun
                    if (!in_array($today, $val['days'] ?? [])) return false;
                    break;
                case 'payment_method':
                    if (!in_array($paymentMethod, $val['methods'] ?? [])) return false;
                    break;
            }
        }
        return true;
    }

    private function resolveConflicts(array $eligible): array
    {
        $stackable    = array_filter($eligible, fn($d) => $d['is_stackable']);
        $nonStackable = array_filter($eligible, fn($d) => !$d['is_stackable']);

        // Dari non-stackable, pilih yang nilainya terbesar
        $bestNonStack = null;
        $bestVal      = 0;
        foreach ($nonStackable as $d) {
            $val = (float)$d['value'];
            if ($val > $bestVal) { $bestVal = $val; $bestNonStack = $d; }
        }

        $result = array_values($stackable);
        if ($bestNonStack) $result[] = $bestNonStack;

        return $result;
    }

    private function computeAmount(array $disc, array $items, float $subtotal, array $targets): float
    {
        $base = $subtotal;

        // Jika target spesifik, hitung dari item yang relevan saja
        if (!empty($targets) && $targets[0]['target_type'] !== 'order') {
            $targetIds = array_column($targets, 'target_id');
            $base = 0;
            foreach ($items as $item) {
                if (in_array($item['product_id'], $targetIds)) {
                    $base += $item['unit_price'] * $item['qty'];
                }
            }
        }

        return match($disc['type']) {
            'percentage'  => round($base * ((float)$disc['value'] / 100), 2),
            'nominal'     => min((float)$disc['value'], $base),
            'buy_x_get_y' => $this->computeBuyXGetY($disc, $items),
            default       => 0,
        };
    }

    private function computeBuyXGetY(array $disc, array $items): float
    {
        // Nilai diskon = harga item termurah jika qty memenuhi syarat
        $totalQty = array_sum(array_column($items,'qty'));
        if ($totalQty < (int)$disc['value']) return 0;

        $prices = array_map(fn($i) => $i['unit_price'], $items);
        return $prices ? min($prices) : 0;
    }
}
