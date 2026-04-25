<?php
namespace App\Controllers\Api;

class ReportController extends BaseApiController
{
    private function db() { return \Config\Database::connect(); }

    private function dateRange(): array
    {
        $from = $this->request->getGet('from') ?? date('Y-m-d');
        $to   = $this->request->getGet('to')   ?? date('Y-m-d');
        return [$from, $to];
    }

    // ----------------------------------------------------------------
    // GET /api/v1/reports/sales?from=&to=
    // ----------------------------------------------------------------
    public function sales()
    {
        [$from, $to] = $this->dateRange();
        $outletId    = $this->currentOutletId();

        $summary = $this->db()->query("
            SELECT
                COUNT(*)                        AS total_orders,
                COALESCE(SUM(subtotal),0)       AS gross_revenue,
                COALESCE(SUM(discount_total),0) AS total_discount,
                COALESCE(SUM(grand_total),0)    AS net_revenue,
                COALESCE(SUM(hpp_total),0)      AS total_hpp,
                COALESCE(SUM(grand_total),0) - COALESCE(SUM(hpp_total),0) AS gross_profit
            FROM orders
            WHERE outlet_id = ?
              AND payment_status = 'paid'
              AND DATE(created_at) BETWEEN ? AND ?
        ", [$outletId, $from, $to])->getRowArray();

        // Data harian untuk chart trend
        $daily = $this->db()->query("
            SELECT
                DATE(created_at)                AS date,
                COUNT(*)                        AS orders,
                COALESCE(SUM(grand_total),0)    AS revenue,
                COALESCE(SUM(hpp_total),0)      AS hpp,
                COALESCE(SUM(discount_total),0) AS discount
            FROM orders
            WHERE outlet_id = ?
              AND payment_status = 'paid'
              AND DATE(created_at) BETWEEN ? AND ?
            GROUP BY DATE(created_at)
            ORDER BY date ASC
        ", [$outletId, $from, $to])->getResultArray();

        // Breakdown metode pembayaran
        $byPayment = $this->db()->query("
            SELECT
                p.method,
                COUNT(*)                     AS count,
                COALESCE(SUM(p.amount), 0)   AS total
            FROM payments p
            JOIN orders o ON o.id = p.order_id
            WHERE o.outlet_id = ?
              AND p.status = 'success'
              AND DATE(o.created_at) BETWEEN ? AND ?
            GROUP BY p.method
            ORDER BY total DESC
        ", [$outletId, $from, $to])->getResultArray();

        // Jam tersibuk (per jam)
        $byHour = $this->db()->query("
            SELECT
                HOUR(created_at)             AS hour,
                COUNT(*)                     AS orders,
                COALESCE(SUM(grand_total),0) AS revenue
            FROM orders
            WHERE outlet_id = ?
              AND payment_status = 'paid'
              AND DATE(created_at) BETWEEN ? AND ?
            GROUP BY HOUR(created_at)
            ORDER BY hour ASC
        ", [$outletId, $from, $to])->getResultArray();

        return $this->success(compact('summary', 'daily', 'byPayment', 'byHour'));
    }

    // ----------------------------------------------------------------
    // GET /api/v1/reports/products?from=&to=&limit=10
    // ----------------------------------------------------------------
    public function products()
    {
        [$from, $to] = $this->dateRange();
        $outletId    = $this->currentOutletId();
        $limit       = (int)($this->request->getGet('limit') ?? 10);

        $data = $this->db()->query("
            SELECT
                oi.name_snapshot                                        AS product_name,
                COALESCE(SUM(oi.qty), 0)                               AS total_qty,
                COALESCE(SUM(oi.subtotal), 0)                          AS total_revenue,
                COALESCE(SUM(oi.unit_hpp * oi.qty), 0)                 AS total_hpp,
                COALESCE(SUM(oi.subtotal), 0)
                    - COALESCE(SUM(oi.unit_hpp * oi.qty), 0)           AS profit
            FROM order_items oi
            JOIN orders o ON o.id = oi.order_id
            WHERE o.outlet_id = ?
              AND o.payment_status = 'paid'
              AND DATE(o.created_at) BETWEEN ? AND ?
            GROUP BY oi.name_snapshot
            ORDER BY total_qty DESC
            LIMIT ?
        ", [$outletId, $from, $to, $limit])->getResultArray();

        return $this->success($data);
    }

    // ----------------------------------------------------------------
    // GET /api/v1/reports/stock
    // ----------------------------------------------------------------
    public function stock()
    {
        $outletId = $this->currentOutletId();

        $data = $this->db()->query("
            SELECT
                id, code, name, unit,
                stock_qty, min_stock, cost_per_unit,
                ROUND(stock_qty * cost_per_unit, 2) AS stock_value,
                CASE WHEN stock_qty <= min_stock THEN 1 ELSE 0 END AS is_low
            FROM raw_materials
            WHERE outlet_id = ?
              AND active = 1
            ORDER BY is_low DESC, name ASC
        ", [$outletId])->getResultArray();

        $totalValue = array_sum(array_column($data, 'stock_value'));
        $lowCount   = count(array_filter($data, fn($r) => (int)$r['is_low'] === 1));

        return $this->success([
            'items'       => $data,
            'total_value' => round($totalValue, 2),
            'low_stock'   => $lowCount,
        ]);
    }

    // ----------------------------------------------------------------
    // GET /api/v1/reports/hpp?from=&to=
    // ----------------------------------------------------------------
    public function hpp()
    {
        [$from, $to] = $this->dateRange();
        $outletId    = $this->currentOutletId();

        $data = $this->db()->query("
            SELECT
                oi.name_snapshot,
                COALESCE(SUM(oi.qty), 0)                           AS qty_sold,
                COALESCE(AVG(oi.unit_price), 0)                    AS avg_price,
                COALESCE(AVG(oi.unit_hpp), 0)                      AS avg_hpp,
                COALESCE(AVG(oi.unit_price), 0)
                    - COALESCE(AVG(oi.unit_hpp), 0)                AS avg_margin,
                CASE
                    WHEN COALESCE(AVG(oi.unit_price), 0) > 0
                    THEN ROUND(
                        (COALESCE(AVG(oi.unit_price), 0) - COALESCE(AVG(oi.unit_hpp), 0))
                        / COALESCE(AVG(oi.unit_price), 0) * 100, 2
                    )
                    ELSE 0
                END AS margin_pct
            FROM order_items oi
            JOIN orders o ON o.id = oi.order_id
            WHERE o.outlet_id = ?
              AND o.payment_status = 'paid'
              AND DATE(o.created_at) BETWEEN ? AND ?
            GROUP BY oi.name_snapshot
            ORDER BY margin_pct DESC
        ", [$outletId, $from, $to])->getResultArray();

        return $this->success($data);
    }

    // ----------------------------------------------------------------
    // GET /api/v1/reports/cashflow?from=&to=
    // ----------------------------------------------------------------
    public function cashflow()
    {
        [$from, $to] = $this->dateRange();
        $outletId    = $this->currentOutletId();

        $movements = $this->db()->query("
            SELECT
                DATE(created_at) AS date,
                kas_type,
                type,
                COALESCE(SUM(amount), 0) AS total
            FROM kas_transactions
            WHERE outlet_id = ?
              AND DATE(created_at) BETWEEN ? AND ?
            GROUP BY DATE(created_at), kas_type, type
            ORDER BY date ASC
        ", [$outletId, $from, $to])->getResultArray();

        $balanceBesar = $this->db()->query(
            "SELECT balance_after FROM kas_transactions
             WHERE outlet_id = ? AND kas_type = 'besar'
             ORDER BY created_at DESC LIMIT 1",
            [$outletId]
        )->getRowArray();

        return $this->success([
            'movements'     => $movements,
            'kas_besar_bal' => (float)($balanceBesar['balance_after'] ?? 0),
        ]);
    }

    // ----------------------------------------------------------------
    // GET /api/v1/reports/profit-loss?from=&to=
    // ----------------------------------------------------------------
    public function profitLoss()
    {
        [$from, $to] = $this->dateRange();
        $outletId    = $this->currentOutletId();

        $row = $this->db()->query("
            SELECT
                COALESCE(SUM(grand_total),    0) AS net_revenue,
                COALESCE(SUM(hpp_total),      0) AS total_hpp,
                COALESCE(SUM(discount_total), 0) AS total_discount,
                COALESCE(SUM(subtotal),       0) AS gross_revenue
            FROM orders
            WHERE outlet_id = ?
              AND payment_status = 'paid'
              AND DATE(created_at) BETWEEN ? AND ?
        ", [$outletId, $from, $to])->getRowArray();

        $netRevenue  = (float)($row['net_revenue']  ?? 0);
        $totalHpp    = (float)($row['total_hpp']    ?? 0);
        $totalDisc   = (float)($row['total_discount'] ?? 0);
        $grossProfit = $netRevenue - $totalHpp;
        $marginPct   = $netRevenue > 0
            ? round($grossProfit / $netRevenue * 100, 2)
            : 0;

        return $this->success([
            'period'           => compact('from', 'to'),
            'net_revenue'      => $netRevenue,
            'total_hpp'        => $totalHpp,
            'total_discount'   => $totalDisc,
            'gross_revenue'    => (float)($row['gross_revenue'] ?? 0),
            'gross_profit'     => $grossProfit,
            'gross_margin_pct' => $marginPct,
        ]);
    }

    // ----------------------------------------------------------------
    // GET /api/v1/reports/discounts?from=&to=
    // ----------------------------------------------------------------
    public function discounts()
    {
        [$from, $to] = $this->dateRange();
        $outletId    = $this->currentOutletId();

        $data = $this->db()->query("
            SELECT
                odl.discount_name,
                odl.discount_code,
                COUNT(*)                        AS usage_count,
                COALESCE(SUM(odl.amount), 0)    AS total_discount
            FROM order_discount_log odl
            JOIN orders o ON o.id = odl.order_id
            WHERE o.outlet_id = ?
              AND DATE(o.created_at) BETWEEN ? AND ?
            GROUP BY odl.discount_name, odl.discount_code
            ORDER BY total_discount DESC
        ", [$outletId, $from, $to])->getResultArray();

        return $this->success($data);
    }

    public function balanceSheet()
    {
        return $this->success([], 'Coming soon — phase 2');
    }
}
