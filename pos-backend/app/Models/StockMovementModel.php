<?php
namespace App\Models;
class StockMovementModel extends BaseModel {
    protected $table = 'stock_movements';
    protected $allowedFields = ['id','outlet_id','material_id','reference_type','reference_id','movement_type','qty','qty_before','qty_after','cost_per_unit','notes','created_by'];
    protected $useTimestamps = false;
    protected $createdField = 'moved_at';
}
