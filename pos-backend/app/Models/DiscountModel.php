<?php
namespace App\Models;
class DiscountModel extends BaseModel {
    protected $table = 'discounts';
    protected $allowedFields = ['id','code_internal','outlet_id','name','code','type','value','max_cap','is_stackable','priority','usage_limit','usage_count','per_customer_limit','require_member','min_member_tier','active','valid_from','valid_until','created_by'];
    public function getActive(string $outletId): array {
        $now = date('Y-m-d H:i:s');
        return $this->where('outlet_id',$outletId)->where('active',1)
                    ->groupStart()->where('valid_from IS NULL')->orWhere('valid_from <=',$now)->groupEnd()
                    ->groupStart()->where('valid_until IS NULL')->orWhere('valid_until >=',$now)->groupEnd()
                    ->orderBy('priority','DESC')->findAll();
    }
    public function findByCode(string $code, string $outletId): ?array {
        return $this->where('outlet_id',$outletId)->where('code',$code)->where('active',1)->first();
    }
    public function incrementUsage(string $id): void {
        \Config\Database::connect()->query('UPDATE discounts SET usage_count = usage_count + 1 WHERE id = ?',[$id]);
    }
}
