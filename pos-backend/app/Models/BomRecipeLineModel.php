<?php
namespace App\Models;
class BomRecipeLineModel extends BaseModel {
    protected $table = 'bom_recipe_lines';
    protected $allowedFields = ['id','recipe_id','material_id','qty_required','notes'];
    protected $useTimestamps = false;
    public function getByRecipe(string $recipeId): array {
        return $this->select('bom_recipe_lines.*, raw_materials.name as material_name, raw_materials.unit, raw_materials.stock_qty, raw_materials.cost_per_unit')
                    ->join('raw_materials','raw_materials.id = bom_recipe_lines.material_id')
                    ->where('recipe_id',$recipeId)->findAll();
    }
}
