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
        $this->testHost = Host::normalize($testHost);
        $this->testUrl = $this->testHost === null
            ? url($testPath)
            : self::buildUrlForHost($this->testHost, $testPath);
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

    /**
     * Rebuild the test URL on the rule's own host, keeping the scheme and port of this install so
     * a local or non-standard-port site is still reachable when the tester follows the URL.
     */
    private static function buildUrlForHost(string $host, string $testPath): string
    {
        $base = parse_url(url('/'));
        $scheme = $base['scheme'] ?? 'http';
        $port = isset($base['port']) ? ':' . $base['port'] : '';

        return $scheme . '://' . $host . $port . '/' . ltrim($testPath, '/');
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
        curl_setopt($curlHandle, CURLOPT_HTTPHEADER, [
            'X-Winter-Redirect: Tester',
        ]);
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
