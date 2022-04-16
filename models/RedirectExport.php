<?php

declare(strict_types=1);

namespace Winter\Redirect\Models;

use Backend\Models\ExportModel;

final class RedirectExport extends ExportModel
{
    public $table = 'winter_redirect_redirects';

    public function exportData($columns, $sessionKey = null)
    {
        return static::make()
            ->get()
            ->toArray();
    }
}
