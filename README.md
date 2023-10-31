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
OR
```php
use XLR8\Exception\XLR8Exception;
use XLR8\Search;

require implode(DIRECTORY_SEPARATOR, [__DIR__, "vendor", "autoload.php"]);

try {
    $options = [
        'page' => 1,
        'limit' => 15,
        'responseJson' => false | true,
        'selectSource' => null | 'source_1' | 'any other endpoint in config.json'
    ];
    $search = new Search();
    $search->getNearbyHotels(41.157944, -8.629105, "pricepernight", $options);
} catch (XLR8Exception $e) {
    echo $e->getMessage();
}
```

Obs.:
- Default param required `getNearbyHotels($latitude, $longitude)`
- To set sort by `"pricepernight"` => `getNearbyHotels($latitude, $longitude, "pricepernight")`
- By default sorting is by `"proximity"`
- Param options:
    ```php
        $options = [
            'page' => 1,
            'limit' => 15,
            'responseJson' => false | true,
            'selectSource' => null | 'source_1' | 'any other endpoint in config.json'
        ];
    ```

  Obs-1.: By default it will bring all the results, if you want pagination then you need to pass the values ​​in the options param, `$page => selected page` and `$limit => data limit per page`.

  Obs-2.: It is optional but there is the possibility of setting the information cache time, `new Search(3600);`, in this case 3600 seconds are equivalent to 1 hour, by default the cache is 1 day 42300 seconds.