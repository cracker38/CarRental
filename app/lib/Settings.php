<?php
declare(strict_types=1);

final class Settings
{
    private static array $cache = [];
    private static bool $loaded = false;

    public const CATALOG = [
        'app_name' => ['type' => 'string', 'group' => 'Application', 'label' => 'Application name', 'description' => 'Name shown in the interface'],
        'business_name' => ['type' => 'string', 'group' => 'Business', 'label' => 'Business name', 'description' => 'Name shown on invoices and agreements'],
        'business_address' => ['type' => 'string', 'group' => 'Business', 'label' => 'Business address', 'description' => 'Street address of the rental office'],
        'business_phone' => ['type' => 'string', 'group' => 'Business', 'label' => 'Business phone', 'description' => 'Public contact telephone number'],
        'business_email' => ['type' => 'string', 'group' => 'Business', 'label' => 'Business email', 'description' => 'Public contact email address'],
        'business_country' => ['type' => 'string', 'group' => 'Business', 'label' => 'Business country', 'description' => 'Business operating country'],
        'default_currency' => ['type' => 'string', 'group' => 'Regional', 'label' => 'Default currency', 'description' => 'ISO currency code used across the system', 'options' => ['RWF' => 'RWF — Rwandan Franc', 'USD' => 'USD — US Dollar', 'EUR' => 'EUR — Euro', 'KES' => 'KES — Kenyan Shilling']],
        'timezone' => ['type' => 'string', 'group' => 'Regional', 'label' => 'Time zone', 'description' => 'Business timezone for timestamps', 'options' => ['Africa/Kigali' => 'Africa/Kigali', 'Africa/Nairobi' => 'Africa/Nairobi', 'Africa/Lagos' => 'Africa/Lagos', 'UTC' => 'UTC']],
        'reservation_confirmation_required' => ['type' => 'boolean', 'group' => 'Operations', 'label' => 'Reservation confirmation required', 'description' => 'Reservations require staff confirmation before they become active'],
        'default_deposit_amount' => ['type' => 'number', 'group' => 'Operations', 'label' => 'Default security deposit', 'description' => 'Default deposit amount in the business currency'],
        'session_idle_timeout' => ['type' => 'number', 'group' => 'Security', 'label' => 'Session idle timeout (minutes)', 'description' => 'Minutes of inactivity before staff sessions expire'],
    ];

    public static function ensureDefaults(): void
    {
        $defaults = [
            'app_name' => ['CRMS', 'string', 'Application display name'],
            'business_address' => ['Kigali, Rwanda', 'string', 'Street address of the rental office'],
            'business_phone' => ['+250 000 000 000', 'string', 'Public contact telephone number'],
            'business_email' => ['info@carrental.local', 'string', 'Public contact email address'],
            'session_idle_timeout' => ['30', 'number', 'Idle session timeout in minutes'],
        ];
        $sql = 'INSERT IGNORE INTO system_settings (setting_key, setting_value, value_type, description)
                VALUES (:k, :v, :t, :d)';
        $stmt = Database::connection()->prepare($sql);
        foreach ($defaults as $key => $meta) {
            $stmt->execute([':k' => $key, ':v' => $meta[0], ':t' => $meta[1], ':d' => $meta[2]]);
        }
    }

    public static function all(): array
    {
        self::load();
        return self::$cache;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        self::load();
        if (!isset(self::$cache[$key])) {
            return $default;
        }
        return self::cast(self::$cache[$key]['setting_value'], self::$cache[$key]['value_type']);
    }

    public static function update(string $key, mixed $value, ?int $userId): void
    {
        if (!isset(self::CATALOG[$key])) {
            throw new InvalidArgumentException('Unknown setting.');
        }
        $type = self::CATALOG[$key]['type'];
        $stored = self::normalize($value, $type);

        $old = self::get($key);

        $sql = 'INSERT INTO system_settings (setting_key, setting_value, value_type, description, updated_by)
                VALUES (:k, :v, :t, :d, :u)
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value),
                    value_type = VALUES(value_type),
                    description = VALUES(description),
                    updated_by = VALUES(updated_by)';
        Database::connection()->prepare($sql)->execute([
            ':k' => $key,
            ':v' => $stored,
            ':t' => $type,
            ':d' => self::CATALOG[$key]['description'],
            ':u' => $userId,
        ]);

        self::$loaded = false;
        Audit::record($userId, 'settings.update', 'system_settings', $key, 'Updated system setting ' . $key, 'success', ['value' => $old], ['value' => $stored]);
    }

    private static function load(): void
    {
        if (self::$loaded) {
            return;
        }
        $rows = Database::connection()->query('SELECT setting_key, setting_value, value_type, description, is_sensitive, updated_at FROM system_settings')->fetchAll();
        self::$cache = [];
        foreach ($rows as $row) {
            self::$cache[$row['setting_key']] = $row;
        }
        self::$loaded = true;
    }

    private static function cast(?string $value, string $type): mixed
    {
        return match ($type) {
            'number' => $value === null || $value === '' ? 0 : (str_contains((string) $value, '.') ? (float) $value : (int) $value),
            'boolean' => in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true),
            'json' => json_decode((string) $value, true),
            default => $value,
        };
    }

    public static function normalize(mixed $value, string $type): string
    {
        if ($type === 'boolean') {
            $truthy = $value === true || $value === 1 || $value === '1' || $value === 'true' || $value === 'on';
            return $truthy ? 'true' : 'false';
        }
        if ($type === 'number') {
            if (!is_numeric($value)) {
                throw new InvalidArgumentException('Setting must be numeric.');
            }
            return (string) $value;
        }
        $value = trim((string) $value);
        if ($type === 'string' && mb_strlen($value) > 2000) {
            throw new InvalidArgumentException('Setting value is too long.');
        }
        return $value;
    }
}
