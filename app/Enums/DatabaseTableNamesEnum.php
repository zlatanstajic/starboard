<?php

declare(strict_types=1);

namespace App\Enums;

enum DatabaseTableNamesEnum: string
{
    case personal_access_tokens = 'personal_access_tokens';
    case users = 'users';
    case network_sources = 'network_sources';
    case network_profiles = 'network_profiles';
    case network_tags = 'network_tags';
    case network_profile_network_tag = 'network_profile_network_tag';
}
