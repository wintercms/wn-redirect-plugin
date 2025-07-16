<?php

declare(strict_types=1);

namespace Winter\Redirect\Controllers;

use Backend\Classes\Controller;
use Backend\Models\BrandSetting;
use Carbon\Carbon;
use Carbon\Exceptions\InvalidFormatException;
use JsonException;
use SystemException;
use Winter\Redirect\Classes\StatisticsHelper;

/**
 * @property string $pageTitle
 */
final class Statistics extends Controller
{
    public $requiredPermissions = ['winter.redirect.access_redirects'];
    private StatisticsHelper $helper;

    public function __construct()
    {
        parent::__construct();

        $this->pageTitle = 'winter.redirect::lang.title.statistics';

        $this->addCss('/plugins/winter/redirect/assets/css/redirect.css');
        $this->addCss('/plugins/winter/redirect/assets/css/statistics.css');

        $this->helper = new StatisticsHelper();
    }

    public function index(): void
    {
    }

    /**
     * @throws SystemException|JsonException|InvalidFormatException
     */
    public function onLoadHitsPerDay(): array
    {
        $today = Carbon::today();

        $postValue = post('period-month-year', $today->month . '_' . $today->year);

        [$month, $year] = explode('_', $postValue);

        return [
            '#hitsPerDay' => $this->makePartial('hits-per-day', [
                'dataSets' => json_encode([
                    $this->getHitsPerDayAsDataSet((int) $month, (int) $year, true),
                    $this->getHitsPerDayAsDataSet((int) $month, (int) $year, false),
                ], JSON_THROW_ON_ERROR),
                'labels' => json_encode($this->getLabels(), JSON_THROW_ON_ERROR),
                'monthYearOptions' => $this->helper->getMonthYearOptions(),
                'monthYearSelected' => $month . '_' . $year,
            ]),
        ];
    }

    /**
     * @throws SystemException|JsonException
     */
    public function onSelectPeriodMonthYear(): array
    {
        return $this->onLoadHitsPerDay();
    }

    /**
     * @throws SystemException
     */
    public function onLoadTopRedirectsThisMonth(): array
    {
        return [
            '#topRedirectsThisMonth' => $this->makePartial('top-redirects-this-month', [
                'topTenRedirectsThisMonth' => $this->helper->getTopRedirectsThisMonth(),
            ]),
        ];
    }

    /**
     * @throws SystemException
     */
    public function onLoadTopCrawlersThisMonth(): array
    {
        return [
            '#topCrawlersThisMonth' => $this->makePartial('top-crawlers-this-month', [
                'topTenCrawlersThisMonth' => $this->helper->getTopTenCrawlersThisMonth(),
            ]),
        ];
    }

    /**
     * @throws SystemException
     */
    public function onLoadRedirectHitsPerMonth(): array
    {
        return [
            '#redirectHitsPerMonth' => $this->makePartial('redirect-hits-per-month', [
                'redirectHitsPerMonth' => $this->helper->getRedirectHitsPerMonth(),
            ]),
        ];
    }

    /**
     * @throws SystemException
     */
    public function onLoadScoreBoard(): array
    {
        return [
            '#scoreBoard' => $this->makePartial('score-board', [
                'redirectHitsPerMonth' => $this->helper->getRedirectHitsPerMonth(),
                'totalActiveRedirects' => $this->helper->getTotalActiveRedirects(),
                'activeRedirects' => $this->helper->getActiveRedirects(),
                'totalRedirectsServed' => $this->helper->getTotalRedirectsServed(),
                'totalThisMonth' => $this->helper->getTotalThisMonth(),
                'totalLastMonth' => $this->helper->getTotalLastMonth(),
                'latestClient' => $this->helper->getLatestClient(),
            ]),
        ];
    }

    private function getLabels(): array
    {
        $labels = [];

        foreach (Carbon::today()->firstOfMonth()->daysUntil(Carbon::today()->endOfMonth()) as $date) {
            $labels[] = $date->isoFormat('LL');
        }

        return $labels;
    }

    /**
     * @throws InvalidFormatException
     */
    private function getHitsPerDayAsDataSet(int $month, int $year, bool $crawler): array
    {
        $today = Carbon::createFromDate($year, $month, 1);

        $data = $this->helper->getRedirectHitsPerDay($month, $year, $crawler);

        for ($i = $today->firstOfMonth()->day; $i <= $today->lastOfMonth()->day; $i++) {
            if (!array_key_exists($i, $data)) {
                $data[$i] = ['hits' => 0];
            }
        }

        ksort($data);

        $brandSettings = new BrandSetting();

        $color = $crawler
            ? $brandSettings->get('primary_color')
            : $brandSettings->get('secondary_color');

        [$r, $g, $b] = sscanf($color, "#%02x%02x%02x");

        return [
            'label' => $crawler
                ? e(trans('winter.redirect::lang.statistics.crawler_hits'))
                : e(trans('winter.redirect::lang.statistics.visitor_hits')),
            'backgroundColor' => sprintf('rgb(%d, %d, %d, 0.5)', $r, $g, $b),
            'borderColor' => sprintf('rgb(%d, %d, %d, 1)', $r, $g, $b),
            'borderWidth' => 1,
            'data' => data_get($data, '*.hits'),
        ];
    }
}
