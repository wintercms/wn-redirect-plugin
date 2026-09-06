<?php

declare(strict_types=1);

namespace Winter\Redirect\Updates;

use Winter\Storm\Database\Schema\Blueprint;
use Winter\Storm\Database\Updates\Migration;
use Winter\Storm\Support\Facades\Schema;

class AddFromHostToRedirectsTable extends Migration
{
    public function up(): void
    {
        Schema::table('winter_redirect_redirects', function (Blueprint $table) {
            $table->string('from_host')
                ->nullable()
                ->after('from_url');
        });
    }

    public function down(): void
    {
        Schema::table('winter_redirect_redirects', function (Blueprint $table) {
            $table->dropColumn('from_host');
        });
    }
}
