<?php
namespace App\Models;
class DiscountRuleModel extends BaseModel {
    protected $table = 'discount_rules';
    protected $allowedFields = ['id','discount_id','rule_type','rule_value'];
    protected $useTimestamps = false;
    public function getByDiscount(string $discountId): array {
        return $this->where('discount_id',$discountId)->findAll();
    }
}
