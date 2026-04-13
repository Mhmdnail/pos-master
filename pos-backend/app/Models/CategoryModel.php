<?php
namespace App\Models;
class CategoryModel extends BaseModel {
    protected $table = 'categories';
    protected $allowedFields = ['id','code','outlet_id','name','sort_order','active'];
    protected $updatedField = '';
}
