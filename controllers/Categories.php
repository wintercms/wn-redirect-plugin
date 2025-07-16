<?php

declare(strict_types=1);

namespace Winter\Redirect\Controllers;

use Backend\Behaviors;
use Backend\Classes\Controller;

/**
 * @mixin Behaviors\FormController
 * @mixin Behaviors\ListController
 */
final class Categories extends Controller
{
    public $implement = [
        Behaviors\FormController::class,
        Behaviors\ListController::class
    ];

    public $requiredPermissions = ['winter.redirect.access_redirects'];

    public function __construct()
    {
        parent::__construct();

        $this->addCss('/plugins/winter/redirect/assets/css/redirect.css');
    }
}
