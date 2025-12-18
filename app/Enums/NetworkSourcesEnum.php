<?php

namespace App\Enums;

/**
 * @package App\Enums
 */
enum NetworkSourcesEnum: int
{
    case instagram      = 1;
    case tiktok         = 2;
    case facebook       = 3;
    case x              = 4;
    case youtube        = 5;
    case rumble         = 6;

    /**
     * @return array<int, array{username: string, network_source_id: int}>
     */
    public static function toNetworkProfileArray(): array
    {
        return array_map(fn(self $case) => [
            'username' => $case->name,
            'network_source_id' => $case->value,
        ], self::cases());
    }

    /**
     * @return string
     */
    public function urlTemplate(): string
    {
        return match ($this) {
            self::instagram      => 'https://instagram.com/{username}',
            self::tiktok         => 'https://tiktok.com/@{username}',
            self::facebook       => 'https://facebook.com/{username}',
            self::x              => 'https://x.com/{username}',
            self::youtube        => 'https://youtube.com/@{username}/videos',
            self::rumble         => 'https://rumble.com/c/{username}/videos',
        };
    }
}
