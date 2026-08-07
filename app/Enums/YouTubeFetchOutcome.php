<?php

declare(strict_types=1);

namespace App\Enums;

enum YouTubeFetchOutcome: string
{
    case Success = 'success';
    case ConfigurationFailure = 'configuration_failure';
    case InvalidUrl = 'invalid_url';
    case UnsafeRedirect = 'unsafe_redirect';
    case ConsentRequired = 'consent_required';
    case SignInRequired = 'sign_in_required';
    case RateLimited = 'rate_limited';
    case TransientHttpFailure = 'transient_http_failure';
    case PermanentHttpFailure = 'permanent_http_failure';
    case TransportFailure = 'transport_failure';
    case ChannelIdMissing = 'channel_id_missing';
    case MalformedFeed = 'malformed_feed';
    case ApiQuotaExhausted = 'api_quota_exhausted';
    case UploadsPlaylistMissing = 'uploads_playlist_missing';
    case MalformedApiResponse = 'malformed_api_response';
    case PaginationLimitExceeded = 'pagination_limit_exceeded';
    case RequestBudgetExhausted = 'request_budget_exhausted';
    case SharedCircuitOpen = 'shared_circuit_open';
    case StaleProfile = 'stale_profile';
    case Disabled = 'disabled';
    case UnexpectedFailure = 'unexpected_failure';
    case Retrying = 'retrying';

    public function retryable(): bool
    {
        return in_array($this, [self::RateLimited, self::TransientHttpFailure, self::TransportFailure], true);
    }
}
