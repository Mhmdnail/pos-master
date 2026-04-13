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

    // GET /api/v1/reports/sales?from=2026-01-01&to=2026-01-31
    public function sales()
    {
        [$from, $to] = $this->dateRange();
        $outletId    = $this->currentOutletId();

        $summary = $this->db()->query("
            SELECT
                COUNT(*) as total_orders,
                SUM(subtotal) as gross_revenue,
                SUM(discount_total) as total_discount,
                SUM(grand_total) as net_revenue,
                SUM(hpp_total) as total_hpp,
                SUM(grand_total) - SUM(hpp_total) as gross_profit
            FROM orders
            WHERE outlet_id = ? AND payment_status = 'paid'
              AND DATE(created_at) BETWEEN ? AND ?
        ", [$outletId, $from, $to])->getRowArray();

        $daily = $this->db()->query("
            SELECT
                DATE(created_at) as date,
                COUNT(*) as orders,
                SUM(grand_total) as revenue,
                SUM(hpp_total) as hpp
            FROM orders
            WHERE outlet_id = ? AND payment_status = 'paid'
              AND DATE(created_at) BETWEEN ? AND ?
            GROUP BY DATE(created_at)
            ORDER BY date ASC
        ", [$outletId, $from, $to])->getResultArray();

        $byPayment = $this->db()->query("
            SELECT method, COUNT(*) as count, SUM(amount) as total
            FROM payments p
            JOIN orders o ON o.id = p.order_id
            WHERE o.outlet_id = ? AND p.status = 'success'
              AND DATE(p.created_at) BETWEEN ? AND ?
            GROUP BY method
        ", [$outletId, $from, $to])->getResultArray();

        return $this->success(compact('summary','daily','byPayment'));
    }

    // GET /api/v1/reports/products?from=&to=&limit=10
    public function products()
    {
        [$from, $to] = $this->dateRange();
        $outletId    = $this->currentOutletId();
        $limit       = (int)($this->request->getGet('limit') ?? 10);

        $data = $this->db()->query("
            SELECT
                oi.name_snapshot as product_name,
                SUM(oi.qty) as total_qty,
                SUM(oi.subtotal) as total_revenue,
                SUM(oi.unit_hpp * oi.qty) as total_hpp,
                SUM(oi.subtotal) - SUM(oi.unit_hpp * oi.qty) as profit
            FROM order_items oi
            JOIN orders o ON o.id = oi.order_id
            WHERE o.outlet_id = ? AND o.payment_status = 'paid'
              AND DATE(o.created_at) BETWEEN ? AND ?
            GROUP BY oi.name_snapshot
            ORDER BY total_qty DESC
            LIMIT ?
        ", [$outletId, $from, $to, $limit])->getResultArray();

        return $this->success($data);
    }

    // GET /api/v1/reports/stock
    public function stock()
    {
        $outletId = $this->currentOutletId();

        $data = $this->db()->query("
            SELECT
                id, code, name, unit,
                stock_qty, min_stock, cost_per_unit,
                stock_qty * cost_per_unit as stock_value,
                CASE WHEN stock_qty <= min_stock THEN 1 ELSE 0 END as is_low
            FROM raw_materials
            WHERE outlet_id = ? AND active = 1
            ORDER BY name ASC
        ", [$outletId])->getResultArray();

        $totalValue = array_sum(array_column($data,'stock_value'));
        $lowCount   = count(array_filter($data, fn($r) => $r['is_low']));

        return $this->success([
            'items'       => $data,
            'total_value' => $totalValue,
            'low_stock'   => $lowCount,
        ]);
    }

    // GET /api/v1/reports/hpp?from=&to=
    public function hpp()
    {
        [$from, $to] = $this->dateRange();
        $outletId    = $this->currentOutletId();

        $data = $this->db()->query("
            SELECT
                oi.name_snapshot,
                SUM(oi.qty) as qty_sold,
                AVG(oi.unit_price) as avg_price,
                AVG(oi.unit_hpp) as avg_hpp,
                AVG(oi.unit_price) - AVG(oi.unit_hpp) as avg_margin,
                ROUND((AVG(oi.unit_price) - AVG(oi.unit_hpp)) / AVG(oi.unit_price) * 100, 2) as margin_pct
            FROM order_items oi
            JOIN orders o ON o.id = oi.order_id
            WHERE o.outlet_id = ? AND o.payment_status = 'paid'
              AND DATE(o.created_at) BETWEEN ? AND ?
            GROUP BY oi.name_snapshot
            ORDER BY margin_pct DESC
        ", [$outletId, $from, $to])->getResultArray();

        return $this->success($data);
    }

    // GET /api/v1/reports/cashflow?from=&to=
    public function cashflow()
    {
        [$from, $to] = $this->dateRange();
        $outletId    = $this->currentOutletId();

        $data = $this->db()->query("
            SELECT
                DATE(created_at) as date,
                kas_type,
                type,
                SUM(amount) as total
            FROM kas_transactions
            WHERE outlet_id = ? AND DATE(created_at) BETWEEN ? AND ?
            GROUP BY DATE(created_at), kas_type, type
            ORDER BY date ASC
        ", [$outletId, $from, $to])->getResultArray();

        $balanceBesar = \Config\Database::connect()->query(
            "SELECT balance_after FROM kas_transactions WHERE outlet_id=? AND kas_type='besar' ORDER BY created_at DESC LIMIT 1",
            [$outletId]
        )->getRowArray();

        return $this->success([
            'movements'     => $data,
            'kas_besar_bal' => (float)($balanceBesar['balance_after'] ?? 0),
        ]);
    }

    // GET /api/v1/reports/profit-loss?from=&to=
    public function profitLoss()
    {
        [$from, $to] = $this->dateRange();
        $outletId    = $this->currentOutletId();

        $revenue = $this->db()->query("
            SELECT COALESCE(SUM(grand_total),0) as val FROM orders
            WHERE outlet_id=? AND payment_status='paid' AND DATE(created_at) BETWEEN ? AND ?
        ", [$outletId,$from,$to])->getRowArray();

        $hpp = $this->db()->query("
            SELECT COALESCE(SUM(hpp_total),0) as val FROM orders
            WHERE outlet_id=? AND payment_status='paid' AND DATE(created_at) BETWEEN ? AND ?
        ", [$outletId,$from,$to])->getRowArray();

        $discount = $this->db()->query("
            SELECT COALESCE(SUM(discount_total),0) as val FROM orders
            WHERE outlet_id=? AND payment_status='paid' AND DATE(created_at) BETWEEN ? AND ?
        ", [$outletId,$from,$to])->getRowArray();

        $netRevenue  = (float)$revenue['val'];
        $grossProfit = $netRevenue - (float)$hpp['val'];

        return $this->success([
            'period'       => compact('from','to'),
            'net_revenue'  => $netRevenue,
            'total_hpp'    => (float)$hpp['val'],
            'gross_profit' => $grossProfit,
            'total_discount'=> (float)$discount['val'],
            'gross_margin_pct' => $netRevenue > 0 ? round($grossProfit/$netRevenue*100,2) : 0,
        ]);
    }

    public function balanceSheet() { return $this->success([],'Coming soon — phase 2'); }
    public function discounts()
    {
        [$from,$to] = $this->dateRange();
        $outletId   = $this->currentOutletId();
        $data = $this->db()->query("
            SELECT discount_name, discount_code, COUNT(*) as usage_count, SUM(amount) as total_discount
            FROM order_discount_log odl
            JOIN orders o ON o.id = odl.order_id
            WHERE o.outlet_id = ? AND DATE(o.created_at) BETWEEN ? AND ?
            GROUP BY discount_name, discount_code
            ORDER BY total_discount DESC
        ", [$outletId,$from,$to])->getResultArray();
        return $this->success($data);
    }
}
