<?php

declare(strict_types=1);

namespace Winter\Redirect\Tests\Cases;

use Winter\Redirect\Classes\Contracts\CacheManagerInterface;
use Winter\Redirect\Classes\Exceptions\NoMatchForRequest;
use Winter\Redirect\Classes\RedirectManager;
use Winter\Redirect\Classes\RedirectRule;
use Winter\Redirect\Models\Redirect;
use Winter\Redirect\Models\Settings;
use Winter\Storm\Database\ModelException;
use Winter\Redirect\ServiceProvider;

class RedirectHostTest extends \Winter\Redirect\Tests\RedirectPluginTestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $this->app->register(ServiceProvider::class);

        Settings::instance()->setSettingsValue('relative_paths_enabled', true);
    }

    private function makeManager(array $attributes = []): RedirectManager
    {
        $redirect = new Redirect(array_merge([
            'match_type' => Redirect::TYPE_EXACT,
            'target_type' => Redirect::TARGET_TYPE_PATH_URL,
            'from_url' => '/old-page',
            'from_scheme' => Redirect::SCHEME_AUTO,
            'to_url' => '/new-page',
            'to_scheme' => Redirect::SCHEME_AUTO,
            'requirements' => null,
            'status_code' => 301,
        ], $attributes));

        self::assertTrue($redirect->save());

        /** @var RedirectManager $manager */
        $manager = RedirectManager::createWithRule(RedirectRule::createWithModel($redirect));

        return $manager;
    }

    public function testRuleWithoutHostMatchesEveryHost(): void
    {
        $manager = $this->makeManager();

        self::assertEquals('/new-page', $manager->getLocation(
            $manager->match('/old-page', Redirect::SCHEME_HTTPS, 'example.com')
        ));

        self::assertEquals('/new-page', $manager->getLocation(
            $manager->match('/old-page', Redirect::SCHEME_HTTPS, 'other-site.com')
        ));

        // No host supplied at all is how every caller behaved before this feature existed.
        self::assertEquals('/new-page', $manager->getLocation(
            $manager->match('/old-page', Redirect::SCHEME_HTTPS)
        ));
    }

    public function testRuleWithHostOnlyMatchesThatHost(): void
    {
        $manager = $this->makeManager(['from_host' => 'example.com']);

        self::assertEquals('/new-page', $manager->getLocation(
            $manager->match('/old-page', Redirect::SCHEME_HTTPS, 'example.com')
        ));

        $this->expectException(NoMatchForRequest::class);

        $manager->match('/old-page', Redirect::SCHEME_HTTPS, 'other-site.com');
    }

    public function testRuleWithHostDoesNotMatchWhenNoHostIsGiven(): void
    {
        $manager = $this->makeManager(['from_host' => 'example.com']);

        $this->expectException(NoMatchForRequest::class);

        $manager->match('/old-page', Redirect::SCHEME_HTTPS);
    }

    public function testHostMatchIgnoresCaseAndPort(): void
    {
        $manager = $this->makeManager(['from_host' => 'Example.COM']);

        self::assertEquals('/new-page', $manager->getLocation(
            $manager->match('/old-page', Redirect::SCHEME_HTTPS, 'EXAMPLE.com:8080')
        ));
    }

    public function testWildcardHostMatchesSubdomainsButNotTheDomain(): void
    {
        $manager = $this->makeManager(['from_host' => '*.example.com']);

        self::assertEquals('/new-page', $manager->getLocation(
            $manager->match('/old-page', Redirect::SCHEME_HTTPS, 'www.example.com')
        ));

        $this->expectException(NoMatchForRequest::class);

        $manager->match('/old-page', Redirect::SCHEME_HTTPS, 'example.com');
    }

    public function testHostAppliesToRegexRulesToo(): void
    {
        $manager = $this->makeManager([
            'match_type' => Redirect::TYPE_REGEX,
            'from_url' => '#^/news/([^/]{1,})/?$#',
            'from_host' => 'example.com',
            'to_url' => '/articles/{1}',
        ]);

        self::assertEquals('/articles/my-post', $manager->getLocation(
            $manager->match('/news/my-post', Redirect::SCHEME_HTTPS, 'example.com')
        ));

        $this->expectException(NoMatchForRequest::class);

        $manager->match('/news/my-post', Redirect::SCHEME_HTTPS, 'other-site.com');
    }

    public function testAbsoluteSourceUrlIsSplitIntoHostAndPath(): void
    {
        $redirect = new Redirect([
            'match_type' => Redirect::TYPE_EXACT,
            'target_type' => Redirect::TARGET_TYPE_PATH_URL,
            'from_url' => 'https://Example.com:8080/old-page?a=b',
            'from_scheme' => Redirect::SCHEME_AUTO,
            'to_url' => '/new-page',
            'to_scheme' => Redirect::SCHEME_AUTO,
            'requirements' => null,
            'status_code' => 301,
        ]);

        self::assertTrue($redirect->save());

        self::assertEquals('example.com', $redirect->getAttribute('from_host'));
        self::assertEquals('/old-page?a=b', $redirect->getAttribute('from_url'));

        // The scheme of the pasted URL is deliberately not adopted; from_scheme owns that.
        self::assertEquals(Redirect::SCHEME_AUTO, $redirect->getAttribute('from_scheme'));
    }

    public function testRegexSourceIsNeverSplit(): void
    {
        $pattern = '#^https://example\.com/news$#';

        $redirect = new Redirect([
            'match_type' => Redirect::TYPE_REGEX,
            'target_type' => Redirect::TARGET_TYPE_PATH_URL,
            'from_url' => $pattern,
            'from_scheme' => Redirect::SCHEME_AUTO,
            'to_url' => '/new-page',
            'to_scheme' => Redirect::SCHEME_AUTO,
            'requirements' => null,
            'status_code' => 301,
        ]);

        self::assertTrue($redirect->save());

        self::assertEquals($pattern, $redirect->getAttribute('from_url'));
        self::assertNull($redirect->getAttribute('from_host'));
    }

    public function testSourceHostIsNormalizedOnSave(): void
    {
        $redirect = $this->makeManagerModel(['from_host' => ' HTTPS://Example.COM:8080/ignored ']);

        self::assertEquals('example.com', $redirect->getAttribute('from_host'));
    }

    public function testAnInvalidSourceHostIsRejected(): void
    {
        $redirect = new Redirect([
            'match_type' => Redirect::TYPE_EXACT,
            'target_type' => Redirect::TARGET_TYPE_PATH_URL,
            'from_url' => '/old-page',
            'from_host' => '-not a host-',
            'from_scheme' => Redirect::SCHEME_AUTO,
            'to_url' => '/new-page',
            'to_scheme' => Redirect::SCHEME_AUTO,
            'requirements' => null,
            'status_code' => 301,
        ]);

        try {
            $redirect->validate();
            self::fail('An invalid source host should not pass validation.');
        } catch (ModelException $exception) {
            self::assertTrue($redirect->errors()->has('from_host'));
        }
    }

    public function testAnAbsoluteSourceUrlWithAFragmentStillMatches(): void
    {
        $redirect = new Redirect([
            'match_type' => Redirect::TYPE_EXACT,
            'target_type' => Redirect::TARGET_TYPE_PATH_URL,
            'from_url' => 'https://example.com/old-page#section',
            'from_scheme' => Redirect::SCHEME_AUTO,
            'to_url' => '/new-page',
            'to_scheme' => Redirect::SCHEME_AUTO,
            'requirements' => null,
            'status_code' => 301,
        ]);

        self::assertTrue($redirect->save());

        // A request never carries the fragment, so storing it would make the rule unmatchable.
        self::assertEquals('/old-page', $redirect->getAttribute('from_url'));

        $manager = RedirectManager::createWithRule(RedirectRule::createWithModel($redirect));

        self::assertEquals('/new-page', $manager->getLocation(
            $manager->match('/old-page', Redirect::SCHEME_HTTPS, 'example.com')
        ));
    }

    public function testCacheKeyVariesByHost(): void
    {
        /** @var CacheManagerInterface $cacheManager */
        $cacheManager = resolve(CacheManagerInterface::class);

        $withoutHost = $cacheManager->cacheKey('/old-page', 'https');
        $exampleCom = $cacheManager->cacheKey('/old-page', 'https', 'example.com');
        $otherSite = $cacheManager->cacheKey('/old-page', 'https', 'other-site.com');

        // Without this, the first host to request a path would poison the match for every other.
        self::assertNotEquals($exampleCom, $otherSite);
        self::assertNotEquals($withoutHost, $exampleCom);

        self::assertEquals($exampleCom, $cacheManager->cacheKey('/old-page', 'https', 'example.com'));
    }

    private function makeManagerModel(array $attributes): Redirect
    {
        $redirect = new Redirect(array_merge([
            'match_type' => Redirect::TYPE_EXACT,
            'target_type' => Redirect::TARGET_TYPE_PATH_URL,
            'from_url' => '/old-page',
            'from_scheme' => Redirect::SCHEME_AUTO,
            'to_url' => '/new-page',
            'to_scheme' => Redirect::SCHEME_AUTO,
            'requirements' => null,
            'status_code' => 301,
        ], $attributes));

        self::assertTrue($redirect->save());

        return $redirect;
    }
}
