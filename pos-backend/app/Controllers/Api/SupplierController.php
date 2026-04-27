<?php
namespace App\Controllers\Api;

class SupplierController extends BaseApiController
{
    private function db() { return \Config\Database::connect(); }

    // GET /api/v1/suppliers
    public function index()
    {
        $outletId = $this->currentOutletId();
        $page     = (int)($this->request->getGet('page')     ?? 1);
        $perPage  = (int)($this->request->getGet('per_page') ?? 50);
        $search   = $this->request->getGet('search') ?? '';

        $query = "SELECT * FROM suppliers WHERE outlet_id = ?";
        $params = [$outletId];
        if ($search) {
            $query .= " AND (name LIKE ? OR contact_name LIKE ? OR phone LIKE ?)";
            $params[] = "%{$search}%"; $params[] = "%{$search}%"; $params[] = "%{$search}%";
        }

        $total = $this->db()->query(
            "SELECT COUNT(*) as cnt FROM suppliers WHERE outlet_id = ?" .
            ($search ? " AND (name LIKE ? OR contact_name LIKE ? OR phone LIKE ?)" : ""),
            $search ? [$outletId, "%{$search}%", "%{$search}%", "%{$search}%"] : [$outletId]
        )->getRowArray()['cnt'] ?? 0;

        $data = $this->db()->query(
            $query . " ORDER BY name ASC LIMIT ? OFFSET ?",
            array_merge($params, [$perPage, ($page - 1) * $perPage])
        )->getResultArray();

        return $this->success($this->paginate($data, $total, $page, $perPage));
    }

    // GET /api/v1/suppliers/{id}
    public function show($id)
    {
        $supplier = $this->db()->query(
            "SELECT * FROM suppliers WHERE id = ? AND outlet_id = ?",
            [$id, $this->currentOutletId()]
        )->getRowArray();

        if (!$supplier) return $this->notFound('Supplier tidak ditemukan');

        // Lampirkan riwayat PO
        $supplier['recent_po'] = $this->db()->query(
            "SELECT po_number, status, order_date, total_amount
             FROM purchase_orders WHERE supplier_id = ?
             ORDER BY order_date DESC LIMIT 5",
            [$id]
        )->getResultArray();

        return $this->success($supplier);
    }

    // POST /api/v1/suppliers
    public function create()
    {
        helper(['uuid', 'code']);
        $json  = $this->request->getJSON(true) ?? [];
        $rules = ['name' => 'required|max_length[150]'];
        if (!$this->validate($rules)) {
            return $this->error('Validasi gagal', 422, $this->validator->getErrors());
        }

        $outletId = $this->currentOutletId();
        $id       = generate_uuid();

        $this->db()->query("
            INSERT INTO suppliers
                (id, code, outlet_id, name, contact_name, phone, email,
                 address, npwp, bank_name, bank_account, bank_holder, notes, active)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
        ", [
            $id,
            generate_code('suppliers', $outletId),
            $outletId,
            $json['name'],
            $json['contact_name'] ?? null,
            $json['phone']        ?? null,
            $json['email']        ?? null,
            $json['address']      ?? null,
            $json['npwp']         ?? null,
            $json['bank_name']    ?? null,
            $json['bank_account'] ?? null,
            $json['bank_holder']  ?? null,
            $json['notes']        ?? null,
        ]);

        return $this->created(['id' => $id], 'Supplier berhasil ditambahkan');
    }

    // PUT /api/v1/suppliers/{id}
    public function update($id)
    {
        $supplier = $this->db()->query(
            "SELECT id FROM suppliers WHERE id = ? AND outlet_id = ?",
            [$id, $this->currentOutletId()]
        )->getRowArray();
        if (!$supplier) return $this->notFound('Supplier tidak ditemukan');

        $json    = $this->request->getJSON(true) ?? [];
        $allowed = ['name','contact_name','phone','email','address','npwp',
                    'bank_name','bank_account','bank_holder','notes','active'];
        $sets    = [];
        $params  = [];
        foreach ($allowed as $field) {
            if (array_key_exists($field, $json)) {
                $sets[]   = "`{$field}` = ?";
                $params[] = $json[$field];
            }
        }
        if (empty($sets)) return $this->error('Tidak ada data yang diubah');

        $params[] = $id;
        $this->db()->query("UPDATE suppliers SET " . implode(', ', $sets) . " WHERE id = ?", $params);

        return $this->success(null, 'Supplier berhasil diupdate');
    }

    // PATCH /api/v1/suppliers/{id}/toggle
    public function toggle($id)
    {
        $supplier = $this->db()->query(
            "SELECT id, active FROM suppliers WHERE id = ? AND outlet_id = ?",
            [$id, $this->currentOutletId()]
        )->getRowArray();
        if (!$supplier) return $this->notFound('Supplier tidak ditemukan');

        $newStatus = (int)$supplier['active'] === 1 ? 0 : 1;
        $this->db()->query("UPDATE suppliers SET active = ? WHERE id = ?", [$newStatus, $id]);

        return $this->success(
            ['active' => $newStatus],
            $newStatus ? 'Supplier diaktifkan' : 'Supplier dinonaktifkan'
        );
    }
}
