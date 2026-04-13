<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Cors extends BaseConfig
{
    /**
     * Origin yang diizinkan — sesuaikan dengan port Vue dev server
     * Development : http://localhost:5173 (Vite default)
     * Production  : ganti dengan domain asli
     */
    public array $allowedOrigins = [
        'http://localhost:5173',
        'http://localhost:3000',
        'http://127.0.0.1:5173',
    ];

    public array $allowedOriginsPatterns = [];

    public bool $supportsCredentials = true;

    public array $allowedHeaders = [
        'Accept',
        'Authorization',
        'Content-Type',
        'X-Requested-With',
        'X-CSRF-TOKEN',
    ];

    public array $exposedHeaders = [];

    public array $allowedMethods = [
        'GET',
        'POST',
        'PUT',
        'PATCH',
        'DELETE',
        'OPTIONS',
    ];

    /**
     * Preflight cache dalam detik (1 jam)
     */
    public int $maxAge = 3600;
}
