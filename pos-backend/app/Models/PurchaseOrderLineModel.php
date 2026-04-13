<?php
namespace App\Models;
class PurchaseOrderLineModel extends BaseModel {
    protected $table = 'purchase_order_lines';
    protected $allowedFields = ['id','po_id','material_id','qty_ordered','qty_received','unit_price','subtotal'];
    protected $useTimestamps = false;
}
