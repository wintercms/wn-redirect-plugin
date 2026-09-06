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

        // The wildcard prefix is this plugin's own notation rather than URL grammar, so it is set
        // aside before parsing and put back afterwards.
        $wildcard = '';

        if (strpos($host, '*.') === 0) {
            $wildcard = '*.';
            $host = substr($host, 2);
        }

        // parse_url() only reports a host when the string has an authority to parse: given a bare
        // `example.com` it reports a path instead. Supplying the protocol-relative marker gives it
        // an authority, after which it handles userinfo, ports and IPv6 literals for us.
        if (preg_match('~^(?:[a-z][a-z0-9+.\-]*:)?//~i', $host) !== 1) {
            $host = '//' . $host;
        }

        $parsed = parse_url($host);
        $host = is_array($parsed) ? ($parsed['host'] ?? '') : '';

        // Hosts are case insensitive and the root label is implicit.
        $host = rtrim(strtolower($host), '.');

        return $host === '' ? null : $wildcard . $host;
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
}
