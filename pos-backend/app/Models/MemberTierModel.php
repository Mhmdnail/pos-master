<?php
namespace App\Models;
use CodeIgniter\Model;
class MemberTierModel extends Model {
    protected $table = 'member_tiers';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $allowedFields = ['name','min_points','discount_pct','point_multiplier'];
    protected $useTimestamps = false;
}
