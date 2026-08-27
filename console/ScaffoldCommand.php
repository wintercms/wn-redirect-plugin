<?php

declare(strict_types=1);

namespace Winter\Redirect\Console;

use Backend;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Winter\Redirect\Models\Category;
use Winter\Redirect\Models\Client;
use Winter\Redirect\Models\Redirect;
use Winter\Redirect\Models\RedirectLog;

/**
 * Scaffolds Winter.Redirect demo data for local development and testing.
 *
 * Creates a spread of redirects covering every match type (exact / placeholders
 * / regex), every target type (path-or-url / cms-page / static-page / none), a
 * mix of enabled/disabled and scheduled rows, some assigned to categories, and a
 * few deliberately long URLs — enough rows to paginate the list (20/page).
 *
 * Crucially for a dark-mode audit it also seeds the *hit* data the statistics
 * page charts and dashboard report widgets render from. Those surfaces read the
 * `winter_redirect_clients` table (grouped by day/month/year, split by crawler
 * vs. visitor), so this command generates client hit records spread across the
 * current and previous months — plus matching, deduped `RedirectLog` rows for
 * the per-redirect "Logs" tab and a `hits`/`last_used_at` counter on each
 * redirect (which drives the "Top redirects" list column and report widget).
 *
 * Mirrors the env-guarded, idempotent `scaffold:*` pattern used elsewhere:
 * scaffold redirects are marked with a `[scaffold]` description prefix so
 * `--fresh` can scope its cleanup (their clients/logs cascade), and scaffold
 * categories carry a `Scaffold —` name prefix.
 */
class ScaffoldCommand extends Command
{
    protected $signature = 'scaffold:winter.redirect
        {--fresh : Delete any existing scaffold data before recreating it}';

    protected $description = 'Scaffold Winter.Redirect demo data (varied redirects + hit statistics for the charts) for local development/testing.';

    /**
     * Marker prefix stamped on scaffold-created redirects (in the `description`
     * column) and categories (in the `name` column) so `--fresh` deletion and
     * the idempotency check can be scoped to scaffold data only.
     */
    const MARKER = '[scaffold]';
    const CATEGORY_PREFIX = 'Scaffold —';

    public function handle(): int
    {
        // Never inject demo content into a production install.
        if ($this->getLaravel()->environment('production')) {
            $this->error('scaffold:winter.redirect cannot run in the production environment.');

            return self::FAILURE;
        }

        if ($this->option('fresh')) {
            $this->deleteExisting();
        }

        if (Redirect::where('description', 'like', self::MARKER . '%')->exists()) {
            $this->warn('Winter.Redirect scaffold data already exists. Use --fresh to recreate it.');

            return self::SUCCESS;
        }

        $categories = $this->createCategories();
        $this->info('Created ' . count($categories) . ' categories.');

        $redirects = $this->createRedirects($categories);
        $this->info('Created ' . count($redirects) . ' redirects.');

        [$clientCount, $logCount] = $this->seedHits($redirects);
        $this->info("Seeded {$clientCount} client hit records and {$logCount} redirect log rows.");

        $this->newLine();
        $this->line('Redirects:  ' . Backend::url('winter/redirect/redirects'));
        $this->line('Categories: ' . Backend::url('winter/redirect/categories'));
        $this->line('Statistics: ' . Backend::url('winter/redirect/statistics'));
        $this->line('Dashboard:  ' . Backend::url('backend/dashboard') . ' (add the "Top 10 redirects" + "Create redirect" report widgets)');

        return self::SUCCESS;
    }

    /**
     * Remove previously scaffolded redirects (their clients + logs cascade via
     * FK, but we also delete explicitly in case the DB driver does not) and the
     * scaffold categories.
     */
    protected function deleteExisting(): void
    {
        $redirects = Redirect::where('description', 'like', self::MARKER . '%')->get();

        foreach ($redirects as $redirect) {
            Client::where('redirect_id', $redirect->id)->delete();
            RedirectLog::where('redirect_id', $redirect->id)->delete();
            $redirect->delete();
        }

        $categories = Category::where('name', 'like', self::CATEGORY_PREFIX . '%')->get();
        foreach ($categories as $category) {
            $category->delete();
        }

        if ($redirects->isNotEmpty() || $categories->isNotEmpty()) {
            $this->info("Removed {$redirects->count()} scaffold redirect(s) and {$categories->count()} category(ies).");
        }
    }

    /**
     * @return array<string, Category>
     */
    protected function createCategories(): array
    {
        $marketing = $this->makeCategory('Marketing campaigns');
        $legacy = $this->makeCategory('Legacy site URLs');
        $longName = $this->makeCategory(
            'A deliberately very long category name used to test truncation and wrapping in the '
            . 'categories list, the redirect form relation picker and the redirects list filter dropdown'
        );

        return compact('marketing', 'legacy', 'longName');
    }

    protected function makeCategory(string $name): Category
    {
        $category = new Category();
        $category->name = self::CATEGORY_PREFIX . ' ' . $name;
        $category->save();

        return $category;
    }

    /**
     * Build a varied set of redirects covering every match type, every target
     * type, enabled/disabled/scheduled states, long URLs and category
     * assignments — plus filler to paginate the 20-per-page list.
     *
     * @param array<string, Category> $cats
     * @return Redirect[]
     */
    protected function createRedirects(array $cats): array
    {
        $redirects = [];
        $sort = 1;

        // 1. Exact -> path/url, permanent, enabled, categorised.
        $redirects[] = $this->makeRedirect([
            'match_type' => Redirect::TYPE_EXACT,
            'target_type' => Redirect::TARGET_TYPE_PATH_URL,
            'from_url' => '/old-home',
            'to_url' => '/',
            'status_code' => 301,
            'is_enabled' => true,
            'category_id' => $cats['legacy']->id,
            'sort_order' => $sort++,
            'note' => 'exact -> path, 301, enabled',
        ]);

        // 2. Exact -> external URL, temporary, enabled.
        $redirects[] = $this->makeRedirect([
            'match_type' => Redirect::TYPE_EXACT,
            'target_type' => Redirect::TARGET_TYPE_PATH_URL,
            'from_url' => '/promo',
            'to_url' => 'https://example.com/landing/summer-sale',
            'status_code' => 302,
            'is_enabled' => true,
            'category_id' => $cats['marketing']->id,
            'sort_order' => $sort++,
            'note' => 'exact -> external, 302, enabled',
        ]);

        // 3. Placeholders match -> path, permanent, enabled, with requirements.
        $redirects[] = $this->makeRedirect([
            'match_type' => Redirect::TYPE_PLACEHOLDERS,
            'target_type' => Redirect::TARGET_TYPE_PATH_URL,
            'from_url' => '/blog/{category}/{slug}',
            'to_url' => '/articles/{slug}',
            'status_code' => 301,
            'is_enabled' => true,
            'category_id' => $cats['legacy']->id,
            'sort_order' => $sort++,
            'requirements' => [
                ['placeholder' => 'category', 'requirement' => '[a-z0-9\-]+', 'replacement' => null],
                ['placeholder' => 'slug', 'requirement' => '[a-z0-9\-]+', 'replacement' => null],
            ],
            'note' => 'placeholders -> path, 301, enabled, with requirements',
        ]);

        // 4. Regex match -> path, permanent, disabled.
        $redirects[] = $this->makeRedirect([
            'match_type' => Redirect::TYPE_REGEX,
            'target_type' => Redirect::TARGET_TYPE_PATH_URL,
            'from_url' => '/^\/product\/(\d+)\/.*$/',
            'to_url' => '/shop/item/$1',
            'status_code' => 301,
            'is_enabled' => false,
            'category_id' => $cats['legacy']->id,
            'sort_order' => $sort++,
            'note' => 'regex -> path, 301, DISABLED',
        ]);

        // 5. Exact -> CMS page, temporary, enabled.
        $redirects[] = $this->makeRedirect([
            'match_type' => Redirect::TYPE_EXACT,
            'target_type' => Redirect::TARGET_TYPE_CMS_PAGE,
            'from_url' => '/contact-us',
            'cms_page' => 'contact',
            'status_code' => 302,
            'is_enabled' => true,
            'sort_order' => $sort++,
            'note' => 'exact -> cms_page, 302, enabled',
        ]);

        // 6. Exact -> static page (Winter.Pages), see-other, enabled.
        $redirects[] = $this->makeRedirect([
            'match_type' => Redirect::TYPE_EXACT,
            'target_type' => Redirect::TARGET_TYPE_STATIC_PAGE,
            'from_url' => '/about-old',
            'static_page' => 'about',
            'status_code' => 303,
            'is_enabled' => true,
            'sort_order' => $sort++,
            'note' => 'exact -> static_page, 303, enabled',
        ]);

        // 7. Exact, no target, 404 Not Found, enabled.
        $redirects[] = $this->makeRedirect([
            'match_type' => Redirect::TYPE_EXACT,
            'target_type' => Redirect::TARGET_TYPE_NONE,
            'from_url' => '/deleted-page',
            'status_code' => 404,
            'is_enabled' => true,
            'sort_order' => $sort++,
            'note' => 'exact -> none, 404 not found, enabled',
        ]);

        // 8. Exact, no target, 410 Gone, enabled.
        $redirects[] = $this->makeRedirect([
            'match_type' => Redirect::TYPE_EXACT,
            'target_type' => Redirect::TARGET_TYPE_NONE,
            'from_url' => '/retired-offer',
            'status_code' => 410,
            'is_enabled' => true,
            'category_id' => $cats['marketing']->id,
            'sort_order' => $sort++,
            'note' => 'exact -> none, 410 gone, enabled',
        ]);

        // 9. Scheduled redirect (active window in the past -> renders "special"/inactive row + warning).
        $redirects[] = $this->makeRedirect([
            'match_type' => Redirect::TYPE_EXACT,
            'target_type' => Redirect::TARGET_TYPE_PATH_URL,
            'from_url' => '/xmas-2024',
            'to_url' => '/happy-new-year',
            'status_code' => 302,
            'is_enabled' => true,
            'category_id' => $cats['marketing']->id,
            'from_date' => Carbon::now()->subYear()->startOfYear(),
            'to_date' => Carbon::now()->subYear()->endOfYear(),
            'sort_order' => $sort++,
            'note' => 'scheduled, expired window (inactive/special row)',
        ]);

        // 10. Scheduled redirect (currently active window).
        $redirects[] = $this->makeRedirect([
            'match_type' => Redirect::TYPE_EXACT,
            'target_type' => Redirect::TARGET_TYPE_PATH_URL,
            'from_url' => '/current-campaign',
            'to_url' => '/campaigns/live',
            'status_code' => 302,
            'is_enabled' => true,
            'category_id' => $cats['marketing']->id,
            'from_date' => Carbon::now()->subMonth(),
            'to_date' => Carbon::now()->addMonth(),
            'sort_order' => $sort++,
            'note' => 'scheduled, currently active window',
        ]);

        // 11. Deliberately very long from/to URLs to test list truncation + form wrapping.
        $longFrom = '/legacy/deeply/nested/category/structure/that/keeps/going/'
            . 'products/2019/archived/discontinued/'
            . str_repeat('segment/', 6) . 'final-item-with-a-very-long-slug-name';
        $longTo = 'https://www.example.com/new/consolidated/catalogue/'
            . str_repeat('path/', 8) . 'destination?utm_source=redirect&utm_medium=legacy&utm_campaign=migration';
        $redirects[] = $this->makeRedirect([
            'match_type' => Redirect::TYPE_EXACT,
            'target_type' => Redirect::TARGET_TYPE_PATH_URL,
            'from_url' => $longFrom,
            'to_url' => $longTo,
            'status_code' => 301,
            'is_enabled' => true,
            'category_id' => $cats['longName']->id,
            'sort_order' => $sort++,
            'note' => 'very long from/to URLs',
        ]);

        // 12. Regex -> external, disabled, uncategorised.
        $redirects[] = $this->makeRedirect([
            'match_type' => Redirect::TYPE_REGEX,
            'target_type' => Redirect::TARGET_TYPE_PATH_URL,
            'from_url' => '/^\/news\/(\d{4})\/(\d{2})\/(.+)$/',
            'to_url' => 'https://news.example.com/$1-$2/$3',
            'status_code' => 301,
            'is_enabled' => false,
            'sort_order' => $sort++,
            'note' => 'regex -> external, DISABLED',
        ]);

        // Filler rows to paginate the 20-per-page list and give the "hits" column
        // a range of values. Alternate match/target types and enabled state.
        // (Regex filler is skipped here because a regex match type requires the
        // from_url itself to be a valid regular expression — the varied regex
        // examples above already cover that match type.)
        $matchTypes = [Redirect::TYPE_EXACT, Redirect::TYPE_PLACEHOLDERS];
        $statusCodes = [301, 302, 303, 404, 410];
        for ($i = 1; $i <= 22; $i++) {
            $status = $statusCodes[$i % count($statusCodes)];
            $isNoTarget = in_array($status, [404, 410], true);
            $isPlaceholder = ($matchTypes[$i % count($matchTypes)] === Redirect::TYPE_PLACEHOLDERS);

            $attrs = [
                'match_type' => $matchTypes[$i % count($matchTypes)],
                'target_type' => $isNoTarget ? Redirect::TARGET_TYPE_NONE : Redirect::TARGET_TYPE_PATH_URL,
                'from_url' => $isPlaceholder ? "/legacy/{section}/path-{$i}" : "/legacy/path-{$i}",
                'status_code' => $status,
                'is_enabled' => ($i % 4 !== 0),
                'sort_order' => $sort++,
                'note' => "filler #{$i}",
            ];

            if (!$isNoTarget) {
                $attrs['to_url'] = $isPlaceholder ? "/new/{section}/destination-{$i}" : "/new/destination-{$i}";
            }
            if ($i % 3 === 0) {
                $attrs['category_id'] = $cats['legacy']->id;
            }

            $redirects[] = $this->makeRedirect($attrs);
        }

        return $redirects;
    }

    /**
     * Persist a single redirect, stamping the scaffold marker into its
     * `description` so `--fresh` can find it. `note` becomes the visible
     * description suffix.
     *
     * @param array<string, mixed> $attrs
     */
    protected function makeRedirect(array $attrs): Redirect
    {
        $note = $attrs['note'] ?? '';
        unset($attrs['note']);

        $redirect = new Redirect();
        $redirect->fill($attrs);
        $redirect->description = trim(self::MARKER . ' ' . $note);
        $redirect->from_scheme = Redirect::SCHEME_AUTO;
        $redirect->to_scheme = Redirect::SCHEME_AUTO;
        $redirect->save();

        return $redirect;
    }

    /**
     * Seed hit data so the statistics charts + dashboard widgets render.
     *
     * The statistics surfaces read `winter_redirect_clients` (one row per hit,
     * carrying day/month/year/timestamp and an optional crawler string). We
     * spread hits across the current and previous month so both the "hits per
     * day" chart (current month) and the "hits per month" chart have data, and
     * we mark a slice of them as crawler hits so the crawler dataset + the "top
     * crawlers this month" chart populate.
     *
     * We also maintain each redirect's `hits`/`last_used_at` counter (drives the
     * list column + "Top 10 redirects" widget) and write a deduped `RedirectLog`
     * row (drives the per-redirect "Logs" relation tab).
     *
     * @param Redirect[] $redirects
     * @return array{0:int,1:int} [clientCount, logCount]
     */
    protected function seedHits(array $redirects): array
    {
        // A few realistic crawler UA fragments for the "top crawlers" chart.
        $crawlerSamples = array_slice([
            'Googlebot', 'bingbot', 'YandexBot', 'DuckDuckBot', 'facebookexternalhit',
        ], 0, 5);

        // Only enabled, active redirects realistically accrue hits; weight the
        // "featured" first few so the Top-redirects chart has a clear ranking.
        $eligible = array_values(array_filter($redirects, static function (Redirect $r): bool {
            return (bool) $r->is_enabled;
        }));

        $now = Carbon::now();
        $clientRows = [];
        $logAgg = []; // [redirectId] => ['hits' => n, 'log' => [...]]
        $counter = 0;

        foreach ($eligible as $index => $redirect) {
            // Give earlier redirects more hits (clear ranking for the top chart).
            $baseHits = max(3, 60 - ($index * 4));

            for ($h = 0; $h < $baseHits; $h++) {
                $counter++;

                // Spread across ~75 days so both current + previous month have data.
                $timestamp = $now->copy()->subDays(random_int(0, 74))
                    ->setTime(random_int(0, 23), random_int(0, 59), random_int(0, 59));

                // ~25% crawler hits.
                $isCrawler = ($counter % 4 === 0);
                $crawler = $isCrawler ? $crawlerSamples[$counter % count($crawlerSamples)] : null;

                $clientRows[] = [
                    'redirect_id' => $redirect->id,
                    'timestamp' => $timestamp,
                    'day' => $timestamp->day,
                    'month' => $timestamp->month,
                    'year' => $timestamp->year,
                    'crawler' => $crawler,
                ];

                // Aggregate for the redirect hit counter + last_used_at.
                if (!isset($logAgg[$redirect->id])) {
                    $logAgg[$redirect->id] = ['hits' => 0, 'last' => $timestamp];
                }
                $logAgg[$redirect->id]['hits']++;
                if ($timestamp->gt($logAgg[$redirect->id]['last'])) {
                    $logAgg[$redirect->id]['last'] = $timestamp;
                }
            }
        }

        // Bulk-insert client hit records.
        foreach (array_chunk($clientRows, 500) as $chunk) {
            Client::insert(array_map(static function (array $row): array {
                return [
                    'redirect_id' => $row['redirect_id'],
                    'timestamp' => $row['timestamp']->toDateTimeString(),
                    'day' => $row['day'],
                    'month' => $row['month'],
                    'year' => $row['year'],
                    'crawler' => $row['crawler'],
                ];
            }, $chunk));
        }

        // Update each redirect's hit counter + last_used_at and write a deduped log.
        $logCount = 0;
        foreach ($logAgg as $redirectId => $agg) {
            /** @var Redirect $redirect */
            $redirect = collect($eligible)->firstWhere('id', $redirectId);

            $redirect->forceFill([
                'hits' => $agg['hits'],
                'last_used_at' => $agg['last'],
            ])->save();

            $toUrl = $redirect->to_url ?? ($redirect->cms_page ?? ($redirect->static_page ?? ''));

            RedirectLog::create([
                'redirect_id' => $redirectId,
                'from_to_hash' => sha1(($redirect->from_url ?? '') . '|' . $toUrl),
                'status_code' => (string) $redirect->status_code,
                'from_url' => (string) $redirect->from_url,
                'to_url' => (string) $toUrl,
                'hits' => $agg['hits'],
            ]);
            $logCount++;
        }

        return [count($clientRows), $logCount];
    }
}
