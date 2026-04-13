<?php
namespace App\Models;
class BomRecipeModel extends BaseModel {
    protected $table = 'bom_recipes';
    protected $allowedFields = ['id','product_id','name','active'];
    protected $updatedField = '';
    public function getActiveRecipe(string $productId): ?array {
        return $this->where('product_id',$productId)->where('active',1)->first();
    }
}
