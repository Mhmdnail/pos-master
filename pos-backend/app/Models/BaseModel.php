<?php

namespace App\Models;

use CodeIgniter\Model;

// ============================================================
// BaseModel — shared logic untuk semua model
// ============================================================
class BaseModel extends Model
{
    protected $primaryKey    = 'id';
    protected $useAutoIncrement = false;  // UUID, bukan auto increment
    protected $returnType    = 'array';
    protected $useSoftDeletes = false;
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected function initialize()
    {
        helper('uuid');
    }

    /**
     * Override insert — auto-inject UUID jika id kosong
     */
    public function insert($data = null, bool $returnID = true)
    {
        if (is_array($data) && empty($data['id'])) {
            $data['id'] = generate_uuid();
        }
        return parent::insert($data, $returnID);
    }

    /**
     * Scope: filter by outlet_id dari JWT
     */
    public function forOutlet(string $outletId): static
    {
        return $this->where($this->table . '.outlet_id', $outletId);
    }
}
