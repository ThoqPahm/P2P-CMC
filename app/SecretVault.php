<?php

declare(strict_types=1);

final class SecretVault
{
    public static function encrypt(string $plainText): string
    {
        if ($plainText === '') {
            return '';
        }
        if (!function_exists('openssl_encrypt')) {
            throw new RuntimeException('OpenSSL extension is required to protect API keys.');
        }
        $iv = random_bytes(12);
        $tag = '';
        $cipherText = openssl_encrypt($plainText, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag);
        if (!is_string($cipherText)) {
            throw new RuntimeException('Could not encrypt API key.');
        }
        return base64_encode(json_encode([
            'v' => 1,
            'iv' => base64_encode($iv),
            'tag' => base64_encode($tag),
            'data' => base64_encode($cipherText),
        ], JSON_THROW_ON_ERROR));
    }

    public static function decrypt(string $payload): string
    {
        if ($payload === '') {
            return '';
        }
        if (!function_exists('openssl_decrypt')) {
            throw new RuntimeException('OpenSSL extension is required to read API keys.');
        }
        $decoded = base64_decode($payload, true);
        $data = is_string($decoded) ? json_decode($decoded, true) : null;
        if (!is_array($data)) {
            throw new RuntimeException('Encrypted API key is invalid.');
        }
        $iv = base64_decode((string) ($data['iv'] ?? ''), true);
        $tag = base64_decode((string) ($data['tag'] ?? ''), true);
        $cipherText = base64_decode((string) ($data['data'] ?? ''), true);
        if (!is_string($iv) || !is_string($tag) || !is_string($cipherText)) {
            throw new RuntimeException('Encrypted API key is incomplete.');
        }
        $plainText = openssl_decrypt($cipherText, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag);
        if (!is_string($plainText)) {
            throw new RuntimeException('Could not decrypt API key. Check APP_SECRET.');
        }
        return $plainText;
    }

    private static function key(): string
    {
        $configured = trim((string) getenv('APP_SECRET'));
        if ($configured !== '') {
            return hash('sha256', $configured, true);
        }

        $path = dirname(__DIR__) . '/data/.app_secret';
        if (!is_file($path)) {
            $secret = bin2hex(random_bytes(32));
            if (file_put_contents($path, $secret, LOCK_EX) === false) {
                throw new RuntimeException('Could not create the local encryption secret.');
            }
            @chmod($path, 0600);
        }
        $secret = trim((string) file_get_contents($path));
        if ($secret === '') {
            throw new RuntimeException('The local encryption secret is empty.');
        }
        return hash('sha256', $secret, true);
    }
}
