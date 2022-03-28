<?php

declare(strict_types=1);

namespace Winter\Redirect\Controllers;

use Backend\Classes\Controller;
use Backend\Facades\BackendMenu;
use System\Classes\PluginManager;

final class Extensions extends Controller
{
    /** @var string[] */
    private static array $extensions = [
        'Winter.RedirectConditions',
        'Winter.RedirectConditionsDomain',
        'Winter.RedirectConditionsExample',
        'Winter.RedirectConditionsHeader',
        'Winter.RedirectConditionsUserAgent',
    ];

    public function __construct()
    {
        parent::__construct();

        BackendMenu::setContext('Winter.Redirect', 'redirect', 'extensions');

        $this->addCss('/plugins/winter/redirect/assets/css/redirect.css');

        $this->pageTitle = 'Redirect Extensions (new)';
    }

    public function index(): void
    {
        $this->vars['extensions'] = [];

        foreach (self::$extensions as $extension) {
            $this->vars['extensions'][$extension] = PluginManager::instance()->hasPlugin($extension)
                && !PluginManager::instance()->isDisabled($extension);
        }
    }
}
