<?php
namespace App\Models;
class JournalEntryModel extends BaseModel {
    protected $table = 'journal_entries';
    protected $allowedFields = ['id','outlet_id','entry_number','reference_type','reference_id','description','total_debit','total_credit','created_by'];
    protected $useTimestamps = false;
    protected $createdField = 'posted_at';
    public function getTodayCounter(string $outletId): int {
        return $this->where('outlet_id',$outletId)->where('DATE(posted_at)',date('Y-m-d'))->countAllResults();
    }
}
