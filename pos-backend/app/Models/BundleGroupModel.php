<?php
namespace App\Models;
class BundleGroupModel extends BaseModel {
    protected $table = 'bundle_groups';
    protected $allowedFields = ['id','bundle_id','name','min_select','max_select','sort_order'];
    protected $useTimestamps = false;
}
