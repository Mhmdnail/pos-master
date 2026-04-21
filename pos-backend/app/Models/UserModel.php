<?php
namespace App\Models;

class UserModel extends BaseModel
{
    protected $table         = 'users';
    protected $allowedFields = [
        'id', 'code', 'outlet_id', 'role_id',
        'name', 'username', 'password_hash', 'pin',
        'active', 'last_login_at',
    ];
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    // Tidak ada auto-join di sini — join dilakukan manual di controller
    // supaya tidak menyebabkan "Not unique table/alias: roles"
}
