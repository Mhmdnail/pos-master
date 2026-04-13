<?php
namespace App\Models;
class DiscountTargetModel extends BaseModel {
    protected $table = 'discount_targets';
    protected $allowedFields = ['id','discount_id','target_type','target_id'];
    protected $useTimestamps = false;
}
