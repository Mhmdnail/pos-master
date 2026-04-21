<?php
namespace App\Models;

class ShiftModel extends BaseModel
{
    protected $table         = 'shifts';
    protected $allowedFields = [
        'id','outlet_id','code','cashier_id','opened_by','closed_by',
        'status','opening_cash','closing_cash','expected_cash','difference',
        'total_orders','total_revenue','total_cash','total_qris',
        'total_edc','total_ewallet','total_discount',
        'notes_open','notes_close','opened_at','closed_at',
    ];
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    /**
     * Ambil shift yang sedang open untuk outlet tertentu
     */
    public function getOpenShift(string $outletId): ?array
    {
        return $this->select('shifts.*, users.name as cashier_name')
                    ->join('users', 'users.id = shifts.cashier_id', 'left')
                    ->where('shifts.outlet_id', $outletId)
                    ->where('shifts.status', 'open')
                    ->orderBy('shifts.opened_at', 'DESC')
                    ->first();
    }

    /**
     * Hitung ringkasan order dalam shift ini
     */
    public function calculateSummary(string $shiftId): array
    {
        $db = \Config\Database::connect();

        $summary = $db->query("
            SELECT
                COUNT(*)                                            AS total_orders,
                COALESCE(SUM(grand_total), 0)                      AS total_revenue,
                COALESCE(SUM(discount_total), 0)                   AS total_discount,
                COALESCE(SUM(CASE WHEN p.method = 'cash'    THEN p.amount ELSE 0 END), 0) AS total_cash,
                COALESCE(SUM(CASE WHEN p.method = 'qris'    THEN p.amount ELSE 0 END), 0) AS total_qris,
                COALESCE(SUM(CASE WHEN p.method = 'edc'     THEN p.amount ELSE 0 END), 0) AS total_edc,
                COALESCE(SUM(CASE WHEN p.method = 'ewallet' THEN p.amount ELSE 0 END), 0) AS total_ewallet
            FROM orders o
            LEFT JOIN payments p ON p.order_id = o.id AND p.status = 'success'
            WHERE o.shift_id       = ?
              AND o.payment_status = 'paid'
        ", [$shiftId])->getRowArray();

        return $summary ?? [];
    }
}
