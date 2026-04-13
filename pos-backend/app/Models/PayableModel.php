<?php
namespace App\Models;
class PayableModel extends BaseModel {
    protected $table = 'payables';
    protected $allowedFields = ['id','outlet_id','supplier_id','po_id','invoice_number','invoice_date','due_date','amount','paid_amount','status','notes'];
    public function withSupplier(): static {
        return $this->select('payables.*, suppliers.name as supplier_name')
                    ->join('suppliers','suppliers.id = payables.supplier_id','left');
    }
}
