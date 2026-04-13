<?php
namespace App\Models;
class PaymentModel extends BaseModel {
    protected $table = 'payments';
    protected $allowedFields = ['id','order_id','method','provider','reference_no','amount','fee','status','paid_at','metadata'];
    protected $useTimestamps = false;
    protected $createdField = 'created_at';
}
