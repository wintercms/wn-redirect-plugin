<?php

declare(strict_types=1);

namespace Winter\Redirect\Models;

use Winter\Storm\Database\Model;

final class Client extends Model
{
    public $table = 'vdlp_redirect_clients';

    public $belongsTo = [
        'redirect' => Redirect::class,
    ];

    public $timestamps = false;

    protected $guarded = [];
}
