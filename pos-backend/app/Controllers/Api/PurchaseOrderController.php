<?php
namespace App\Controllers\Api;

class PurchaseOrderController extends BaseApiController
{
    private function db() { return \Config\Database::connect(); }

    // GET /api/v1/purchase-orders
    public function index()
    {
        $outletId = $this->currentOutletId();
        $page     = (int)($this->request->getGet('page')     ?? 1);
        $perPage  = (int)($this->request->getGet('per_page') ?? 20);
        $status   = $this->request->getGet('status') ?? '';
        $from     = $this->request->getGet('from')   ?? date('Y-m-d', strtotime('-30 days'));
        $to       = $this->request->getGet('to')     ?? date('Y-m-d');

        $where  = "po.outlet_id = ? AND po.order_date BETWEEN ? AND ?";
        $params = [$outletId, $from, $to];
        if ($status) { $where .= " AND po.status = ?"; $params[] = $status; }

        $total = $this->db()->query(
            "SELECT COUNT(*) as cnt FROM purchase_orders po WHERE {$where}",
            $params
        )->getRowArray()['cnt'] ?? 0;

        $data = $this->db()->query("
            SELECT po.*, s.name as supplier_name, u.name as created_by_name
            FROM purchase_orders po
            LEFT JOIN suppliers s ON s.id = po.supplier_id
            LEFT JOIN users u     ON u.id = po.created_by
            WHERE {$where}
            ORDER BY po.order_date DESC, po.created_at DESC
            LIMIT ? OFFSET ?
        ", array_merge($params, [$perPage, ($page - 1) * $perPage]))->getResultArray();

        return $this->success($this->paginate($data, $total, $page, $perPage));
    }

    // GET /api/v1/purchase-orders/{id}
    public function show($id)
    {
        $po = $this->db()->query("
            SELECT po.*, s.name as supplier_name, s.phone as supplier_phone,
                   u.name as created_by_name
            FROM purchase_orders po
            LEFT JOIN suppliers s ON s.id = po.supplier_id
            LEFT JOIN users u     ON u.id = po.created_by
            WHERE po.id = ? AND po.outlet_id = ?
        ", [$id, $this->currentOutletId()])->getRowArray();

        if (!$po) return $this->notFound('Purchase order tidak ditemukan');

        $po['lines'] = $this->db()->query("
            SELECT pol.*, m.name as material_name, m.unit, m.code as material_code
            FROM purchase_order_lines pol
            LEFT JOIN raw_materials m ON m.id = pol.material_id
            WHERE pol.po_id = ?
            ORDER BY m.name ASC
        ", [$id])->getResultArray();

        return $this->success($po);
    }

    // POST /api/v1/purchase-orders — buat PO baru
    public function create()
    {
        helper(['uuid', 'code']);
        $json  = $this->request->getJSON(true) ?? [];
        $rules = [
            'supplier_id' => 'required',
            'order_date'  => 'required',
            'lines'       => 'required',
        ];
        if (!$this->validate($rules)) {
            return $this->error('Validasi gagal', 422, $this->validator->getErrors());
        }

        $lines = $json['lines'] ?? [];
        if (empty($lines)) return $this->error('PO harus memiliki minimal 1 item');

        $outletId = $this->currentOutletId();
        $userId   = $this->currentUserId();

        // Hitung total
        $subtotal = 0;
        foreach ($lines as $line) {
            $subtotal += (float)($line['qty_ordered'] ?? 0) * (float)($line['unit_price'] ?? 0);
        }
        $taxAmount   = (float)($json['tax_amount'] ?? 0);
        $totalAmount = $subtotal + $taxAmount;

        $id       = generate_uuid();
        $poNumber = generate_code('purchase_orders', $outletId);

        $this->db()->query("
            INSERT INTO purchase_orders
                (id, outlet_id, supplier_id, po_number, status,
                 order_date, expected_date, subtotal, tax_amount, total_amount,
                 notes, created_by)
            VALUES (?, ?, ?, ?, 'draft', ?, ?, ?, ?, ?, ?, ?)
        ", [
            $id, $outletId,
            $json['supplier_id'],
            $poNumber,
            $json['order_date'],
            $json['expected_date'] ?? null,
            $subtotal, $taxAmount, $totalAmount,
            $json['notes'] ?? null,
            $userId,
        ]);

        foreach ($lines as $line) {
            $qty      = (float)($line['qty_ordered'] ?? 0);
            $price    = (float)($line['unit_price']  ?? 0);
            $this->db()->query("
                INSERT INTO purchase_order_lines
                    (id, po_id, material_id, qty_ordered, qty_received, unit_price, subtotal, notes)
                VALUES (?, ?, ?, ?, 0, ?, ?, ?)
            ", [
                generate_uuid(), $id,
                $line['material_id'],
                $qty, $price,
                $qty * $price,
                $line['notes'] ?? null,
            ]);
        }

        return $this->created([
            'id'        => $id,
            'po_number' => $poNumber,
        ], "PO {$poNumber} berhasil dibuat");
    }

    // PUT /api/v1/purchase-orders/{id} — update PO (hanya status draft)
    public function update($id)
    {
        $po = $this->db()->query(
            "SELECT * FROM purchase_orders WHERE id = ? AND outlet_id = ?",
            [$id, $this->currentOutletId()]
        )->getRowArray();
        if (!$po) return $this->notFound('PO tidak ditemukan');
        if (!in_array($po['status'], ['draft'])) {
            return $this->error('Hanya PO berstatus draft yang bisa diubah');
        }

        $json = $this->request->getJSON(true) ?? [];

        // Update status ke ordered
        if (isset($json['status']) && $json['status'] === 'ordered') {
            $this->db()->query(
                "UPDATE purchase_orders SET status = 'ordered' WHERE id = ?", [$id]
            );
            return $this->success(null, 'PO berhasil dikonfirmasi ke supplier');
        }

        // Update field lain
        $allowed = ['expected_date', 'notes', 'tax_amount'];
        $sets = []; $params = [];
        foreach ($allowed as $f) {
            if (array_key_exists($f, $json)) { $sets[] = "`{$f}` = ?"; $params[] = $json[$f]; }
        }
        if (!empty($sets)) {
            $params[] = $id;
            $this->db()->query("UPDATE purchase_orders SET " . implode(', ', $sets) . " WHERE id = ?", $params);
        }

        return $this->success(null, 'PO berhasil diupdate');
    }

    // POST /api/v1/purchase-orders/{id}/receive — terima barang + update stok
    public function receive($id)
    {
        $po = $this->db()->query(
            "SELECT * FROM purchase_orders WHERE id = ? AND outlet_id = ?",
            [$id, $this->currentOutletId()]
        )->getRowArray();
        if (!$po) return $this->notFound('PO tidak ditemukan');
        if ($po['status'] === 'cancelled') return $this->error('PO sudah dibatalkan');
        if ($po['status'] === 'received')  return $this->error('PO sudah fully received');

        $json  = $this->request->getJSON(true) ?? [];
        $items = $json['items'] ?? [];  // [{ line_id, qty_received }]
        if (empty($items)) return $this->error('Tidak ada item yang diterima');

        $userId    = $this->currentUserId();
        $outletId  = $this->currentOutletId();
        $allFulfilled = true;

        foreach ($items as $item) {
            $line = $this->db()->query(
                "SELECT * FROM purchase_order_lines WHERE id = ? AND po_id = ?",
                [$item['line_id'], $id]
            )->getRowArray();
            if (!$line) continue;

            $qtyReceive = (float)($item['qty_received'] ?? 0);
            if ($qtyReceive <= 0) continue;

            $newQtyReceived = (float)$line['qty_received'] + $qtyReceive;

            // Update qty_received di line
            $this->db()->query(
                "UPDATE purchase_order_lines SET qty_received = ? WHERE id = ?",
                [$newQtyReceived, $line['id']]
            );

            // Update stok bahan baku
            $mat = $this->db()->query(
                "SELECT stock_qty, cost_per_unit FROM raw_materials WHERE id = ?",
                [$line['material_id']]
            )->getRowArray();

            if ($mat) {
                $newStock = (float)$mat['stock_qty'] + $qtyReceive;
                // Update cost_per_unit pakai weighted average
                $newCost  = $line['unit_price'] > 0 ? (float)$line['unit_price'] : (float)$mat['cost_per_unit'];

                $this->db()->query(
                    "UPDATE raw_materials SET stock_qty = ?, cost_per_unit = ?, updated_at = NOW() WHERE id = ?",
                    [$newStock, $newCost, $line['material_id']]
                );

                // Catat stock movement
                helper(['uuid']);
                $this->db()->query("
                    INSERT INTO stock_movements
                        (id, outlet_id, material_id, reference_type, reference_id,
                         movement_type, qty, qty_before, qty_after, cost_per_unit,
                         notes, created_by)
                    VALUES (?, ?, ?, 'purchase_order', ?, 'in', ?, ?, ?, ?, ?, ?)
                ", [
                    generate_uuid(), $outletId,
                    $line['material_id'], $id,
                    $qtyReceive,
                    (float)$mat['stock_qty'],
                    $newStock,
                    $newCost,
                    "Penerimaan PO {$po['po_number']}",
                    $userId,
                ]);
            }

            if ($newQtyReceived < (float)$line['qty_ordered']) {
                $allFulfilled = false;
            }
        }

        // Update status PO
        $newStatus = $allFulfilled ? 'received' : 'partial';
        $this->db()->query(
            "UPDATE purchase_orders SET status = ?, received_date = ? WHERE id = ?",
            [$newStatus, date('Y-m-d'), $id]
        );

        return $this->success(
            ['status' => $newStatus],
            $allFulfilled
                ? 'Semua barang berhasil diterima, stok diupdate'
                : 'Penerimaan parsial dicatat, stok diupdate'
        );
    }

    // POST /api/v1/purchase-orders/{id}/cancel — batalkan PO
    public function cancel($id)
    {
        $po = $this->db()->query(
            "SELECT * FROM purchase_orders WHERE id = ? AND outlet_id = ?",
            [$id, $this->currentOutletId()]
        )->getRowArray();
        if (!$po) return $this->notFound('PO tidak ditemukan');
        if (in_array($po['status'], ['received', 'cancelled'])) {
            return $this->error('PO tidak bisa dibatalkan');
        }

        $this->db()->query(
            "UPDATE purchase_orders SET status = 'cancelled' WHERE id = ?", [$id]
        );
        return $this->success(null, 'PO berhasil dibatalkan');
    }
}
