<?php
namespace App\Models;
class UserModel extends BaseModel {
    protected $table = 'users';
    protected $allowedFields = ['id','code','outlet_id','role_id','name','username','password_hash','pin','active','last_login_at'];
    protected $hidden = ['password_hash','pin'];
}
