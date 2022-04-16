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

class AddTimestampCrawlerIndexOnClientsTable extends Migration
{
    public function up(): void
    {
        Schema::table('vdlp_redirect_clients', static function (Blueprint $table) {
            $table->index(
                [
                    'timestamp',
                    'crawler'
                ],
                'timestamp_crawler'
            );
        });
    }

    public function down(): void
    {
        try {
            Schema::table('vdlp_redirect_clients', static function (Blueprint $table) {
                $table->dropIndex('timestamp_crawler');
            });
        } catch (Throwable $e) {
            resolve(LoggerInterface::class)->error(sprintf(
                'Winter.Redirect: Unable to drop index `%s` from table `%s`: %s',
                'timestamp_crawler',
                'vdlp_redirect_clients',
                $e->getMessage()
            ));
        }
    }
}
