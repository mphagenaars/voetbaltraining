<?php
declare(strict_types=1);

class Config {
    private static ?array $config = null;

    public static function get(string $key, mixed $default = null): mixed {
        self::load();
        return self::$config[$key] ?? $default;
    }

    public static function hasEncryptionKey(): bool {
        $value = self::get('encryption_key', null);
        return is_string($value) && trim($value) !== '';
    }

    public static function reload(): void {
        self::$config = null;
        self::load();
    }

    private static function load(): void {
        if (self::$config !== null) {
            return;
        }

        $configPath = __DIR__ . '/../data/config.php';
        if (!file_exists($configPath)) {
            self::$config = [];
            return;
        }

        $loaded = require $configPath;
        self::$config = is_array($loaded) ? $loaded : [];
    }
}
