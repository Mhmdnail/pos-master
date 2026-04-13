<?php

namespace App\Libraries;

class JwtLibrary
{
    private string $secret;
    private int $expire;
    private string $algo = 'HS256';

    public function __construct()
    {
        $this->secret = env('jwt.secret', 'default_secret_ganti_ini');
        $this->expire = (int) env('jwt.expire', 86400);
    }

    // ----------------------------------------------------------------
    // Encode — buat token baru
    // ----------------------------------------------------------------
    public function encode(array $payload): string
    {
        $header = $this->base64url(json_encode([
            'typ' => 'JWT',
            'alg' => $this->algo,
        ]));

        $payload['iat'] = time();
        $payload['exp'] = time() + $this->expire;

        $payloadEncoded = $this->base64url(json_encode($payload));

        $signature = $this->base64url(
            hash_hmac('sha256', "$header.$payloadEncoded", $this->secret, true)
        );

        return "$header.$payloadEncoded.$signature";
    }

    // ----------------------------------------------------------------
    // Decode — verifikasi dan baca token
    // ----------------------------------------------------------------
    public function decode(string $token): object
    {
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            throw new \Exception('Format token tidak valid');
        }

        [$header, $payload, $signature] = $parts;

        // Verifikasi signature
        $expectedSig = $this->base64url(
            hash_hmac('sha256', "$header.$payload", $this->secret, true)
        );

        if (! hash_equals($expectedSig, $signature)) {
            throw new \Exception('Signature token tidak valid');
        }

        $data = json_decode(base64_decode(strtr($payload, '-_', '+/')));

        // Cek expiry
        if (isset($data->exp) && $data->exp < time()) {
            throw new \Exception('Token sudah kadaluarsa');
        }

        return $data;
    }

    // ----------------------------------------------------------------
    // Buat refresh token (random string, simpan di DB jika perlu)
    // ----------------------------------------------------------------
    public function generateRefreshToken(): string
    {
        return bin2hex(random_bytes(40));
    }

    private function base64url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
