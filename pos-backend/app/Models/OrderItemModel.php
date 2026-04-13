<?php
namespace App\Models;
class OrderItemModel extends BaseModel {
    protected $table = 'order_items';
    protected $allowedFields = ['id','order_id','product_id','bundle_id','parent_item_id','name_snapshot','qty','unit_price','unit_hpp','discount_amount','subtotal','modifiers','notes'];
    protected $useTimestamps = false;
    public function getByOrder(string $orderId): array {
        return $this->select('order_items.*, products.image_url')
                    ->join('products','products.id = order_items.product_id','left')
                    ->where('order_id',$orderId)->findAll();
    }
}
