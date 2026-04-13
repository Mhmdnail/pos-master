<?php

namespace App\Controllers\Api;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\ResponseInterface;

class BaseApiController extends Controller
{
    // ----------------------------------------------------------------
    // Response helpers — konsisten di semua endpoint
    // ----------------------------------------------------------------

    protected function success(mixed $data = null, string $message = 'Berhasil', int $code = 200): ResponseInterface
    {
        return $this->response
            ->setStatusCode($code)
            ->setJSON([
                'status'  => true,
                'message' => $message,
                'data'    => $data,
            ]);
    }

    protected function created(mixed $data = null, string $message = 'Data berhasil dibuat'): ResponseInterface
    {
        return $this->success($data, $message, 201);
    }

    protected function error(string $message = 'Terjadi kesalahan', int $code = 400, mixed $errors = null): ResponseInterface
    {
        return $this->response
            ->setStatusCode($code)
            ->setJSON([
                'status'  => false,
                'message' => $message,
                'errors'  => $errors,
                'data'    => null,
            ]);
    }

    protected function notFound(string $message = 'Data tidak ditemukan'): ResponseInterface
    {
        return $this->error($message, 404);
    }

    protected function unauthorized(string $message = 'Tidak memiliki akses'): ResponseInterface
    {
        return $this->error($message, 403);
    }

    protected function serverError(string $message = 'Internal server error'): ResponseInterface
    {
        return $this->error($message, 500);
    }

    // ----------------------------------------------------------------
    // Ambil user yang sedang login dari JWT payload
    // ----------------------------------------------------------------
    protected function currentUser(): ?object
    {
        return $this->request->user ?? null;
    }

    protected function currentUserId(): ?string
    {
        return $this->currentUser()?->sub ?? null;
    }

    protected function currentOutletId(): ?string
    {
        return $this->currentUser()?->outlet_id ?? null;
    }

    // ----------------------------------------------------------------
    // Cek permission user
    // ----------------------------------------------------------------
    protected function can(string $permission): bool
    {
        $user = $this->currentUser();
        if (! $user) return false;

        $permissions = $user->permissions ?? [];

        // Owner punya akses semua
        if (in_array('*', $permissions, true)) return true;

        // Cek exact match atau wildcard (misal order.* mencakup order.create)
        foreach ($permissions as $perm) {
            if ($perm === $permission) return true;
            if (str_ends_with($perm, '.*')) {
                $prefix = str_replace('.*', '', $perm);
                if (str_starts_with($permission, $prefix . '.')) return true;
            }
        }

        return false;
    }

    // ----------------------------------------------------------------
    // Pagination helper
    // ----------------------------------------------------------------
    protected function paginate(array $data, int $total, int $page, int $perPage): array
    {
        return [
            'items'       => $data,
            'pagination'  => [
                'total'        => $total,
                'per_page'     => $perPage,
                'current_page' => $page,
                'last_page'    => (int) ceil($total / $perPage),
            ],
        ];
    }
}
