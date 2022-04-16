<?php

/** @noinspection PhpUnused */
/** @noinspection AutoloadingIssuesInspection */

declare(strict_types=1);

namespace Winter\Redirect\Updates;

use Psr\Log\LoggerInterface;
use Throwable;
use Winter\Storm\Database\Schema\Blueprint;
use Winter\Storm\Database\Updates\Migration;
use Winter\Storm\Support\Facades\Schema;

class AddMonthYearCrawlerIndexOnClientsTable extends Migration
{
    public function up(): void
    {
        Schema::table('vdlp_redirect_clients', static function (Blueprint $table) {
            $table->index(
                [
                    'month',
                    'year',
                    'crawler'
                ],
                'month_year_crawler'
            );

            $table->index(
                [
                    'month',
                    'year',
                ],
                'month_year'
            );
        });
    }

    public function down(): void
    {
        try {
            Schema::table('vdlp_redirect_clients', static function (Blueprint $table) {
                $table->dropIndex('month_year_crawler');
                $table->dropIndex('month_year');
            });
        } catch (Throwable $e) {
            resolve(LoggerInterface::class)->error(sprintf(
                'Winter.Redirect: Unable to drop index `%s`, `%s` from table `%s`: %s',
                'month_year_crawler',
                'month_year',
                'vdlp_redirect_clients',
                $e->getMessage()
            ));
        }
    }
}
