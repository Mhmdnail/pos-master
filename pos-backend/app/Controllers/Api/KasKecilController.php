<?php
namespace App\Controllers\Api;

class KasKecilController extends BaseApiController
{
    private function db() { return \Config\Database::connect(); }

    // ----------------------------------------------------------------
    // GET /api/v1/kas-kecil?date=2026-04-25&from=&to=
    // ----------------------------------------------------------------
    public function index()
    {
        $outletId = $this->currentOutletId();
        $page     = (int)($this->request->getGet('page')     ?? 1);
        $perPage  = (int)($this->request->getGet('per_page') ?? 50);
        $from     = $this->request->getGet('from')  ?? date('Y-m-d');
        $to       = $this->request->getGet('to')    ?? date('Y-m-d');

        $total = $this->db()->query("
            SELECT COUNT(*) as cnt
            FROM kas_kecil_transactions
            WHERE outlet_id = ?
              AND DATE(created_at) BETWEEN ? AND ?
        ", [$outletId, $from, $to])->getRowArray()['cnt'] ?? 0;

        $data = $this->db()->query("
            SELECT k.*, u.name as created_by_name
            FROM kas_kecil_transactions k
            LEFT JOIN users u ON u.id = k.created_by
            WHERE k.outlet_id = ?
              AND DATE(k.created_at) BETWEEN ? AND ?
            ORDER BY k.created_at DESC
            LIMIT ? OFFSET ?
        ", [$outletId, $from, $to, $perPage, ($page - 1) * $perPage])->getResultArray();

        return $this->success($this->paginate($data, $total, $page, $perPage));
    }

    // ----------------------------------------------------------------
    // GET /api/v1/kas-kecil/summary — saldo + ringkasan
    // ----------------------------------------------------------------
    public function summary()
    {
        $outletId = $this->currentOutletId();
        $from     = $this->request->getGet('from') ?? date('Y-m-d');
        $to       = $this->request->getGet('to')   ?? date('Y-m-d');

        // Saldo terakhir (balance_after dari transaksi terbaru)
        $lastTrx = $this->db()->query("
            SELECT balance_after FROM kas_kecil_transactions
            WHERE outlet_id = ?
            ORDER BY created_at DESC LIMIT 1
        ", [$outletId])->getRowArray();
        $saldo = (float)($lastTrx['balance_after'] ?? 0);

        // Ringkasan periode
        $period = $this->db()->query("
            SELECT
                COALESCE(SUM(CASE WHEN type = 'in'  THEN amount ELSE 0 END), 0) AS total_in,
                COALESCE(SUM(CASE WHEN type = 'out' THEN amount ELSE 0 END), 0) AS total_out,
                COUNT(*) AS total_trx
            FROM kas_kecil_transactions
            WHERE outlet_id = ?
              AND DATE(created_at) BETWEEN ? AND ?
        ", [$outletId, $from, $to])->getRowArray();

        // Pengeluaran per kategori dalam periode
        $byCategory = $this->db()->query("
            SELECT category,
                   COUNT(*) as count,
                   SUM(amount) as total
            FROM kas_kecil_transactions
            WHERE outlet_id = ? AND type = 'out'
              AND DATE(created_at) BETWEEN ? AND ?
            GROUP BY category
            ORDER BY total DESC
        ", [$outletId, $from, $to])->getResultArray();

        return $this->success([
            'saldo'       => $saldo,
            'period'      => compact('from', 'to'),
            'total_in'    => (float)($period['total_in']  ?? 0),
            'total_out'   => (float)($period['total_out'] ?? 0),
            'total_trx'   => (int)($period['total_trx']   ?? 0),
            'by_category' => $byCategory,
        ]);
    }

    // ----------------------------------------------------------------
    // POST /api/v1/kas-kecil — tambah transaksi baru
    // ----------------------------------------------------------------
    public function create()
    {
        helper(['uuid', 'code']);
        $json = $this->request->getJSON(true) ?? [];

        $rules = [
            'type'     => 'required|in_list[in,out]',
            'amount'   => 'required|numeric|greater_than[0]',
            'category' => 'required|max_length[100]',
        ];
        if (!$this->validate($rules)) {
            return $this->error('Validasi gagal', 422, $this->validator->getErrors());
        }

        $outletId = $this->currentOutletId();
        $userId   = $this->currentUserId();
        $amount   = (float)$json['amount'];
        $type     = $json['type'];

        // Hitung saldo terbaru
        $lastTrx = $this->db()->query("
            SELECT balance_after FROM kas_kecil_transactions
            WHERE outlet_id = ? ORDER BY created_at DESC LIMIT 1
        ", [$outletId])->getRowArray();
        $currentBalance = (float)($lastTrx['balance_after'] ?? 0);

        // Validasi saldo tidak boleh minus untuk pengeluaran
        if ($type === 'out' && $amount > $currentBalance) {
            return $this->error(
                "Saldo kas kecil tidak mencukupi. Saldo saat ini: Rp " .
                number_format($currentBalance, 0, ',', '.'),
                422
            );
        }

        $newBalance = $type === 'in'
            ? $currentBalance + $amount
            : $currentBalance - $amount;

        $id = generate_uuid();
        $this->db()->query("
            INSERT INTO kas_kecil_transactions
                (id, outlet_id, shift_id, type, category, amount, balance_after,
                 description, reference_no, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ", [
            $id,
            $outletId,
            $json['shift_id']     ?? null,
            $type,
            $json['category'],
            $amount,
            $newBalance,
            $json['description']  ?? null,
            $json['reference_no'] ?? null,
            $userId,
        ]);

        return $this->created([
            'id'            => $id,
            'type'          => $type,
            'amount'        => $amount,
            'balance_after' => $newBalance,
            'category'      => $json['category'],
        ], 'Transaksi kas kecil berhasil dicatat');
    }

    // ----------------------------------------------------------------
    // DELETE /api/v1/kas-kecil/{id} — hapus transaksi (hanya hari ini)
    // ----------------------------------------------------------------
    public function delete($id)
    {
        $trx = $this->db()->query(
            "SELECT * FROM kas_kecil_transactions WHERE id = ? AND outlet_id = ?",
            [$id, $this->currentOutletId()]
        )->getRowArray();

        if (!$trx) return $this->notFound('Transaksi tidak ditemukan');

        // Hanya boleh hapus transaksi hari ini
        if (date('Y-m-d', strtotime($trx['created_at'])) !== date('Y-m-d')) {
            return $this->error('Hanya transaksi hari ini yang bisa dihapus', 422);
        }

        $this->db()->query(
            "DELETE FROM kas_kecil_transactions WHERE id = ?",
            [$id]
        );

        return $this->success(null, 'Transaksi berhasil dihapus');
    }

    // ----------------------------------------------------------------
    // GET /api/v1/kas-kecil/categories — list kategori default
    // ----------------------------------------------------------------
    public function categories()
    {
        $categories = [
            ['value' => 'Pembelian bahan baku',   'icon' => '🛒'],
            ['value' => 'Transport & pengiriman',  'icon' => '🚗'],
            ['value' => 'Listrik & air',           'icon' => '💡'],
            ['value' => 'Kebersihan & sanitasi',   'icon' => '🧹'],
            ['value' => 'Peralatan & perlengkapan','icon' => '🔧'],
            ['value' => 'Konsumsi karyawan',       'icon' => '🍱'],
            ['value' => 'Administrasi & ATK',      'icon' => '📋'],
            ['value' => 'Perbaikan & maintenance', 'icon' => '⚙️'],
            ['value' => 'Lain-lain',               'icon' => '📌'],
        ];
        return $this->success($categories);
    }
}
