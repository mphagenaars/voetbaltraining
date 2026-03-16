<?php
declare(strict_types=1);

class Encryption {
    public static function encrypt(string $plaintext): string {
        $key = self::getKey();

        try {
            $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            $ciphertext = sodium_crypto_secretbox($plaintext, $nonce, $key);
            return base64_encode($nonce . $ciphertext);
        } finally {
            sodium_memzero($key);
        }
    }

    public static function decrypt(string $encoded): string {
        $key = self::getKey();

        try {
            $decoded = base64_decode($encoded, true);
            if ($decoded === false) {
                throw new RuntimeException('Decryptie mislukt: ongeldige data.');
            }

            if (strlen($decoded) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
                throw new RuntimeException('Decryptie mislukt: onvolledige data.');
            }

            $nonce = substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            $ciphertext = substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

            $plaintext = sodium_crypto_secretbox_open($ciphertext, $nonce, $key);
            if ($plaintext === false) {
                throw new RuntimeException('Decryptie mislukt: ongeldige sleutel of data.');
            }

            return $plaintext;
        } finally {
            sodium_memzero($key);
        }
    }

    private static function getKey(): string {
        if (!function_exists('sodium_crypto_secretbox')) {
            throw new RuntimeException('Encryptie niet beschikbaar: sodium-extensie ontbreekt.');
        }

        $raw = (string) Config::get('encryption_key', '');
        if ($raw === '') {
            throw new RuntimeException('Encryptiesleutel ontbreekt.');
        }

        if (str_starts_with($raw, 'base64:')) {
            $raw = substr($raw, 7);
        }

        $key = base64_decode($raw, true);
        if ($key === false || strlen($key) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            throw new RuntimeException('Encryptiesleutel ontbreekt of is ongeldig.');
        }

        return $key;
    }
}
