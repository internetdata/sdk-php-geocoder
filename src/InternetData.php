<?php

declare(strict_types=1);

namespace InternetData\Geocoder;

use Geocoder\Collection;
use Geocoder\Exception\InvalidServerResponse;
use Geocoder\Exception\UnsupportedOperation;
use Geocoder\Http\Provider\AbstractHttpProvider;
use Geocoder\Model\Address;
use Geocoder\Model\AddressCollection;
use Geocoder\Provider\Provider;
use Geocoder\Query\GeocodeQuery;
use Geocoder\Query\ReverseQuery;
use Psr\Http\Client\ClientInterface;

/**
 * Geolocates an IPv4 or IPv6 address with the InternetData API.
 *
 * Without a key every address gets its country and timezone, on the free
 * Unlimited plan. A key whose plan includes them adds the city, region, postal
 * code and coordinates: https://internetdata.io/pricing
 */
final class InternetData extends AbstractHttpProvider implements Provider
{
    public const DEFAULT_BASE_URL = 'https://api.internetdata.io';

    private ?string $apiKey;

    private string $baseUrl;

    public function __construct(ClientInterface $client, ?string $apiKey = null, string $baseUrl = self::DEFAULT_BASE_URL)
    {
        parent::__construct($client);
        // A key of blanks sent as `Bearer ` reads as a wrong key rather than as
        // none, so it is sent only when there is one.
        $this->apiKey = null === $apiKey || '' === trim($apiKey) ? null : trim($apiKey);
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    public function geocodeQuery(GeocodeQuery $query): Collection
    {
        $address = $query->getText();
        if (!filter_var($address, FILTER_VALIDATE_IP)) {
            throw new UnsupportedOperation('The InternetData provider does not support street addresses, only IP addresses.');
        }
        if (in_array($address, ['127.0.0.1', '::1'], true)) {
            return new AddressCollection([$this->getLocationForLocalhost()]);
        }

        $request = $this->getRequest($this->baseUrl.'/'.$address);
        if (null !== $this->apiKey) {
            $request = $request->withHeader('Authorization', 'Bearer '.$this->apiKey);
        }
        $body = $this->getParsedResponse($request);
        $data = json_decode($body, true);
        if (!is_array($data)) {
            throw InvalidServerResponse::create((string) $request->getUri());
        }

        $location = $data['location'] ?? [];
        // A private or reserved address answers with every location field
        // empty, as does a public one nobody has placed.
        if (!is_array($location) || '' === ($location['country'] ?? '')) {
            return new AddressCollection([]);
        }

        return new AddressCollection([$this->toAddress($location)]);
    }

    public function reverseQuery(ReverseQuery $query): Collection
    {
        throw new UnsupportedOperation('The InternetData provider is not able to do reverse geocoding.');
    }

    public function getName(): string
    {
        return 'internetdata';
    }

    /**
     * @param array<string, mixed> $location
     */
    private function toAddress(array $location): Address
    {
        $adminLevels = [];
        if (null !== ($region = self::text($location, 'region'))) {
            $code = self::text($location, 'region_code');
            // The API's code is ISO 3166-2, `US-OK`; Geocoder's other IP
            // providers give the subdivision's own part, `OK`.
            if (null !== $code && str_contains($code, '-')) {
                $code = substr($code, strpos($code, '-') + 1);
            }
            $adminLevels[] = ['name' => $region, 'code' => $code, 'level' => 1];
        }

        return Address::createFromArray([
            'providedBy' => $this->getName(),
            'latitude' => is_numeric($location['latitude'] ?? null) ? (float) $location['latitude'] : null,
            'longitude' => is_numeric($location['longitude'] ?? null) ? (float) $location['longitude'] : null,
            'locality' => self::text($location, 'city'),
            'postalCode' => self::text($location, 'postal_code'),
            'adminLevels' => $adminLevels,
            'country' => self::text($location, 'country_name'),
            'countryCode' => self::text($location, 'country'),
            'timezone' => self::text($location, 'timezone'),
        ]);
    }

    /**
     * @param array<string, mixed> $location
     */
    private static function text(array $location, string $field): ?string
    {
        $value = $location[$field] ?? null;

        return is_string($value) && '' !== $value ? $value : null;
    }
}
