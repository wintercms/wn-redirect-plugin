<?php

declare(strict_types=1);

use Winter\Storm\Database\Schema\Blueprint;
use Winter\Storm\Database\Updates\Migration;
use Winter\Storm\Support\Facades\Schema;

class RenameToWinterRedirect extends Migration
{
    const TABLES = [
        'categories',
        'clients',
        'redirect_logs',
        'redirects',
    ];

    protected $oldPrefix = 'vdlp_redirect_';
    protected $newPrefix = 'winter_redirect_';

    public function up()
    {
        Schema::disableForeignKeyConstraints();

        foreach (self::TABLES as $table) {
            $from = $this->oldPrefix . $table;
            $to = $this->newPrefix . $table;

            if (Schema::hasTable($from) && !Schema::hasTable($to)) {
                Schema::rename($from, $to);
                $this->updateIndexNames($from, $to, $to);
            }
        }

        if (Schema::hasColumn('system_request_logs', 'vdlp_redirect_redirect_id')) {
            Schema::table('system_request_logs', function (Blueprint $table) {
                $table->renameColumn('vdlp_redirect_redirect_id', 'winter_redirect_redirect_id');

                $table->dropForeign('vdlp_redirect_request_log');

                $table->foreign('winter_redirect_redirect_id', 'winter_redirect_request_log')
                    ->references('id')
                    ->on('winter_redirect_redirects')
                    ->onDelete('set null');
            });
        }

        Schema::enableForeignKeyConstraints();

        // Migrate the plugin settings
        DB::table('system_settings')
            ->where('item', $this->oldPrefix . 'settings')
            ->update(['item' => $this->newPrefix . 'settings']);
    }

    public function down()
    {
        Schema::disableForeignKeyConstraints();

        foreach (self::TABLES as $table) {
            $from = $this->newPrefix . $table;
            $to = $this->oldPrefix . $table;

            if (Schema::hasTable($from) && !Schema::hasTable($to)) {
                Schema::rename($from, $to);
                $this->updateIndexNames($from, $to, $from);
            }
        }

        if (Schema::hasColumn('system_request_logs', 'winter_redirect_redirect_id')) {
            Schema::table('system_request_logs', function (Blueprint $table) {
                $table->renameColumn('winter_redirect_redirect_id', 'vdlp_redirect_redirect_id');

                $table->dropForeign('winter_redirect_request_log');

                $table->foreign('vdlp_redirect_redirect_id', 'vdlp_redirect_request_log')
                    ->references('id')
                    ->on('vdlp_redirect_redirects')
                    ->onDelete('set null');
            });
        }

        Schema::enableForeignKeyConstraints();

        // Migrate the plugin settings
        DB::table('system_settings')
            ->where('item', $this->newPrefix . 'settings')
            ->update(['item' => $this->oldPrefix . 'settings']);
    }

    public function updateIndexNames($from, $to, $table)
    {
        Schema::table($table, function ($blueprint) use ($from, $to) {
            foreach ($this->getIndexes($blueprint) as $index) {
                if (is_object($index) ? $index->isPrimary() : $index['primary']) {
                    continue;
                }

                $old = is_object($index) ? $index->getName() : $index['name'];
                $new = str_replace($from, $to, $old);

                $blueprint->renameIndex($old, $new);
            }
        });
    }

    public function getIndexes($blueprint)
    {
        $connection = Schema::getConnection();
        $table = $blueprint->getTable();

        if (method_exists($connection, 'getDoctrineSchemaManager')) {
            $sm = $connection->getDoctrineSchemaManager();
            return $sm->listTableDetails($table)->getIndexes();
        } else {
            return $connection->getSchemaBuilder()->getIndexes($table);
        }
    }
}
