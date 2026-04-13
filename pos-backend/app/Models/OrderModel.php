<?php
namespace App\Models;
class OrderModel extends BaseModel {
    protected $table = 'orders';
    protected $allowedFields = ['id','outlet_id','order_number','cashier_id','customer_id','order_type','table_number','status','subtotal','discount_total','grand_total','hpp_total','payment_status','notes','cancelled_at','cancel_reason','cancelled_by'];
    public function withDetails(): static {
        return $this->select('orders.*, users.name as cashier_name, customers.name as customer_name')
                    ->join('users','users.id = orders.cashier_id','left')
                    ->join('customers','customers.id = orders.customer_id','left');
    }
    public function getTodayCounter(string $outletId): int {
        return $this->where('outlet_id',$outletId)->where('DATE(created_at)',date('Y-m-d'))->countAllResults();
    }
}
