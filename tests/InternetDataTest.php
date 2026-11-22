<?php

declare(strict_types=1);

namespace InternetData\Geocoder\Tests;

use Geocoder\Exception\InvalidCredentials;
use Geocoder\Exception\UnsupportedOperation;
use Geocoder\IntegrationTest\BaseTestCase;
use Geocoder\Model\Address;
use Geocoder\Query\GeocodeQuery;
use Geocoder\Query\ReverseQuery;
use InternetData\Geocoder\InternetData;
use Nyholm\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;

class InternetDataTest extends BaseTestCase
{
    protected function getCacheDir(): string
    {
        return __DIR__.'/.cached_responses';
    }

    public function testGetName(): void
    {
        $this->assertSame('internetdata', (new InternetData($this->getMockedHttpClient()))->getName());
    }

    public function testStreetAddressIsUnsupported(): void
    {
        $this->expectException(UnsupportedOperation::class);
        $this->expectExceptionMessage('The InternetData provider does not support street addresses, only IP addresses.');
        (new InternetData($this->getMockedHttpClient()))->geocodeQuery(GeocodeQuery::create('10 avenue Gambetta, Paris, France'));
    }

    public function testReverseIsUnsupported(): void
    {
        $this->expectException(UnsupportedOperation::class);
        (new InternetData($this->getMockedHttpClient()))->reverseQuery(ReverseQuery::fromCoordinates(0, 0));
    }

    /** @dataProvider provideLocalhostIps */
    public function testLocalhostAsksNothing(string $ip): void
    {
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->never())->method('sendRequest');
        $result = (new InternetData($client))->geocodeQuery(GeocodeQuery::create($ip))->first();
        $this->assertSame('localhost', $result->getLocality());
    }

    /** @return iterable<string[]> */
    public static function provideLocalhostIps(): iterable
    {
        yield ['127.0.0.1'];
        yield ['::1'];
    }

    public function testNoKeySendsNoAuthorization(): void
    {
        $seen = null;
        $client = $this->getMockedHttpClientCallback(function (RequestInterface $request) use (&$seen) {
            $seen = $request;

            return new Response(200, [], '{"location":{"country":"US","country_name":"United States","timezone":"America/Chicago"}}');
        });
        (new InternetData($client, '  '))->geocodeQuery(GeocodeQuery::create('8.8.8.8'));
        $this->assertSame('https://api.internetdata.io/8.8.8.8', (string) $seen->getUri());
        $this->assertFalse($seen->hasHeader('Authorization'));
    }

    public function testKeyIsSentAsBearer(): void
    {
        $seen = null;
        $client = $this->getMockedHttpClientCallback(function (RequestInterface $request) use (&$seen) {
            $seen = $request;

            return new Response(200, [], '{"location":{"country":"US"}}');
        });
        (new InternetData($client, 'k', 'https://example.test/'))->geocodeQuery(GeocodeQuery::create('8.8.8.8'));
        $this->assertSame('https://example.test/8.8.8.8', (string) $seen->getUri());
        $this->assertSame('Bearer k', $seen->getHeaderLine('Authorization'));
    }

    public function testWrongKeyIsInvalidCredentials(): void
    {
        $this->expectException(InvalidCredentials::class);
        (new InternetData($this->getMockedHttpClient('{"error":"invalid API key"}', 401), 'wrong'))->geocodeQuery(GeocodeQuery::create('8.8.8.8'));
    }

    public function testBogonAnswersNothing(): void
    {
        $body = '{"ip":"10.0.0.1","is_bogon":true,"location":{"continent":"","country":"","country_name":"","timezone":""}}';
        $results = (new InternetData($this->getMockedHttpClient($body)))->geocodeQuery(GeocodeQuery::create('10.0.0.1'));
        $this->assertCount(0, $results);
    }

    public function testKeylessAnswerIsCountryLevel(): void
    {
        $results = (new InternetData($this->getHttpClient()))->geocodeQuery(GeocodeQuery::create('8.8.4.4'));
        $this->assertCount(1, $results);
        /** @var Address $result */
        $result = $results->first();
        $this->assertSame('internetdata', $result->getProvidedBy());
        $this->assertSame('US', $result->getCountry()->getCode());
        $this->assertSame('United States', $result->getCountry()->getName());
        $this->assertNotNull($result->getTimezone());
        $this->assertNull($result->getCoordinates());
        $this->assertNull($result->getLocality());
        $this->assertCount(0, $result->getAdminLevels());
    }

    public function testKeyedAnswerIsCityLevelIpv4(): void
    {
        $result = $this->keyed()->geocodeQuery(GeocodeQuery::create('74.125.45.100'))->first();
        $this->assertSame('Tulsa', $result->getLocality());
        $this->assertSame('74103', $result->getPostalCode());
        $this->assertSame('Oklahoma', $result->getAdminLevels()->get(1)->getName());
        $this->assertSame('OK', $result->getAdminLevels()->get(1)->getCode());
        $this->assertSame('US', $result->getCountry()->getCode());
        $this->assertSame('America/Chicago', $result->getTimezone());
        $this->assertEqualsWithDelta(36.154, $result->getCoordinates()->getLatitude(), 0.01);
        $this->assertEqualsWithDelta(-95.993, $result->getCoordinates()->getLongitude(), 0.01);
    }

    public function testKeyedAnswerIsCityLevelIpv6(): void
    {
        $result = $this->keyed()->geocodeQuery(GeocodeQuery::create('2601:9:7680:363:75df:f491:6f85:352f'))->first();
        $this->assertSame('Mount Laurel', $result->getLocality());
        $this->assertSame('08054', $result->getPostalCode());
        $this->assertSame('NJ', $result->getAdminLevels()->get(1)->getCode());
        $this->assertEqualsWithDelta(39.934, $result->getCoordinates()->getLatitude(), 0.01);
    }

    /**
     * Recording these answers needs a key whose plan includes the city, as
     * INTERNETDATA_API_KEY; replaying them needs none, since the cache file is
     * keyed by the URL alone.
     */
    private function keyed(): InternetData
    {
        $key = $_SERVER['INTERNETDATA_API_KEY'] ?? getenv('INTERNETDATA_API_KEY') ?: null;

        return new InternetData($this->getHttpClient($key ?: null), $key ?: null);
    }
}
