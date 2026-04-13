<?php
namespace App\Models;
class ReceivableModel extends BaseModel {
    protected $table = 'receivables';
    protected $allowedFields = ['id','outlet_id','customer_id','description','invoice_date','due_date','amount','received_amount','status','notes'];
}
