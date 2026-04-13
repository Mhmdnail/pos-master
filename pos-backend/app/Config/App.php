<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class App extends BaseConfig
{
    // ----------------------------------------------------------------
    // Base URL
    // ----------------------------------------------------------------
    public string $baseURL = 'http://localhost:8080/';
    public string $indexPage = '';
    public string $uriProtocol = 'REQUEST_URI';

    // ----------------------------------------------------------------
    // Locale
    // ----------------------------------------------------------------
    public string $defaultLocale = 'id';
    public bool $negotiateLocale = false;
    public array $supportedLocales = ['id', 'en'];
    public string $appTimezone = 'Asia/Jakarta';
    public string $charset = 'UTF-8';
    public bool $forceGlobalSecureRequests = false;
    public array $proxyIPs = [];

    // ----------------------------------------------------------------
    // Session
    // ----------------------------------------------------------------
    public string $sessionDriver            = 'CodeIgniter\Session\Handlers\FileHandler';
    public string $sessionCookieName        = 'ci_session';
    public int    $sessionExpiration        = 7200;
    public string $sessionSavePath          = WRITEPATH . 'session';
    public bool   $sessionMatchIP           = false;
    public int    $sessionTimeToUpdate      = 300;
    public bool   $sessionRegenerateDestroy = false;

    // ----------------------------------------------------------------
    // Cookie
    // ----------------------------------------------------------------
    public string  $cookiePrefix   = '';
    public string  $cookieDomain   = '';
    public string  $cookiePath     = '/';
    public bool    $cookieSecure   = false;
    public bool    $cookieHTTPOnly = false;
    public ?string $cookieSameSite = 'Lax';

    // ----------------------------------------------------------------
    // CSRF — dinonaktifkan untuk REST API
    // ----------------------------------------------------------------
    public string $CSRFTokenName    = 'csrf_test_name';
    public string $CSRFHeaderName   = 'X-CSRF-TOKEN';
    public string $CSRFCookieName   = 'csrf_cookie_name';
    public int    $CSRFExpire       = 7200;
    public bool   $CSRFRegenerate   = true;
    public bool   $CSRFExcludeURIs  = false;
    public string $CSRFSameSite     = 'Lax';

    // ----------------------------------------------------------------
    // Content Security Policy — WAJIB ADA di CI4 v4.7
    // ----------------------------------------------------------------
    public bool $CSPEnabled = false;

    // ----------------------------------------------------------------
    // Allowed Hostnames
    // ----------------------------------------------------------------
    public array $allowedHostnames = [];
}
