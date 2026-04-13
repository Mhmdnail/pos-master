<?php
namespace App\Models;
class KasTransactionModel extends BaseModel {
    protected $table = 'kas_transactions';
    protected $allowedFields = ['id','outlet_id','kas_type','type','amount','reference_type','reference_id','description','balance_after','created_by'];
    protected $useTimestamps = false;
    protected $createdField = 'created_at';
    public function getBalance(string $outletId, string $kasType = 'besar'): float {
        $last = $this->where('outlet_id',$outletId)->where('kas_type',$kasType)->orderBy('created_at','DESC')->first();
        return (float)($last['balance_after'] ?? 0);
    }
}
