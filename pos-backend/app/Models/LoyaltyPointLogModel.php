<?php
namespace App\Models;
class LoyaltyPointLogModel extends BaseModel {
    protected $table = 'loyalty_point_logs';
    protected $allowedFields = ['id','customer_id','reference_type','reference_id','points','balance_after','notes'];
    protected $useTimestamps = false;
    protected $createdField = 'created_at';
}
