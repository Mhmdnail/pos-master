<?php
namespace App\Models;
class OutletModel extends BaseModel {
    protected $table = 'outlets';
    protected $allowedFields = ['id','code','name','address','phone','email','active'];
}
