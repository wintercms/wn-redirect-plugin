<?php

declare(strict_types=1);

namespace Winter\Redirect\Classes\Testers;

use InvalidArgumentException;
use Winter\Redirect\Classes\TesterBase;
use Winter\Redirect\Classes\TesterResult;

final class RedirectLoop extends TesterBase
{
    /**
     * @throws InvalidArgumentException
     */
    protected function test(): TesterResult
    {
        $curlHandle = curl_init($this->testUrl);

        $this->setDefaultCurlOptions($curlHandle);

        curl_setopt($curlHandle, CURLOPT_MAXREDIRS, 20);

        $error = null;

        if (curl_exec($curlHandle) === false
            && curl_errno($curlHandle) === CURLE_TOO_MANY_REDIRECTS) {
            $error = e(trans('winter.redirect::lang.test_lab.possible_loop'));
        }

        curl_close($curlHandle);

        $message = $error ?? e(trans('winter.redirect::lang.test_lab.no_loop'));

        return new TesterResult($error === null, $message);
    }
}
