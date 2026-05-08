<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

final class TokenService
{
    public function __construct(
        private readonly string $secret,
        private readonly int $ttlSeconds = 3600,
        private readonly string $issuer = 'product-import-api',
        private readonly string $audience = 'product-import-frontend'
    ) {
    }

    public function issueAccessToken(string $subject): array
    {
        $now = time();
        $payload = [
            'sub' => $subject,
            'iss' => $this->issuer,
            'aud' => $this->audience,
            'iat' => $now,
            'nbf' => $now,
            'exp' => $now + $this->ttlSeconds,
            'jti' => bin2hex(random_bytes(16)),
            'type' => 'access',
        ];
        $token = $this->encode($payload);

        return [
            'token' => $token,
            'expiresIn' => $this->ttlSeconds,
        ];
    }

    public function issueRefreshToken(string $subject, int $ttlSeconds): array
    {
        $now = time();
        $payload = [
            'sub' => $subject,
            'iss' => $this->issuer,
            'aud' => $this->audience,
            'iat' => $now,
            'nbf' => $now,
            'exp' => $now + $ttlSeconds,
            'jti' => bin2hex(random_bytes(16)),
            'type' => 'refresh',
        ];
        $token = $this->encode($payload);

        return [
            'token' => $token,
            'expiresAt' => $payload['exp'],
        ];
    }

    public function verifyAccessToken(string $token): bool
    {
        $payload = $this->decodeAndValidate($token);
        if ($payload === null) {
            return false;
        }

        return ($payload['type'] ?? '') === 'access';
    }

    public function parseRefreshToken(string $token): ?array
    {
        $payload = $this->decodeAndValidate($token);
        if ($payload === null || ($payload['type'] ?? '') !== 'refresh') {
            return null;
        }

        return $payload;
    }

    public function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    private function decodeAndValidate(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        [$encodedHeader, $encodedPayload, $encodedSignature] = $parts;
        $expectedSignature = $this->base64UrlEncode(
            hash_hmac('sha256', $encodedHeader . '.' . $encodedPayload, $this->secret, true)
        );
        if (!hash_equals($expectedSignature, $encodedSignature)) {
            return null;
        }

        $payloadRaw = $this->base64UrlDecode($encodedPayload);
        if ($payloadRaw === false) {
            return null;
        }
        $payload = json_decode($payloadRaw, true);
        if (!is_array($payload)) {
            return null;
        }

        $now = time();
        $iss = (string) ($payload['iss'] ?? '');
        $aud = (string) ($payload['aud'] ?? '');
        $sub = (string) ($payload['sub'] ?? '');
        $jti = (string) ($payload['jti'] ?? '');
        $exp = (int) ($payload['exp'] ?? 0);
        $nbf = (int) ($payload['nbf'] ?? 0);
        $iat = (int) ($payload['iat'] ?? 0);
        if ($iss !== $this->issuer || $aud !== $this->audience || $sub === '' || $jti === '') {
            return null;
        }
        if ($iat <= 0 || $nbf <= 0 || $exp <= 0) {
            return null;
        }
        if ($nbf > $now || $exp <= $now) {
            return null;
        }
        return $payload;
    }

    private function encode(array $payload): string
    {
        $header = [
            'alg' => 'HS256',
            'typ' => 'JWT',
        ];
        $encodedHeader = $this->base64UrlEncode((string) json_encode($header, JSON_THROW_ON_ERROR));
        $encodedPayload = $this->base64UrlEncode((string) json_encode($payload, JSON_THROW_ON_ERROR));
        $signature = hash_hmac('sha256', $encodedHeader . '.' . $encodedPayload, $this->secret, true);
        $encodedSignature = $this->base64UrlEncode($signature);

        return $encodedHeader . '.' . $encodedPayload . '.' . $encodedSignature;
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): string|false
    {
        $padding = strlen($value) % 4;
        if ($padding > 0) {
            $value .= str_repeat('=', 4 - $padding);
        }

        return base64_decode(strtr($value, '-_', '+/'), true);
    }
}
