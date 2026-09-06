<?php

declare(strict_types=1);

namespace Winter\Redirect\Classes\Util;

/**
 * Helpers for the source host of a redirect rule.
 *
 * A rule host is stored bare and lower case (`example.com`), optionally with a leading `*.`
 * wildcard (`*.example.com`) which matches any subdomain but not the domain itself.
 */
final class Host
{
    /**
     * Reduce user input to a bare, comparable host.
     *
     * Accepts anything from `example.com` to `https://user@Example.COM:8080/path?q` and returns
     * `example.com`. Returns null when no host remains, so "no host" is always a single value.
     */
    public static function normalize(?string $host): ?string
    {
        if ($host === null) {
            return null;
        }

        $host = trim($host);

        if ($host === '') {
            return null;
        }

        // Drop a leading scheme (`https://`) or protocol-relative marker (`//`).
        $host = (string) preg_replace('~^(?:[a-z][a-z0-9+.\-]*:)?//~i', '', $host);

        // Keep the authority only; a path, query or fragment is not part of the host.
        $host = (string) preg_replace('~[/?#].*$~', '', $host);

        // Userinfo (`user:pass@`) is not part of the host either.
        $atPosition = strrpos($host, '@');

        if ($atPosition !== false) {
            $host = substr($host, $atPosition + 1);
        }

        $host = self::removePort($host);

        // Hosts are case insensitive and the root label is implicit.
        $host = rtrim(strtolower($host), '.');

        return $host === '' ? null : $host;
    }

    /**
     * Split a URL into its host and everything from the path onwards.
     *
     * Only absolute (`https://example.com/path`) and protocol-relative (`//example.com/path`)
     * URLs carry a host. Anything else — a plain path, or a regular expression used as a source
     * pattern — is returned untouched with a null host, so callers can split unconditionally.
     *
     * @return array{0: string|null, 1: string} The host, and the remaining path.
     */
    public static function splitUrl(string $url): array
    {
        if (preg_match('~^(?:[a-z][a-z0-9+.\-]*:)?//([^/?#]*)(.*)$~i', $url, $matches) !== 1) {
            return [null, $url];
        }

        $host = self::normalize($matches[1]);

        if ($host === null) {
            return [null, $url];
        }

        $path = $matches[2];

        if ($path === '' || $path[0] !== '/') {
            $path = '/' . $path;
        }

        return [$host, $path];
    }

    /**
     * Does a request host satisfy a rule host?
     *
     * An empty rule host places no restriction, which is what every rule created before this
     * feature existed has, so those keep matching every host.
     */
    public static function matches(?string $ruleHost, ?string $requestHost): bool
    {
        $ruleHost = self::normalize($ruleHost);

        if ($ruleHost === null) {
            return true;
        }

        $requestHost = self::normalize($requestHost);

        if ($requestHost === null) {
            return false;
        }

        if (strpos($ruleHost, '*.') === 0) {
            // `*.example.com` covers `www.example.com` but deliberately not `example.com`,
            // matching the wildcard convention of nginx and Apache server names.
            $suffix = substr($ruleHost, 1);

            return strlen($requestHost) > strlen($suffix)
                && substr($requestHost, -strlen($suffix)) === $suffix;
        }

        return $ruleHost === $requestHost;
    }

    /**
     * Strip a trailing `:port`, leaving bracketed IPv6 literals intact.
     */
    private static function removePort(string $host): string
    {
        if (strpos($host, '[') === 0) {
            $bracket = strpos($host, ']');

            return $bracket === false ? $host : substr($host, 0, $bracket + 1);
        }

        $colon = strrpos($host, ':');

        return $colon === false ? $host : substr($host, 0, $colon);
    }
}
