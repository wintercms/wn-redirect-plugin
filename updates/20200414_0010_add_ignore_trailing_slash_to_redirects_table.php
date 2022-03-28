<?php

declare(strict_types=1);

use Psr\Log\LoggerInterface;
use Winter\Storm\Database\Schema\Blueprint;
use Winter\Storm\Database\Updates\Migration;

class AddIgnoreTrailingSlashToRedirectsTable extends Migration
{
    public function up(): void
    {
        Schema::table('vdlp_redirect_redirects', static function (Blueprint $table) {
            $table->boolean('ignore_trailing_slash')
                ->default(false)
                ->after('ignore_case');
        });
    }

    public function down(): void
    {
        try {
            Schema::table('vdlp_redirect_redirects', static function (Blueprint $table) {
                $table->dropColumn('ignore_trailing_slash');
            });
        } catch (Throwable $e) {
            resolve(LoggerInterface::class)->error(sprintf(
                'Winter.Redirect: Unable to drop column `%s` from table `%s`: %s',
                'ignore_trailing_slash',
                'vdlp_redirect_redirects',
                $e->getMessage()
            ));
        }
    }
}
