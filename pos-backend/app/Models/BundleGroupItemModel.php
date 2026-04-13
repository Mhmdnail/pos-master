<?php
namespace App\Models;
class BundleGroupItemModel extends BaseModel {
    protected $table = 'bundle_group_items';
    protected $allowedFields = ['id','group_id','product_id','upgrade_fee','is_default','sort_order'];
    protected $useTimestamps = false;
    public function getByGroup(string $groupId): array {
        return $this->select('bundle_group_items.*, products.name as product_name, products.base_price')
                    ->join('products','products.id = bundle_group_items.product_id')
                    ->where('group_id',$groupId)->orderBy('sort_order','ASC')->findAll();
    }
}
