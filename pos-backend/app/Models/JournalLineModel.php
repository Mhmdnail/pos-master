<?php
namespace App\Models;
class JournalLineModel extends BaseModel {
    protected $table = 'journal_lines';
    protected $allowedFields = ['id','entry_id','account_id','debit','credit','description'];
    protected $useTimestamps = false;
    protected $createdField = 'posted_at';
}
