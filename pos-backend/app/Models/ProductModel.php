<?php
namespace App\Models;
class ProductModel extends BaseModel {
    protected $table = 'products';
    protected $allowedFields = ['id','code','outlet_id','category_id','name','sku','description','base_price','is_bundle','has_bom','image_url','active'];
    public function withCategory(): static {
        return $this->select('products.*, categories.name as category_name')
                    ->join('categories','categories.id = products.category_id','left');
    }
}
