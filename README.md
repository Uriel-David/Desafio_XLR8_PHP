# Backend Interview Challenge - XLR8/PHP
Project to develop PHP package for testing in XLR8. The project consists of creating a PHP library to consume the XLR8 API, and with that, through the coordinates of Latitude and Longitude, generate a list of hotels closest to you or cheaper.

## Library Setup with Composer
```shell
composer require urieldavid/desafio_xlr8_php
```

## Use the library

```php
use XLR8\Exception\XLR8Exception;
use XLR8\Search;

try {
    Search::getNearbyHotels(41.157944, -8.629105);
} catch (XLR8Exception $e) {
    echo $e->getMessage();
}
```

Obs.:
- Default param required `getNearbyHotels($latitude, $longitude)`
- To set sort by `"pricepernight"` `getNearbyHotels($latitude, $longitude, "pricepernight")`
- By default sorting is by `"proximity"`
- Others params: `int $page = 0, int $limit = 15, bool $responseJson = false, string $selectSource = null, array $addSources = null`