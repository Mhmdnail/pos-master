<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;
use CodeIgniter\Filters\CSRF;
use CodeIgniter\Filters\DebugToolbar;
use CodeIgniter\Filters\Honeypot;
use CodeIgniter\Filters\InvalidChars;
use CodeIgniter\Filters\SecureHeaders;

class Filters extends BaseConfig
{
    public array $aliases = [
        'csrf'          => CSRF::class,
        'toolbar'       => DebugToolbar::class,
        'honeypot'      => Honeypot::class,
        'invalidchars'  => InvalidChars::class,
        'secureheaders' => SecureHeaders::class,
        'auth'          => \App\Filters\AuthFilter::class,
        'cors'          => \App\Filters\CorsFilter::class,
    ];

    public array $globals = [
        'before' => [
            'cors',          // CORS selalu jalan duluan
            // 'csrf' -- dinonaktifkan untuk REST API
        ],
        'after' => [
            'toolbar' => ['except' => ['api/*']],
        ],
    ];

    public array $methods = [];

    public array $filters = [];
}
