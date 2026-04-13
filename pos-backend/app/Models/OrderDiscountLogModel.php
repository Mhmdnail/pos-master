<?php
namespace App\Models;
class OrderDiscountLogModel extends BaseModel {
    protected $table = 'order_discount_log';
    protected $allowedFields = ['id','order_id','discount_id','discount_name','discount_code','applied_to','order_item_id','amount','approved_by'];
    protected $useTimestamps = false;
    protected $createdField = 'created_at';
}
