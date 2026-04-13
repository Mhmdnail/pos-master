<?php
namespace App\Models;
class BundleModel extends BaseModel {
    protected $table = 'bundles';
    protected $allowedFields = ['id','code','product_id','type','bundle_price','allow_further_discount','active','valid_from','valid_until'];
    public function withProduct(): static {
        return $this->select('bundles.*, products.name as product_name, products.image_url')
                    ->join('products','products.id = bundles.product_id','left');
    }
    public function getActive(string $outletId): array {
        return $this->select('bundles.*, products.name as product_name')
                    ->join('products','products.id = bundles.product_id')
                    ->join('categories','categories.id = products.category_id')
                    ->where('categories.outlet_id',$outletId)
                    ->where('bundles.active',1)
                    ->groupStart()->where('bundles.valid_from IS NULL')->orWhere('bundles.valid_from <=',date('Y-m-d'))->groupEnd()
                    ->groupStart()->where('bundles.valid_until IS NULL')->orWhere('bundles.valid_until >=',date('Y-m-d'))->groupEnd()
                    ->findAll();
    }
}
