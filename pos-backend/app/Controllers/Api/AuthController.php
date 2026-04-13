<?php

namespace App\Controllers\Api;

use App\Libraries\JwtLibrary;
use App\Models\UserModel;

class AuthController extends BaseApiController
{
    protected UserModel $userModel;
    protected JwtLibrary $jwt;

    public function __construct()
    {
        helper('uuid');
        $this->userModel = new UserModel();
        $this->jwt       = new JwtLibrary();
    }

    // ----------------------------------------------------------------
    // POST /api/v1/auth/login
    // Body: { username, password }
    // ----------------------------------------------------------------
    public function login()
    {
        $rules = [
            'username' => 'required|min_length[3]',
            'password' => 'required|min_length[6]',
        ];

        if (! $this->validate($rules)) {
            return $this->error('Validasi gagal', 422, $this->validator->getErrors());
        }

        $username = $this->request->getJSON(true)['username'] ?? $this->request->getPost('username');
        $password = $this->request->getJSON(true)['password'] ?? $this->request->getPost('password');

        $user = $this->userModel
            ->select('users.*, roles.name as role_name, roles.permissions')
            ->join('roles', 'roles.id = users.role_id')
            ->where('users.username', $username)
            ->where('users.active', 1)
            ->first();

        if (! $user || ! password_verify($password, $user['password_hash'])) {
            return $this->error('Username atau password salah', 401);
        }

        // Update last login
        $this->userModel->update($user['id'], ['last_login_at' => date('Y-m-d H:i:s')]);

        $permissions = json_decode($user['permissions'], true) ?? [];

        $token = $this->jwt->encode([
            'sub'         => $user['id'],
            'name'        => $user['name'],
            'username'    => $user['username'],
            'outlet_id'   => $user['outlet_id'],
            'role'        => $user['role_name'],
            'permissions' => $permissions,
        ]);

        return $this->success([
            'token'       => $token,
            'token_type'  => 'Bearer',
            'expires_in'  => (int) env('jwt.expire', 86400),
            'user'        => [
                'id'        => $user['id'],
                'name'      => $user['name'],
                'username'  => $user['username'],
                'role'      => $user['role_name'],
                'outlet_id' => $user['outlet_id'],
            ],
        ], 'Login berhasil');
    }

    // ----------------------------------------------------------------
    // POST /api/v1/auth/logout
    // ----------------------------------------------------------------
    public function logout()
    {
        // Stateless JWT — client cukup hapus token di frontend
        // Jika ingin blacklist token, simpan ke tabel sessions
        return $this->success(null, 'Logout berhasil');
    }

    // ----------------------------------------------------------------
    // GET /api/v1/auth/me — data user yang sedang login
    // ----------------------------------------------------------------
    public function me()
    {
        $userId = $this->currentUserId();

        $user = $this->userModel
            ->select('users.id, users.name, users.username, users.outlet_id, users.last_login_at, roles.name as role')
            ->join('roles', 'roles.id = users.role_id')
            ->find($userId);

        if (! $user) {
            return $this->notFound('User tidak ditemukan');
        }

        return $this->success($user);
    }

    // ----------------------------------------------------------------
    // POST /api/v1/auth/refresh
    // ----------------------------------------------------------------
    public function refresh()
    {
        // Implementasi sederhana: client kirim token lama yang masih valid
        // Server keluarkan token baru dengan expire diperpanjang
        $authHeader = $this->request->getHeaderLine('Authorization');

        if (empty($authHeader) || ! str_starts_with($authHeader, 'Bearer ')) {
            return $this->error('Token tidak ditemukan', 401);
        }

        try {
            $token   = substr($authHeader, 7);
            $payload = $this->jwt->decode($token);

            $newToken = $this->jwt->encode([
                'sub'         => $payload->sub,
                'name'        => $payload->name,
                'username'    => $payload->username,
                'outlet_id'   => $payload->outlet_id,
                'role'        => $payload->role,
                'permissions' => $payload->permissions,
            ]);

            return $this->success([
                'token'      => $newToken,
                'token_type' => 'Bearer',
                'expires_in' => (int) env('jwt.expire', 86400),
            ], 'Token diperbarui');
        } catch (\Exception $e) {
            return $this->error('Token tidak valid', 401);
        }
    }
}
