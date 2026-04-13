<?php
namespace App\Models;
class AccountModel extends BaseModel {
    protected $table = 'accounts';
    protected $allowedFields = ['id','code_display','outlet_id','code','name','type','normal_balance','parent_id','is_system','active'];
    protected $updatedField = '';
    public function getByType(string $outletId, string $type): array {
        return $this->where('outlet_id',$outletId)->where('type',$type)->where('active',1)->findAll();
    }
    public function findByCode(string $outletId, string $code): ?array {
        return $this->where('outlet_id',$outletId)->where('code',$code)->first();
    }
}
