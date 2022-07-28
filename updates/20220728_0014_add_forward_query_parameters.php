<?php

declare(strict_types=1);

namespace Winter\Redirect\Updates;

use Winter\Storm\Database\Schema\Blueprint;
use Winter\Storm\Database\Updates\Migration;
use Winter\Storm\Support\Facades\Schema;

class AddQueryParametersForward extends Migration
{
    public function up(): void
    {
        Schema::table('winter_redirect_redirects', function (Blueprint $table) {
            $table->boolean('forward_query_parameters')
                ->default(false)
                ->after('ignore_trailing_slash');
        });
    }

    public function down(): void
    {
        Schema::table('winter_redirect_redirects', function (Blueprint $table) {
            $table->dropColumn('forward_query_parameters');
        });
    }
}
