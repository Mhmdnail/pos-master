<?php
namespace App\Controllers\Api;

use App\Models\UserModel;

class UserController extends BaseApiController
{
    protected UserModel $model;

    public function __construct()
    {
        helper(['uuid', 'code']);
        $this->model = new UserModel();
    }

    // ----------------------------------------------------------------
    // GET /api/v1/users
    // ----------------------------------------------------------------
    public function index()
    {
        if (! $this->can('order.*')) return $this->unauthorized('Tidak memiliki akses');

        $outletId = $this->currentOutletId();
        $page     = (int)($this->request->getGet('page')     ?? 1);
        $perPage  = (int)($this->request->getGet('per_page') ?? 50);

        // Pakai query builder langsung — hindari double JOIN dari model
        $db = \Config\Database::connect();

        $total = $db->table('users u')
                    ->join('roles r', 'r.id = u.role_id')
                    ->where('u.outlet_id', $outletId)
                    ->countAllResults();

        $data = $db->table('users u')
                   ->select('u.id, u.code, u.name, u.username, u.active,
                             u.last_login_at, u.created_at,
                             r.id as role_id, r.name as role_name')
                   ->join('roles r', 'r.id = u.role_id')
                   ->where('u.outlet_id', $outletId)
                   ->orderBy('u.created_at', 'ASC')
                   ->limit($perPage, ($page - 1) * $perPage)
                   ->get()
                   ->getResultArray();

        return $this->success($this->paginate($data, $total, $page, $perPage));
    }

    // ----------------------------------------------------------------
    // GET /api/v1/users/{id}
    // ----------------------------------------------------------------
    public function show($id)
    {
        $db   = \Config\Database::connect();
        $user = $db->table('users u')
                   ->select('u.id, u.code, u.name, u.username, u.active,
                             u.last_login_at, u.created_at,
                             r.id as role_id, r.name as role_name')
                   ->join('roles r', 'r.id = u.role_id')
                   ->where('u.outlet_id', $this->currentOutletId())
                   ->where('u.id', $id)
                   ->get()
                   ->getRowArray();

        if (! $user) return $this->notFound('User tidak ditemukan');
        return $this->success($user);
    }

    // ----------------------------------------------------------------
    // POST /api/v1/users — tambah user baru
    // ----------------------------------------------------------------
    public function create()
    {
        if (! $this->can('order.*')) return $this->unauthorized();

        $json  = $this->request->getJSON(true) ?? [];
        $rules = [
            'name'     => 'required|max_length[100]',
            'username' => 'required|min_length[3]|max_length[50]',
            'password' => 'required|min_length[6]',
            'role_id'  => 'required|integer',
        ];
        if (! $this->validate($rules)) {
            return $this->error('Validasi gagal', 422, $this->validator->getErrors());
        }

        $outletId = $this->currentOutletId();

        // Cek username unik
        $existing = $this->model->where('username', $json['username'])->first();
        if ($existing) {
            return $this->error('Username sudah digunakan', 422);
        }

        $id   = generate_uuid();
        $code = generate_code('users', $outletId);

        $data = [
            'id'            => $id,
            'code'          => $code,
            'outlet_id'     => $outletId,
            'role_id'       => (int)$json['role_id'],
            'name'          => $json['name'],
            'username'      => $json['username'],
            'password_hash' => password_hash($json['password'], PASSWORD_BCRYPT),
            'pin'           => $json['pin'] ?? null,
            'active'        => 1,
        ];

        $this->model->insert($data);

        // Return tanpa password
        unset($data['password_hash'], $data['pin']);
        return $this->created($data, 'User berhasil ditambahkan');
    }

    // ----------------------------------------------------------------
    // PUT /api/v1/users/{id} — update data user
    // ----------------------------------------------------------------
    public function update($id)
    {
        if (! $this->can('order.*')) return $this->unauthorized();

        $user = $this->model->where('outlet_id', $this->currentOutletId())->find($id);
        if (! $user) return $this->notFound('User tidak ditemukan');

        $json    = $this->request->getJSON(true) ?? [];
        $allowed = ['name', 'role_id', 'active'];
        $data    = array_intersect_key($json, array_flip($allowed));

        // Update password jika disertakan
        if (! empty($json['password'])) {
            if (strlen($json['password']) < 6) {
                return $this->error('Password minimal 6 karakter', 422);
            }
            $data['password_hash'] = password_hash($json['password'], PASSWORD_BCRYPT);
        }

        // Update PIN jika disertakan (boleh null untuk hapus PIN)
        if (array_key_exists('pin', $json)) {
            $data['pin'] = $json['pin'] ?: null;
        }

        // Tidak bisa nonaktifkan diri sendiri
        if (isset($data['active']) && (int)$data['active'] === 0 && $id === $this->currentUserId()) {
            return $this->error('Tidak bisa menonaktifkan akun sendiri', 422);
        }

        $this->model->update($id, $data);
        return $this->success(null, 'User berhasil diupdate');
    }

    // ----------------------------------------------------------------
    // PATCH /api/v1/users/{id}/toggle — aktif / nonaktif
    // ----------------------------------------------------------------
    public function toggle($id)
    {
        if (! $this->can('order.*')) return $this->unauthorized();

        $user = $this->model->where('outlet_id', $this->currentOutletId())->find($id);
        if (! $user) return $this->notFound('User tidak ditemukan');

        if ($id === $this->currentUserId()) {
            return $this->error('Tidak bisa menonaktifkan akun sendiri', 422);
        }

        $newStatus = (int)$user['active'] === 1 ? 0 : 1;
        $this->model->update($id, ['active' => $newStatus]);

        $label = $newStatus ? 'diaktifkan' : 'dinonaktifkan';
        return $this->success(['active' => $newStatus], "User berhasil {$label}");
    }

    // ----------------------------------------------------------------
    // GET /api/v1/roles — list semua role
    // ----------------------------------------------------------------
    public function roles()
    {
        $db    = \Config\Database::connect();
        $roles = $db->query('SELECT id, name, permissions FROM roles ORDER BY id ASC')
                    ->getResultArray();
        return $this->success($roles);
    }
}
