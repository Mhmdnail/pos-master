<?php
namespace App\Models;
class ModifierOptionModel extends BaseModel {
    protected $table = 'modifier_options';
    protected $allowedFields = ['id','modifier_id','name','price_delta','is_default','sort_order'];
    protected $useTimestamps = false;
}
