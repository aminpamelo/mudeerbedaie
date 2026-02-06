<?php

declare(strict_types=1);

namespace App\Services\MergeTag\DataProviders;

use App\Models\FunnelSession;
use App\Services\MergeTag\DataProviderInterface;

class SessionDataProvider implements DataProviderInterface
{
    public function getValue(string $field, array $context): ?string
    {
        // Get session from context
        $session = $context['funnel_session'] ?? $context['session'] ?? null;

        // Try to get UTM and tracking data from session or direct context
        return match ($field) {
            'utm_source' => $this->getUtmValue($session, $context, 'utm_source'),
            'utm_medium' => $this->getUtmValue($session, $context, 'utm_medium'),
            'utm_campaign' => $this->getUtmValue($session, $context, 'utm_campaign'),
            'utm_content' => $this->getUtmValue($session, $context, 'utm_content'),
            'utm_term' => $this->getUtmValue($session, $context, 'utm_term'),
            'device' => $this->getDeviceType($session, $context),
            'browser' => $this->getBrowser($session, $context),
            'country' => $this->getCountry($session, $context),
            'referrer' => $this->getReferrer($session, $context),
            'ip_address' => $this->getIpAddress($session, $context),
            'landing_page' => $this->getLandingPage($session, $context),
            default => null,
        };
    }

    protected function getUtmValue(?FunnelSession $session, array $context, string $key): ?string
    {
        // Check direct context first
        if (isset($context[$key])) {
            return $context[$key];
        }

        // Check session model
        if ($session instanceof FunnelSession) {
            // Direct property
            if (isset($session->{$key})) {
                return $session->{$key};
            }

            // From utm_data JSON field
            $utmData = $session->utm_data ?? $session->tracking_data ?? null;
            if ($utmData) {
                if (is_string($utmData)) {
                    $utmData = json_decode($utmData, true);
                }
                if (is_array($utmData) && isset($utmData[$key])) {
                    return $utmData[$key];
                }
            }
        }

        // Check context utm_data
        $contextUtm = $context['utm_data'] ?? $context['tracking_data'] ?? null;
        if (is_array($contextUtm) && isset($contextUtm[$key])) {
            return $contextUtm[$key];
        }

        return null;
    }

    protected function getDeviceType(?FunnelSession $session, array $context): ?string
    {
        // Check direct context
        if (isset($context['device']) || isset($context['device_type'])) {
            return $context['device'] ?? $context['device_type'];
        }

        // From session
        if ($session instanceof FunnelSession) {
            $device = $session->device ?? $session->device_type ?? null;
            if ($device) {
                return $this->normalizeDeviceType($device);
            }

            // From user_agent parsing or stored metadata
            $metadata = $this->getSessionMetadata($session);
            if (isset($metadata['device'])) {
                return $this->normalizeDeviceType($metadata['device']);
            }
        }

        return null;
    }

    protected function normalizeDeviceType(string $device): string
    {
        $device = strtolower($device);

        return match (true) {
            str_contains($device, 'mobile') || str_contains($device, 'phone') => 'mobile',
            str_contains($device, 'tablet') || str_contains($device, 'ipad') => 'tablet',
            str_contains($device, 'desktop') || str_contains($device, 'computer') => 'desktop',
            default => $device,
        };
    }

    protected function getBrowser(?FunnelSession $session, array $context): ?string
    {
        // Check direct context
        if (isset($context['browser'])) {
            return $context['browser'];
        }

        // From session
        if ($session instanceof FunnelSession) {
            if (isset($session->browser)) {
                return $session->browser;
            }

            $metadata = $this->getSessionMetadata($session);
            if (isset($metadata['browser'])) {
                return $metadata['browser'];
            }
        }

        return null;
    }

    protected function getCountry(?FunnelSession $session, array $context): ?string
    {
        // Check direct context
        if (isset($context['country'])) {
            return $context['country'];
        }

        // From session
        if ($session instanceof FunnelSession) {
            if (isset($session->country)) {
                return strtoupper($session->country);
            }

            $metadata = $this->getSessionMetadata($session);
            if (isset($metadata['country'])) {
                return strtoupper($metadata['country']);
            }

            // From geo data
            $geoData = $session->geo_data ?? null;
            if ($geoData) {
                if (is_string($geoData)) {
                    $geoData = json_decode($geoData, true);
                }
                if (is_array($geoData) && isset($geoData['country_code'])) {
                    return strtoupper($geoData['country_code']);
                }
            }
        }

        return null;
    }

    protected function getReferrer(?FunnelSession $session, array $context): ?string
    {
        // Check direct context
        if (isset($context['referrer']) || isset($context['referer'])) {
            return $context['referrer'] ?? $context['referer'];
        }

        // From session
        if ($session instanceof FunnelSession) {
            $referrer = $session->referrer ?? $session->referer ?? null;
            if ($referrer) {
                // Extract domain from full URL
                return $this->extractDomain($referrer);
            }
        }

        return null;
    }

    protected function extractDomain(string $url): string
    {
        $parsed = parse_url($url);

        return $parsed['host'] ?? $url;
    }

    protected function getIpAddress(?FunnelSession $session, array $context): ?string
    {
        // Check direct context
        if (isset($context['ip_address']) || isset($context['ip'])) {
            return $context['ip_address'] ?? $context['ip'];
        }

        // From session
        if ($session instanceof FunnelSession) {
            return $session->ip_address ?? $session->ip ?? null;
        }

        return null;
    }

    protected function getLandingPage(?FunnelSession $session, array $context): ?string
    {
        // Check direct context
        if (isset($context['landing_page'])) {
            return $context['landing_page'];
        }

        // From session
        if ($session instanceof FunnelSession) {
            return $session->landing_page ?? $session->entry_url ?? null;
        }

        return null;
    }

    protected function getSessionMetadata(?FunnelSession $session): array
    {
        if (! $session) {
            return [];
        }

        $metadata = $session->metadata ?? $session->session_data ?? null;

        if (is_string($metadata)) {
            return json_decode($metadata, true) ?? [];
        }

        return is_array($metadata) ? $metadata : [];
    }
}
