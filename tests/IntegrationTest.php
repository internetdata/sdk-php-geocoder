<?php

declare(strict_types=1);

namespace InternetData\Geocoder\Tests;

use Geocoder\IntegrationTest\ProviderIntegrationTest;
use InternetData\Geocoder\InternetData;
use Psr\Http\Client\ClientInterface;

/**
 * Geocoder's own contract for a provider: the answers' shape, and the
 * exception each failure of the API maps to.
 */
class IntegrationTest extends ProviderIntegrationTest
{
    protected bool $testAddress = false;

    protected bool $testReverse = false;

    protected bool $testIpv6 = true;

    protected function createProvider(ClientInterface $httpClient)
    {
        return new InternetData($httpClient);
    }

    protected function getCacheDir(): string
    {
        return __DIR__.'/.cached_responses';
    }

    protected function getApiKey(): string
    {
        return '';
    }
}
