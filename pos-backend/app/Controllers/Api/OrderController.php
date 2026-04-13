<?php
namespace App\Controllers\Api;

use App\Models\OrderModel;
use App\Models\OrderItemModel;
use App\Models\OrderDiscountLogModel;
use App\Models\PaymentModel;
use App\Models\ProductModel;
use App\Models\BomRecipeModel;
use App\Models\BomRecipeLineModel;
use App\Models\RawMaterialModel;
use App\Models\StockMovementModel;
use App\Models\JournalEntryModel;
use App\Models\JournalLineModel;
use App\Models\AccountModel;
use App\Models\KasTransactionModel;
use App\Libraries\DiscountEngine;

class OrderController extends BaseApiController
{
    protected OrderModel $orderModel;

    public function __construct()
    {
        helper(['uuid','code']);
        $this->orderModel = new OrderModel();
    }

    public function index()
    {
        $outletId = $this->currentOutletId();
        $page     = (int)($this->request->getGet('page') ?? 1);
        $perPage  = (int)($this->request->getGet('per_page') ?? 20);
        $status   = $this->request->getGet('status');
        $date     = $this->request->getGet('date') ?? date('Y-m-d');

        $builder = $this->orderModel->withDetails()->where('orders.outlet_id', $outletId)
                                    ->where('DATE(orders.created_at)', $date);
        if ($status) $builder->where('orders.status', $status);

        $total = $builder->countAllResults(false);
        $data  = $builder->orderBy('orders.created_at','DESC')->paginate($perPage,'default',$page);
        return $this->success($this->paginate($data, $total, $page, $perPage));
    }

    public function show($id)
    {
        $order = $this->orderModel->withDetails()->find($id);
        if (!$order) return $this->notFound('Order tidak ditemukan');

        $itemModel    = new OrderItemModel();
        $discountLog  = new OrderDiscountLogModel();
        $paymentModel = new PaymentModel();

        $order['items']    = $itemModel->getByOrder($id);
        $order['discounts'] = $discountLog->where('order_id',$id)->findAll();
        $order['payments']  = $paymentModel->where('order_id',$id)->findAll();

        return $this->success($order);
    }

    // ================================================================
    // POST /api/v1/orders — buat order baru
    // ================================================================
    public function create()
    {
        $json  = $this->request->getJSON(true) ?? [];
        $rules = ['items' => 'required'];
        if (!$this->validate($rules)) return $this->error('Validasi gagal', 422, $this->validator->getErrors());

        $outletId  = $this->currentOutletId();
        $cashierId = $this->currentUserId();
        $items     = $json['items'] ?? [];

        if (empty($items)) return $this->error('Order harus memiliki minimal 1 item');

        // ---- Hitung subtotal dari items ----
        $productModel = new ProductModel();
        $orderItems   = [];
        $subtotal     = 0;
        $hppTotal     = 0;

        foreach ($items as $item) {
            $product = $productModel->find($item['product_id']);
            if (!$product || !$product['active']) {
                return $this->error("Produk {$item['product_id']} tidak ditemukan atau tidak aktif");
            }

            $qty        = (int)($item['qty'] ?? 1);
            $unitPrice  = (float)($item['unit_price'] ?? $product['base_price']);
            $modDelta   = 0;

            // Kalkulasi modifier price delta
            $modifiers  = $item['modifiers'] ?? [];
            foreach ($modifiers as $mod) {
                $modDelta += (float)($mod['price_delta'] ?? 0);
            }
            $unitPrice += $modDelta;

            // Hitung HPP dari BOM
            $unitHpp = $this->calculateHpp($product['id'], $outletId);

            $itemSubtotal  = $unitPrice * $qty;
            $subtotal     += $itemSubtotal;
            $hppTotal     += $unitHpp * $qty;

            $orderItems[] = [
                'id'            => generate_uuid(),
                'product_id'    => $product['id'],
                'bundle_id'     => $item['bundle_id'] ?? null,
                'parent_item_id'=> $item['parent_item_id'] ?? null,
                'name_snapshot' => $product['name'],
                'qty'           => $qty,
                'unit_price'    => $unitPrice,
                'unit_hpp'      => $unitHpp,
                'discount_amount'=> 0,
                'subtotal'      => $itemSubtotal,
                'modifiers'     => !empty($modifiers) ? json_encode($modifiers) : null,
                'notes'         => $item['notes'] ?? null,
            ];
        }

        // ---- Jalankan discount engine ----
        $discountTotal   = 0;
        $discountLogs    = [];
        $voucherCode     = $json['voucher_code'] ?? null;
        $customerId      = $json['customer_id'] ?? null;

        $engine = new DiscountEngine($outletId, $customerId);
        $result = $engine->calculate($orderItems, $subtotal, $voucherCode, $json['payment_method'] ?? 'cash');

        $discountTotal = $result['discount_total'];
        $discountLogs  = $result['logs'];

        // Update discount_amount di tiap item
        foreach ($orderItems as &$oi) {
            $oi['discount_amount'] = $result['item_discounts'][$oi['product_id']] ?? 0;
            $oi['subtotal']       -= $oi['discount_amount'];
        }

        $grandTotal = max(0, $subtotal - $discountTotal);

        // ---- Cek stok semua item ----
        $stockCheck = $this->checkAndDeductStock($orderItems, $outletId, $cashierId, dry: true);
        if ($stockCheck !== true) {
            return $this->error("Stok tidak mencukupi: {$stockCheck}");
        }

        // ---- Mulai DB Transaction ----
        $db = \Config\Database::connect();
        $db->transStart();

        $orderId     = generate_uuid();
        $counter     = $this->orderModel->getTodayCounter($outletId) + 1;
        $orderNumber = generate_code('orders', $outletId);

        // Insert order
        $this->orderModel->insert([
            'id'             => $orderId,
            'outlet_id'      => $outletId,
            'order_number'   => $orderNumber,
            'cashier_id'     => $cashierId,
            'customer_id'    => $customerId,
            'order_type'     => $json['order_type'] ?? 'dine_in',
            'table_number'   => $json['table_number'] ?? null,
            'status'         => 'confirmed',
            'subtotal'       => $subtotal,
            'discount_total' => $discountTotal,
            'grand_total'    => $grandTotal,
            'hpp_total'      => $hppTotal,
            'payment_status' => 'unpaid',
            'notes'          => $json['notes'] ?? null,
        ]);

        // Insert order items
        $itemModel = new OrderItemModel();
        foreach ($orderItems as $oi) {
            $oi['order_id'] = $orderId;
            $itemModel->insert($oi);
        }

        // Insert discount logs
        if (!empty($discountLogs)) {
            $logModel = new OrderDiscountLogModel();
            foreach ($discountLogs as $log) {
                $log['id']       = generate_uuid();
                $log['order_id'] = $orderId;
                $logModel->insert($log);
            }
            // Increment usage counter tiap diskon
            $engine->commitUsage();
        }

        // Deduct stok (actual)
        $this->checkAndDeductStock($orderItems, $outletId, $cashierId, $orderId, dry: false);

        // Auto post jurnal: Piutang/Kas D | Pendapatan K + HPP D | Persediaan K
        $this->postJurnal($orderId, $outletId, $cashierId, $grandTotal, $hppTotal, $discountTotal, $orderNumber);

        $db->transComplete();

        if (!$db->transStatus()) {
            return $this->serverError('Gagal membuat order, silakan coba lagi');
        }

        return $this->created([
            'order_id'     => $orderId,
            'order_number' => $orderNumber,
            'subtotal'     => $subtotal,
            'discount_total'=> $discountTotal,
            'grand_total'  => $grandTotal,
        ], 'Order berhasil dibuat');
    }

    // ================================================================
    // POST /api/v1/orders/{id}/payment — proses pembayaran
    // ================================================================
    public function pay($orderId)
    {
        $order = $this->orderModel->find($orderId);
        if (!$order) return $this->notFound('Order tidak ditemukan');
        if ($order['payment_status'] === 'paid') return $this->error('Order sudah dibayar');

        $json   = $this->request->getJSON(true) ?? [];
        $method = $json['method'] ?? 'cash';
        $amount = (float)($json['amount'] ?? 0);

        if ($amount < $order['grand_total']) {
            return $this->error('Jumlah bayar kurang dari total order');
        }

        $db = \Config\Database::connect();
        $db->transStart();

        $paymentId = generate_uuid();
        (new PaymentModel())->insert([
            'id'           => $paymentId,
            'order_id'     => $orderId,
            'method'       => $method,
            'provider'     => $json['provider'] ?? null,
            'reference_no' => $json['reference_no'] ?? null,
            'amount'       => $amount,
            'fee'          => $json['fee'] ?? 0,
            'status'       => 'success',
            'paid_at'      => date('Y-m-d H:i:s'),
            'metadata'     => isset($json['metadata']) ? json_encode($json['metadata']) : null,
        ]);

        $this->orderModel->update($orderId, [
            'payment_status' => 'paid',
            'status'         => 'completed',
        ]);

        // Update kas besar
        $kasModel   = new KasTransactionModel();
        $outletId   = $this->currentOutletId();
        $kasBalance = $kasModel->getBalance($outletId, 'besar');
        $kasModel->insert([
            'id'             => generate_uuid(),
            'outlet_id'      => $outletId,
            'kas_type'       => 'besar',
            'type'           => 'in',
            'amount'         => $order['grand_total'],
            'reference_type' => 'payment',
            'reference_id'   => $paymentId,
            'description'    => "Pembayaran order {$order['order_number']}",
            'balance_after'  => $kasBalance + $order['grand_total'],
            'created_by'     => $this->currentUserId(),
        ]);

        $db->transComplete();

        $change = $amount - $order['grand_total'];
        return $this->success([
            'payment_id'  => $paymentId,
            'amount_paid' => $amount,
            'grand_total' => $order['grand_total'],
            'change'      => $change,
        ], 'Pembayaran berhasil');
    }

    public function updateStatus($id)
    {
        $order  = $this->orderModel->find($id);
        if (!$order) return $this->notFound('Order tidak ditemukan');
        $json   = $this->request->getJSON(true) ?? [];
        $status = $json['status'] ?? '';
        $valid  = ['pending','confirmed','preparing','ready','completed'];
        if (!in_array($status, $valid)) return $this->error('Status tidak valid');
        $this->orderModel->update($id, ['status' => $status]);
        return $this->success(null, 'Status order diupdate');
    }

    public function cancel($id)
    {
        $order = $this->orderModel->find($id);
        if (!$order) return $this->notFound('Order tidak ditemukan');
        if ($order['payment_status'] === 'paid') return $this->error('Order yang sudah dibayar tidak bisa dibatalkan langsung');

        $json = $this->request->getJSON(true) ?? [];
        $this->orderModel->update($id, [
            'status'        => 'cancelled',
            'cancelled_at'  => date('Y-m-d H:i:s'),
            'cancel_reason' => $json['reason'] ?? null,
            'cancelled_by'  => $this->currentUserId(),
        ]);
        return $this->success(null, 'Order berhasil dibatalkan');
    }

    public function receipt($id)
    {
        $order = $this->orderModel->withDetails()->find($id);
        if (!$order) return $this->notFound('Order tidak ditemukan');

        $order['items']    = (new OrderItemModel())->getByOrder($id);
        $order['payments'] = (new PaymentModel())->where('order_id',$id)->findAll();

        return $this->success($order);
    }

    // ================================================================
    // PRIVATE HELPERS
    // ================================================================

    private function calculateHpp(string $productId, string $outletId): float
    {
        $recipe = (new BomRecipeModel())->getActiveRecipe($productId);
        if (!$recipe) return 0;

        $lines = (new BomRecipeLineModel())->getByRecipe($recipe['id']);
        $hpp   = 0;
        foreach ($lines as $line) {
            $hpp += (float)$line['qty_required'] * (float)$line['cost_per_unit'];
        }
        return $hpp;
    }

    private function checkAndDeductStock(array $items, string $outletId, string $userId, string $orderId = '', bool $dry = true): bool|string
    {
        $recipeModel = new BomRecipeModel();
        $lineModel   = new BomRecipeLineModel();
        $matModel    = new RawMaterialModel();
        $movModel    = new StockMovementModel();

        foreach ($items as $item) {
            $recipe = $recipeModel->getActiveRecipe($item['product_id']);
            if (!$recipe) continue;

            $lines = $lineModel->getByRecipe($recipe['id']);
            foreach ($lines as $line) {
                $needed = $line['qty_required'] * $item['qty'];
                $mat    = $matModel->find($line['material_id']);

                if ((float)$mat['stock_qty'] < $needed) {
                    return "{$mat['name']} (tersedia: {$mat['stock_qty']} {$mat['unit']}, dibutuhkan: {$needed} {$mat['unit']})";
                }

                if (!$dry) {
                    $before = (float)$mat['stock_qty'];
                    $after  = $before - $needed;
                    \Config\Database::connect()->query(
                        'UPDATE raw_materials SET stock_qty=?, updated_at=NOW() WHERE id=?',
                        [$after, $line['material_id']]
                    );
                    $movModel->insert([
                        'id'             => generate_uuid(),
                        'outlet_id'      => $outletId,
                        'material_id'    => $line['material_id'],
                        'reference_type' => 'order',
                        'reference_id'   => $orderId,
                        'movement_type'  => 'out',
                        'qty'            => $needed,
                        'qty_before'     => $before,
                        'qty_after'      => $after,
                        'cost_per_unit'  => $mat['cost_per_unit'],
                        'notes'          => "Auto deduct order",
                        'created_by'     => $userId,
                    ]);
                }
            }
        }
        return true;
    }

    private function postJurnal(string $orderId, string $outletId, string $userId, float $grandTotal, float $hpp, float $discount, string $orderNumber): void
    {
        $accountModel = new AccountModel();
        $entryModel   = new JournalEntryModel();
        $lineModel    = new JournalLineModel();

        $kasAcc     = $accountModel->findByCode($outletId, '1-1000');
        $revAcc     = $accountModel->findByCode($outletId, '4-1000');
        $discAcc    = $accountModel->findByCode($outletId, '4-1100');
        $hppAcc     = $accountModel->findByCode($outletId, '5-1000');
        $persAcc    = $accountModel->findByCode($outletId, '1-2000');

        if (!$kasAcc || !$revAcc) return; // CoA belum diseed, skip

        $entryId     = generate_uuid();
        $entryNumber = generate_code('journal_entries', $outletId);
        $totalDebit  = $grandTotal + $hpp;
        $totalCredit = $grandTotal + $hpp;

        $entryModel->insert([
            'id'             => $entryId,
            'outlet_id'      => $outletId,
            'entry_number'   => $entryNumber,
            'reference_type' => 'order',
            'reference_id'   => $orderId,
            'description'    => "Penjualan order {$orderNumber}",
            'total_debit'    => $totalDebit,
            'total_credit'   => $totalCredit,
            'created_by'     => $userId,
        ]);

        $lines = [];
        // Kas D
        $lines[] = ['id'=>generate_uuid(),'entry_id'=>$entryId,'account_id'=>$kasAcc['id'],'debit'=>$grandTotal,'credit'=>0,'description'=>'Kas masuk penjualan'];
        // Diskon D (jika ada)
        if ($discount > 0 && $discAcc) {
            $lines[] = ['id'=>generate_uuid(),'entry_id'=>$entryId,'account_id'=>$discAcc['id'],'debit'=>$discount,'credit'=>0,'description'=>'Diskon penjualan'];
        }
        // Pendapatan K
        $revenue = $grandTotal + $discount;
        $lines[] = ['id'=>generate_uuid(),'entry_id'=>$entryId,'account_id'=>$revAcc['id'],'debit'=>0,'credit'=>$revenue,'description'=>'Pendapatan penjualan'];
        // HPP D
        if ($hpp > 0 && $hppAcc && $persAcc) {
            $lines[] = ['id'=>generate_uuid(),'entry_id'=>$entryId,'account_id'=>$hppAcc['id'],'debit'=>$hpp,'credit'=>0,'description'=>'HPP penjualan'];
            $lines[] = ['id'=>generate_uuid(),'entry_id'=>$entryId,'account_id'=>$persAcc['id'],'debit'=>0,'credit'=>$hpp,'description'=>'Keluar persediaan'];
        }

        foreach ($lines as $line) $lineModel->insert($line);
    }
}
