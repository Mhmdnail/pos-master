<?php
namespace App\Models;
class PurchaseOrderModel extends BaseModel {
    protected $table = 'purchase_orders';
    protected $allowedFields = ['id','outlet_id','supplier_id','po_number','status','order_date','expected_date','received_date','subtotal','tax_amount','total_amount','notes','created_by'];
    public function withSupplier(): static {
        return $this->select('purchase_orders.*, suppliers.name as supplier_name')
                    ->join('suppliers','suppliers.id = purchase_orders.supplier_id','left');
    }
}
