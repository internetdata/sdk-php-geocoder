# [<img src="https://s3.internetdata.io/internetdata-public/brand/mark.svg" alt="InternetData" height="28"/>](https://internetdata.io/) InternetData Provider for Geocoder PHP

[![Packagist](https://img.shields.io/packagist/v/internetdata/geocoder-php-provider.svg)](https://packagist.org/packages/internetdata/geocoder-php-provider)
[![license](https://img.shields.io/packagist/l/internetdata/geocoder-php-provider.svg)](LICENSE)

The official [Geocoder PHP](https://github.com/geocoder-php/Geocoder) provider for the [InternetData](https://internetdata.io) API.

It geolocates IPv4 and IPv6 addresses. Without a key you get the country and timezone of every address. With a key on a paid plan, or within a free key's trial, you also get the city, region, postal code and coordinates.

## Getting Started

```bash
composer require internetdata/geocoder-php-provider
```

Like every Geocoder provider it takes a PSR-18 HTTP client, so bring the one your project already uses, Guzzle or Symfony's for example.

```php
use Geocoder\Query\GeocodeQuery;
use GuzzleHttp\Client;
use InternetData\Geocoder\InternetData;

$provider = new InternetData(new Client());

$address = $provider->geocodeQuery(GeocodeQuery::create('8.8.8.8'))->first();
echo $address->getCountry()->getCode();  // US
echo $address->getTimezone();            // America/Los_Angeles
```

To get the city and coordinates, pass your API key from your [dashboard](https://app.internetdata.io):

```php
$provider = new InternetData(new Client(), getenv('INTERNETDATA_API_KEY'));

$address = $provider->geocodeQuery(GeocodeQuery::create('74.125.45.100'))->first();
echo $address->getLocality();                            // Tulsa
echo $address->getAdminLevels()->get(1)->getName();      // Oklahoma
echo $address->getCoordinates()->getLatitude();          // 36.15398
```

It works with `StatefulGeocoder`, `ProviderAggregator` and the Laravel and Symfony bundles like any other provider.

## Laravel

With [Geocoder for Laravel](https://github.com/geocoder-php/GeocoderLaravel), install both and publish its config:

```bash
composer require -W toin0u/geocoder-laravel internetdata/geocoder-php-provider
php artisan vendor:publish --provider="Geocoder\Laravel\Providers\GeocoderService"
```

`-W` lets Composer move `guzzlehttp/psr7` back to the 2.x the bundle takes, since a new Laravel app ships 3.x.

Then make InternetData the provider in `config/geocoder.php`. The bundle passes its own HTTP client, so the only argument is your key, and with no `INTERNETDATA_API_KEY` in `.env` it looks up without one:

```php
use InternetData\Geocoder\InternetData;

'providers' => [
    InternetData::class => [
        env('INTERNETDATA_API_KEY'),
    ],
],
```

```php
$address = app('geocoder')->geocode('8.8.8.8')->get()->first();
echo $address->getCountry()->getCode();  // US
```

## What it answers

| Geocoder field | Without a key | Paid plan or trial |
|---|---|---|
| `getCountry()` code and name | yes | yes |
| `getTimezone()` | yes | yes |
| `getLocality()` (city) | | yes |
| `getAdminLevels()` level 1 (region, with its ISO 3166-2 code) | | yes |
| `getPostalCode()` | | yes |
| `getCoordinates()` | | yes |

A private or reserved address, like `10.0.0.1`, comes back as an empty collection, and `127.0.0.1` and `::1` as `localhost` without a request. A street address or a reverse query throws `UnsupportedOperation`, a wrong key `InvalidCredentials` and a spent quota `QuotaExceeded`. Plans and their limits are on the [pricing page](https://internetdata.io/pricing).

## Other Libraries

There are official InternetData client libraries available for many languages including PHP, Python, Go, Java, and Ruby. See our GitHub at https://github.com/internetdata for more.

## About InternetData

Geolocation, anonymity, ownership, network, service and domain data for any IP address, AS or domain, by API or as databases you download.

[<picture><source media="(prefers-color-scheme: dark)" srcset="https://s3.internetdata.io/internetdata-public/brand/logo-dark.svg"><img src="https://s3.internetdata.io/internetdata-public/brand/logo.svg" alt="InternetData" height="40"/></picture>](https://internetdata.io/)

## License

This project is licensed under the [MIT License](LICENSE).
