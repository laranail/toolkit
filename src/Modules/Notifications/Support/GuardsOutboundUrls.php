<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Modules\Notifications\Support;

/**
 * Reusable SSRF guard for channels that make outbound HTTP requests
 * (Webhook, Slack, Discord, Push, Apple Business Messages).
 *
 * Rejects destinations that are not plain http(s) URLs, and blocks obvious
 * internal / metadata targets so an attacker-controlled or misconfigured URL
 * cannot be used to reach loopback, link-local, or RFC1918 hosts. The check is
 * intentionally conservative and operates on the URL alone (no DNS resolution)
 * so it stays deterministic and side-effect free.
 */
trait GuardsOutboundUrls
{
    /**
     * Determine whether the given URL is safe to call.
     */
    protected function isOutboundUrlAllowed(string $url): bool
    {
        $url = trim($url);

        if ($url === '') {
            return false;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        // Only plain web schemes — explicitly excludes file://, gopher://, etc.
        if (!in_array($scheme, ['http', 'https'], true)) {
            return false;
        }

        $host = parse_url($url, PHP_URL_HOST);

        if (!is_string($host) || $host === '') {
            return false;
        }

        return !$this->isBlockedHost($host);
    }

    /**
     * Determine whether a host points at an internal / disallowed target.
     */
    private function isBlockedHost(string $host): bool
    {
        $host = strtolower(trim($host, '[]'));

        // Named loopback / metadata aliases.
        if (in_array($host, ['localhost', 'ip6-localhost', 'metadata.google.internal'], true)) {
            return true;
        }

        if (str_ends_with($host, '.localhost')) {
            return true;
        }

        // IPv6 literals.
        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
            return $this->isBlockedIpv6($host);
        }

        // IPv4 literals.
        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            return $this->isBlockedIpv4($host);
        }

        // A non-IP hostname that is not a known internal alias is allowed; we do
        // not resolve DNS here to keep the guard deterministic.
        return false;
    }

    private function isBlockedIpv6(string $ip): bool
    {
        $normalized = strtolower($ip);

        // ::1 loopback and unspecified ::.
        if (in_array($normalized, ['::1', '::'], true)) {
            return true;
        }

        // Unique-local (fc00::/7) and link-local (fe80::/10).
        if (
            str_starts_with($normalized, 'fc') || str_starts_with($normalized, 'fd') || str_starts_with($normalized, 'fe8')
            || str_starts_with($normalized, 'fe9') || str_starts_with($normalized, 'fea') || str_starts_with($normalized, 'feb')
        ) {
            return true;
        }

        // IPv4-mapped (::ffff:a.b.c.d) — defer to the IPv4 check.
        if (str_contains($normalized, '.')) {
            $tail = substr($normalized, (int) strrpos($normalized, ':') + 1);

            if (filter_var($tail, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
                return $this->isBlockedIpv4($tail);
            }
        }

        return false;
    }

    private function isBlockedIpv4(string $ip): bool
    {
        // Anything outside the public range is rejected: this covers private
        // (10/8, 172.16/12, 192.168/16), loopback (127/8), link-local
        // (169.254/16), and reserved blocks in one shot.
        $isPublic = filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        );

        return $isPublic === false;
    }
}
