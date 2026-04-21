<?php
namespace App\Controllers\Api;

use App\Models\ShiftModel;

class ShiftController extends BaseApiController
{
    protected ShiftModel $model;

    public function __construct()
    {
        helper(['uuid', 'code']);
        $this->model = new ShiftModel();
    }

    // ----------------------------------------------------------------
    // GET /api/v1/shifts — list shift dengan filter tanggal
    // ----------------------------------------------------------------
    public function index()
    {
        $outletId = $this->currentOutletId();
        $page     = (int)($this->request->getGet('page')     ?? 1);
        $perPage  = (int)($this->request->getGet('per_page') ?? 20);
        $date     = $this->request->getGet('date')   ?? date('Y-m-d');
        $status   = $this->request->getGet('status') ?? '';

        $builder = $this->model
            ->select('shifts.*, users.name as cashier_name, u2.name as closed_by_name')
            ->join('users',    'users.id = shifts.cashier_id', 'left')
            ->join('users u2', 'u2.id    = shifts.closed_by',  'left')
            ->where('shifts.outlet_id', $outletId)
            ->where('DATE(shifts.opened_at)', $date);

        if ($status) $builder->where('shifts.status', $status);

        $total = $builder->countAllResults(false);
        $data  = $builder->orderBy('shifts.opened_at', 'DESC')
                         ->paginate($perPage, 'default', $page);

        return $this->success($this->paginate($data, $total, $page, $perPage));
    }

    // ----------------------------------------------------------------
    // GET /api/v1/shifts/active — shift yang sedang open
    // ----------------------------------------------------------------
    public function active()
    {
        $shift = $this->model->getOpenShift($this->currentOutletId());
        return $this->success($shift); // null kalau tidak ada shift aktif
    }

    // ----------------------------------------------------------------
    // GET /api/v1/shifts/{id} — detail shift + ringkasan
    // ----------------------------------------------------------------
    public function show($id)
    {
        $shift = $this->model
            ->select('shifts.*, users.name as cashier_name, u2.name as closed_by_name')
            ->join('users',    'users.id = shifts.cashier_id', 'left')
            ->join('users u2', 'u2.id    = shifts.closed_by',  'left')
            ->find($id);

        if (! $shift) return $this->notFound('Shift tidak ditemukan');

        // Lampirkan ringkasan order dalam shift ini
        $shift['summary'] = $this->model->calculateSummary($id);

        // Lampirkan daftar order
        $db = \Config\Database::connect();
        $shift['orders'] = $db->query("
            SELECT o.order_number, o.grand_total, o.payment_status,
                   o.status, o.created_at,
                   p.method as payment_method
            FROM orders o
            LEFT JOIN payments p ON p.order_id = o.id AND p.status = 'success'
            WHERE o.shift_id = ?
            ORDER BY o.created_at ASC
        ", [$id])->getResultArray();

        return $this->success($shift);
    }

    // ----------------------------------------------------------------
    // POST /api/v1/shifts/open — buka shift baru
    // ----------------------------------------------------------------
    public function open()
    {
        $outletId = $this->currentOutletId();
    $userId   = $this->currentUserId();
    $json     = $this->request->getJSON(true) ?? [];

    // Cek tidak ada shift yang masih open
    $existing = $this->model->getOpenShift($outletId);
    if ($existing) {
        return $this->error(
            "Masih ada shift aktif ({$existing['code']}) yang belum ditutup.",
            409
        );
    }

    // Tentukan cashier_id:
    // - Kalau request tidak menyertakan cashier_id → pakai user yang login
    // - Kalau ada cashier_id di request → pakai itu (untuk manager assign ke kasir lain)
    $cashierId = !empty($json['cashier_id'])
        ? $json['cashier_id']
        : $userId;

    $openingCash = (float)($json['opening_cash'] ?? 0);
    $notes       = $json['notes_open'] ?? null;

    $id   = generate_uuid();
    $code = generate_code('shifts', $outletId);

    $this->model->insert([
        'id'           => $id,
        'outlet_id'    => $outletId,
        'code'         => $code,
        'cashier_id'   => $cashierId,   // kasir yang bertugas
        'opened_by'    => $userId,      // siapa yang buka (bisa manager)
        'status'       => 'open',
        'opening_cash' => $openingCash,
        'notes_open'   => $notes,
        'opened_at'    => date('Y-m-d H:i:s'),
    ]);

    $shift = $this->model->find($id);
    return $this->created($shift, "Shift {$code} berhasil dibuka");
    }

    // ----------------------------------------------------------------
    // POST /api/v1/shifts/{id}/close — tutup shift
    // ----------------------------------------------------------------
    public function close($id)
    {
        $shift = $this->model->find($id);
        if (! $shift)                        return $this->notFound('Shift tidak ditemukan');
        if ($shift['status'] === 'closed')   return $this->error('Shift sudah ditutup');
        if ($shift['outlet_id'] !== $this->currentOutletId()) return $this->unauthorized();

        $json        = $this->request->getJSON(true) ?? [];
        $closingCash = (float)($json['closing_cash'] ?? 0);
        $notes       = $json['notes_close'] ?? null;

        // Hitung summary otomatis dari order dalam shift
        $summary = $this->model->calculateSummary($id);

        $expectedCash = (float)$shift['opening_cash'] + (float)($summary['total_cash'] ?? 0);
        $difference   = $closingCash - $expectedCash;

        $this->model->update($id, [
            'status'        => 'closed',
            'closed_by'     => $this->currentUserId(),
            'closing_cash'  => $closingCash,
            'expected_cash' => $expectedCash,
            'difference'    => $difference,
            'total_orders'  => (int)($summary['total_orders']  ?? 0),
            'total_revenue' => (float)($summary['total_revenue'] ?? 0),
            'total_cash'    => (float)($summary['total_cash']    ?? 0),
            'total_qris'    => (float)($summary['total_qris']    ?? 0),
            'total_edc'     => (float)($summary['total_edc']     ?? 0),
            'total_ewallet' => (float)($summary['total_ewallet'] ?? 0),
            'total_discount'=> (float)($summary['total_discount'] ?? 0),
            'notes_close'   => $notes,
            'closed_at'     => date('Y-m-d H:i:s'),
        ]);

        $updated = $this->model->find($id);
        $updated['summary'] = $summary;

        return $this->success($updated, 'Shift berhasil ditutup');
    }

    // ----------------------------------------------------------------
    // GET /api/v1/shifts/{id}/zreport — Z-Report untuk print
    // ----------------------------------------------------------------
    public function zreport($id)
    {
        $shift = $this->model
            ->select('shifts.*, users.name as cashier_name, u2.name as closed_by_name')
            ->join('users',    'users.id = shifts.cashier_id', 'left')
            ->join('users u2', 'u2.id    = shifts.closed_by',  'left')
            ->find($id);

        if (! $shift) return $this->notFound('Shift tidak ditemukan');

        $shift['summary'] = $this->model->calculateSummary($id);

        // Top produk dalam shift ini
        $db = \Config\Database::connect();
        $shift['top_products'] = $db->query("
            SELECT oi.name_snapshot, SUM(oi.qty) as qty, SUM(oi.subtotal) as revenue
            FROM order_items oi
            JOIN orders o ON o.id = oi.order_id
            WHERE o.shift_id = ? AND o.payment_status = 'paid'
            GROUP BY oi.name_snapshot
            ORDER BY qty DESC
            LIMIT 5
        ", [$id])->getResultArray();

        return $this->success($shift);
    }
}
