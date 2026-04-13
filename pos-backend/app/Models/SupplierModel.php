<?php
namespace App\Models;
class SupplierModel extends BaseModel {
    protected $table = 'suppliers';
    protected $allowedFields = ['id','code','outlet_id','name','contact_person','phone','email','address','payment_terms','active'];
    protected $updatedField = '';
}
