<?php

declare(strict_types=1);

namespace Winter\Redirect\Classes\Testers;

use Backend;
use Request;
use Winter\Redirect\Classes\Exceptions\InvalidScheme;
use Winter\Redirect\Classes\Exceptions\NoMatchForRequest;
use Winter\Redirect\Classes\TesterBase;
use Winter\Redirect\Classes\TesterResult;

final class RedirectMatch extends TesterBase
{
    protected function test(): TesterResult
    {
        $manager = $this->getRedirectManager();

        // TODO: Add scheme.
        try {
            $match = $manager->match($this->testPath, Request::getScheme());
        } catch (NoMatchForRequest | InvalidScheme $e) {
            $match = false;
        }

        if ($match === false) {
            return new TesterResult(false, e(trans('winter.redirect::lang.test_lab.not_match_redirect')));
        }

        $message = sprintf(
            '%s <a href="%s" target="_blank">%s</a>.',
            e(trans('winter.redirect::lang.test_lab.matched')),
            Backend::url('winter/redirect/redirects/update/' . $match->getId()),
            e(trans('winter.redirect::lang.test_lab.redirect'))
        );

        return new TesterResult(true, $message);
    }
}
