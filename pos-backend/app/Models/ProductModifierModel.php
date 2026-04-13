<?php
namespace App\Models;
class ProductModifierModel extends BaseModel {
    protected $table = 'product_modifiers';
    protected $allowedFields = ['id','product_id','name','type','required','sort_order'];
    protected $useTimestamps = false;
}
