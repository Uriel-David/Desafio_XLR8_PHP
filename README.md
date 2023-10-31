# Backend Interview Challenge - XLR8/PHP
Project to develop PHP package for testing in XLR8. The project consists of creating a PHP library to consume the XLR8 API, and with that, through the coordinates of Latitude and Longitude, generate a list of hotels closest to you or cheaper.

## Library Setup with Composer
```shell
composer require urieldavid/desafio_xlr8_php
```
After that, it is necessary to create the json, config.json in the root of the project, with valid endpoints to use the library, below is an example of the file:
```json
{
    "source_1": "https://xlr8-interview-files.s3.eu-west-2.amazonaws.com/source_1.json",
    "source_2": "https://xlr8-interview-files.s3.eu-west-2.amazonaws.com/source_2.json"
}
```

## Use the library

```php
use XLR8\Exception\XLR8Exception;
use XLR8\Search;

require implode(DIRECTORY_SEPARATOR, [__DIR__, "vendor", "autoload.php"]);

try {
    $search = new Search();
    $search->getNearbyHotels(41.157944, -8.629105, "pricepernight");
} catch (XLR8Exception $e) {
    echo $e->getMessage();
}
```

Obs.:
- Default param required `getNearbyHotels($latitude, $longitude)`
- To set sort by `"pricepernight"` `getNearbyHotels($latitude, $longitude, "pricepernight")`
- By default sorting is by `"proximity"`
- Others params: `int $page = 0, int $limit = 0, bool $responseJson = false, string $selectSource = null`

  Obs.: By default it will bring all the results, if you want pagination then you need to pass the values ​​in the parameters, `$page = selected page` and `$limit = data limit per page`.
