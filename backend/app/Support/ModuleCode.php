<?php

namespace App\Support;

final class ModuleCode
{
    public const RESERVATION = 'SALE';
    public const FRONTDESK = 'FO';
    public const HOUSEKEEPING = 'HK';

    public static function normalize(?string $value, string $default = self::RESERVATION): string
    {
        return match (strtolower(trim((string) $value))) {
            'sale', 'reservation' => self::RESERVATION,
            'fo', 'frontdesk', 'reception' => self::FRONTDESK,
            'hk', 'housekeeping' => self::HOUSEKEEPING,
            default => $default,
        };
    }

    public static function allowed(): array
    {
        return [self::RESERVATION, self::FRONTDESK, self::HOUSEKEEPING];
    }
}
