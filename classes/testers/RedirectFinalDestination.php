<?php

declare(strict_types=1);

namespace Winter\Redirect\Classes\Testers;

use InvalidArgumentException;
use Winter\Redirect\Classes\TesterBase;
use Winter\Redirect\Classes\TesterResult;

final class RedirectFinalDestination extends TesterBase
{
    /**
     * @throws InvalidArgumentException
     */
    protected function test(): TesterResult
    {
        $curlHandle = curl_init($this->testUrl);

        $this->setDefaultCurlOptions($curlHandle);

        curl_setopt($curlHandle, CURLOPT_FOLLOWLOCATION, false);

        $error = null;

        if (curl_exec($curlHandle) === false) {
            $error = e(trans('winter.redirect::lang.test_lab.not_determinate_destination_url'));
        }

        $finalDestination = curl_getinfo($curlHandle, CURLINFO_REDIRECT_URL);
        $statusCode = (int) curl_getinfo($curlHandle, CURLINFO_HTTP_CODE);

        curl_close($curlHandle);

        if (empty($finalDestination) && $statusCode > 400) {
            $message = $error ?? e(trans('winter.redirect::lang.test_lab.no_destination_url'));
        } else {
            $finalDestination = sprintf(
                '<a href="%s" target="_blank">%s</a>',
                e($finalDestination),
                e($finalDestination)
            );

            $message = $error
                ?? trans('winter.redirect::lang.test_lab.final_destination_is', ['destination' => $finalDestination]);
        }

        return new TesterResult($error === null, $message);
    }
}
