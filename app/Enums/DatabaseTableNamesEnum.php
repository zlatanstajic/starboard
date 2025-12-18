<?php

namespace App\Enums;

/**
 * @package App\Enums
 */
enum DatabaseTableNamesEnum: string
{
    case personal_access_tokens = 'personal_access_tokens';
    case users = 'users';
    case network_sources = 'network_sources';
    case network_profiles = 'network_profiles';
}
