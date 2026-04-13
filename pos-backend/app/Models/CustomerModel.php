<?php
namespace App\Models;
class CustomerModel extends BaseModel {
    protected $table = 'customers';
    protected $allowedFields = ['id','code','outlet_id','name','phone','email','member_number','tier_id','total_points','total_spent','active'];
    protected $createdField = 'joined_at';
    public function withTier(): static {
        return $this->select('customers.*, member_tiers.name as tier_name, member_tiers.discount_pct, member_tiers.point_multiplier')
                    ->join('member_tiers','member_tiers.id = customers.tier_id','left');
    }
    public function findByPhone(string $phone, string $outletId): ?array {
        return $this->where('outlet_id',$outletId)->where('phone',$phone)->first();
    }
    public function recalculateTier(string $customerId): void {
        $customer = $this->find($customerId);
        $tier = (new MemberTierModel())->where('min_points <=',$customer['total_points'])->orderBy('min_points','DESC')->first();
        if ($tier) $this->update($customerId,['tier_id'=>$tier['id']]);
    }
}
