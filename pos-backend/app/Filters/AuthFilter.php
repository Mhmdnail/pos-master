<?php

namespace App\Filters;

use App\Libraries\JwtLibrary;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class AuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $authHeader = $request->getHeaderLine('Authorization');

        if (empty($authHeader) || ! str_starts_with($authHeader, 'Bearer ')) {
            return response()
                ->setStatusCode(401)
                ->setJSON([
                    'status'  => false,
                    'message' => 'Token tidak ditemukan. Silakan login terlebih dahulu.',
                    'data'    => null,
                ]);
        }

        $token = substr($authHeader, 7);

        try {
            $jwt     = new JwtLibrary();
            $payload = $jwt->decode($token);

            // Simpan payload ke request untuk dipakai controller
            $request->user = $payload;
        } catch (\Exception $e) {
            return response()
                ->setStatusCode(401)
                ->setJSON([
                    'status'  => false,
                    'message' => 'Token tidak valid atau sudah kadaluarsa. Silakan login ulang.',
                    'data'    => null,
                ]);
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
