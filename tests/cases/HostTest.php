<?php

declare(strict_types=1);

namespace Winter\Redirect\Tests\Cases;

use Winter\Redirect\Classes\Util\Host;

class HostTest extends \Winter\Redirect\Tests\RedirectPluginTestCase
{
    /**
     * @dataProvider normalizeProvider
     */
    public function testNormalize(?string $input, ?string $expected): void
    {
        self::assertSame($expected, Host::normalize($input));
    }

    public function normalizeProvider(): array
    {
        return [
            'already bare' => ['example.com', 'example.com'],
            'upper case' => ['Example.COM', 'example.com'],
            'surrounding space' => ['  example.com  ', 'example.com'],
            'https scheme' => ['https://example.com', 'example.com'],
            'http scheme' => ['http://example.com', 'example.com'],
            'protocol relative' => ['//example.com', 'example.com'],
            'with path' => ['https://example.com/some/path', 'example.com'],
            'with query' => ['https://example.com?foo=bar', 'example.com'],
            'with fragment' => ['https://example.com#anchor', 'example.com'],
            'with port' => ['example.com:8080', 'example.com'],
            'with scheme and port' => ['https://example.com:8080/path', 'example.com'],
            'with userinfo' => ['https://user:pass@example.com/path', 'example.com'],
            'trailing root dot' => ['example.com.', 'example.com'],
            'wildcard kept' => ['*.example.com', '*.example.com'],
            'ipv6 literal' => ['[::1]:8080', '[::1]'],
            'null' => [null, null],
            'empty' => ['', null],
            'whitespace only' => ['   ', null],
            'scheme only' => ['https://', null],
        ];
    }

    /**
     * @dataProvider splitUrlProvider
     */
    public function testSplitUrl(string $input, ?string $expectedHost, string $expectedPath): void
    {
        self::assertSame([$expectedHost, $expectedPath], Host::splitUrl($input));
    }

    public function splitUrlProvider(): array
    {
        return [
            'absolute url' => ['https://example.com/old-page', 'example.com', '/old-page'],
            'absolute url with query' => [
                'https://example.com/old?a=b',
                'example.com',
                '/old?a=b',
            ],
            'absolute host only' => ['https://example.com', 'example.com', '/'],
            'absolute host and slash' => ['https://example.com/', 'example.com', '/'],
            'query directly on host' => ['https://example.com?a=b', 'example.com', '/?a=b'],
            'protocol relative' => ['//example.com/old-page', 'example.com', '/old-page'],
            'port is dropped from the host' => [
                'http://example.com:8080/old-page',
                'example.com',
                '/old-page',
            ],
            'plain path is untouched' => ['/old-page', null, '/old-page'],
            'path that looks like a host is untouched' => ['example.com/old-page', null, 'example.com/old-page'],
            'regular expression is untouched' => ['#^/news/([^/]+)/?$#', null, '#^/news/([^/]+)/?$#'],
            'regex containing a scheme is untouched' => [
                '#^https://example\.com/news$#',
                null,
                '#^https://example\.com/news$#',
            ],
        ];
    }

    /**
     * @dataProvider matchesProvider
     */
    public function testMatches(?string $ruleHost, ?string $requestHost, bool $expected): void
    {
        self::assertSame($expected, Host::matches($ruleHost, $requestHost));
    }

    public function matchesProvider(): array
    {
        return [
            'no rule host matches anything' => [null, 'example.com', true],
            'empty rule host matches anything' => ['', 'example.com', true],
            'no rule host matches a null request host' => [null, null, true],
            'exact match' => ['example.com', 'example.com', true],
            'exact match is case insensitive' => ['example.com', 'EXAMPLE.COM', true],
            'different host' => ['example.com', 'other-site.com', false],
            'subdomain is not the domain' => ['example.com', 'www.example.com', false],
            'port on the request is ignored' => ['example.com', 'example.com:8080', true],
            'a restricted rule needs a host' => ['example.com', null, false],
            'wildcard matches a subdomain' => ['*.example.com', 'www.example.com', true],
            'wildcard matches a deep subdomain' => ['*.example.com', 'a.b.example.com', true],
            'wildcard does not match the domain itself' => ['*.example.com', 'example.com', false],
            'wildcard does not match a suffix lookalike' => ['*.example.com', 'evil-example.com', false],
            'wildcard does not match another domain' => ['*.example.com', 'www.other.com', false],
        ];
    }
}
