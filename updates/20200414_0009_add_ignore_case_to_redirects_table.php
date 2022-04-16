<?php

declare(strict_types=1);

use Psr\Log\LoggerInterface;
use Winter\Storm\Database\Schema\Blueprint;
use Winter\Storm\Database\Updates\Migration;

class AddIgnoreCaseToRedirectsTable extends Migration
{
    public function up(): void
    {
        Schema::table('vdlp_redirect_redirects', static function (Blueprint $table) {
            $table->boolean('ignore_case')
                ->default(false)
                ->after('ignore_query_parameters');
        });
    }

    public function down(): void
    {
        try {
            Schema::table('vdlp_redirect_redirects', static function (Blueprint $table) {
                $table->dropColumn('ignore_case');
            });
        } catch (Throwable $e) {
            resolve(LoggerInterface::class)->error(sprintf(
                'Winter.Redirect: Unable to drop column `%s` from table `%s`: %s',
                'ignore_case',
                'vdlp_redirect_redirects',
                $e->getMessage()
            ));
        }
    }
}
