<?php
namespace App\Models;
class RawMaterialModel extends BaseModel {
    protected $table = 'raw_materials';
    protected $allowedFields = ['id','code','outlet_id','name','unit','stock_qty','min_stock','cost_per_unit','expired_at','active'];
    public function getLowStock(string $outletId): array {
        return $this->where('outlet_id',$outletId)->where('active',1)->whereRaw('stock_qty <= min_stock')->findAll();
    }
    public function deductStock(string $materialId, float $qty): bool {
        return \Config\Database::connect()->query(
            'UPDATE raw_materials SET stock_qty = stock_qty - ?, updated_at = NOW() WHERE id = ? AND stock_qty >= ?',
            [$qty, $materialId, $qty]
        );
    }
}
