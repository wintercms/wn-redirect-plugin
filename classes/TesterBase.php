<?php

declare(strict_types=1);

namespace Winter\Redirect\Classes;

use InvalidArgumentException;
use Symfony\Component\Stopwatch\Stopwatch;
use Winter\Redirect\Classes\Contracts\RedirectManagerInterface;
use Winter\Redirect\Classes\Contracts\TesterInterface;
use Winter\Redirect\Classes\Util\Host;
use Winter\Redirect\Models\Settings;

abstract class TesterBase implements TesterInterface
{
    /**
     * Maximum redirects to follow.
     */
    public const MAX_REDIRECTS = 10;

    /**
     * Connection timeout in seconds.
     */
    public const CONNECTION_TIMEOUT = 10;

    protected string $testUrl;
    protected string $testPath;
    protected ?string $testHost;

    public function __construct(string $testPath, ?string $testHost = null)
    {
        $this->testPath = $testPath;
        $this->testHost = Host::toTestable($testHost);

        // Always this site. The host a rule is limited to is sent as a request header instead of
        // being built into the URL: it comes from user input, and putting it in the URL would let
        // anyone who can manage redirects point the tester's cURL calls at a loopback address, a
        // private range or a cloud metadata endpoint.
        $this->testUrl = url($testPath);
    }

    final public function execute(): TesterResult
    {
        $stopwatch = new Stopwatch();

        $stopwatch->start(__FUNCTION__);

        $result = $this->test();

        $event = $stopwatch->stop(__FUNCTION__);

        $result->setDuration((int) $event->getDuration());

        return $result;
    }

    public function getTestPath(): string
    {
        return $this->testPath;
    }

    public function getTestUrl(): string
    {
        return $this->testUrl;
    }

    /**
     * The host this test runs against, or null when the rule is not limited to one.
     */
    public function getTestHost(): ?string
    {
        return $this->testHost;
    }

    abstract protected function test(): TesterResult;

    /**
     * @throws InvalidArgumentException
     */
    protected function setDefaultCurlOptions($curlHandle): void
    {
        if (!is_resource($curlHandle)) {
            throw new InvalidArgumentException('Argument must be a valid resource type.');
        }

        curl_setopt($curlHandle, CURLOPT_MAXREDIRS, self::MAX_REDIRECTS);
        curl_setopt($curlHandle, CURLOPT_CONNECTTIMEOUT, self::CONNECTION_TIMEOUT);
        curl_setopt($curlHandle, CURLOPT_AUTOREFERER, true);

        // This constant is not available when open_basedir or safe_mode are enabled.
        curl_setopt($curlHandle, CURLOPT_FOLLOWLOCATION, true);

        /** @noinspection CurlSslServerSpoofingInspection */
        curl_setopt($curlHandle, CURLOPT_SSL_VERIFYHOST, false);
        /** @noinspection CurlSslServerSpoofingInspection */
        curl_setopt($curlHandle, CURLOPT_SSL_VERIFYPEER, false);

        if (defined('CURLOPT_SSL_VERIFYSTATUS')) {
            curl_setopt($curlHandle, CURLOPT_SSL_VERIFYSTATUS, false);
        }

        curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curlHandle, CURLOPT_VERBOSE, false);
        $headers = ['X-Winter-Redirect: Tester'];

        // Makes the request arrive as though it were addressed to the host the rule is limited to,
        // without the connection ever leaving this site.
        if ($this->testHost !== null) {
            $headers[] = 'Host: ' . $this->testHost;
        }

        curl_setopt($curlHandle, CURLOPT_HTTPHEADER, $headers);
    }

    protected function getRedirectManager(): RedirectManagerInterface
    {
        /** @var RedirectManagerInterface $manager */
        $manager = resolve(RedirectManagerInterface::class);
        return $manager->setSettings(new RedirectManagerSettings(
            false,
            false,
            Settings::isRelativePathsEnabled()
        ));
    }
}
