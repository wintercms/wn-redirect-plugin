<?php

declare(strict_types=1);

namespace Winter\Redirect\Console;

use Illuminate\Console\Command;
use Winter\Redirect\Classes\PublishManager;

final class PublishRedirectsCommand extends Command
{
    public function __construct()
    {
        $this->name = 'winter:redirect:publish-redirects';
        $this->description = 'Publish all redirects.';

        parent::__construct();
    }

    public function handle(PublishManager $publishManager): void
    {
        $publishManager->publish();
    }
}
