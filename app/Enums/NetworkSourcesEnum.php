<?php

declare(strict_types=1);

namespace App\Enums;

enum NetworkSourcesEnum: string
{
    case Instagram = 'instagram';
    case TikTok = 'tiktok';
    case Facebook = 'facebook';
    case X = 'x';
    case YouTube = 'youtube';
    case Rumble = 'rumble';

    /**
     * URL template for the network source.
     */
    public function urlTemplate(): string
    {
        return match ($this) {
            self::Instagram => 'https://instagram.com/{username}',
            self::TikTok => 'https://tiktok.com/@{username}',
            self::Facebook => 'https://facebook.com/{username}',
            self::X => 'https://x.com/{username}',
            self::YouTube => 'https://youtube.com/@{username}/videos',
            self::Rumble => 'https://rumble.com/c/{username}/videos',
        };
    }
}
