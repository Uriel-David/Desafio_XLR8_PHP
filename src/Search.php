<?php

namespace XLR8;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use NumberFormatter;
use XLR8\Exception\XLR8Exception;

/**
 * This class is responsible for implementing a search for hotels in the XLR8 API
 */
class Search
{
    const ITEM_PARAM_HOTEL = 0;
    const ITEM_PARAM_LAT = 1;
    const ITEM_PARAM_LOG = 2;
    const ITEM_PARAM_PRICE = 3;
    const PARAM_ORDER_BY_PROXIMITY = "proximity";
    const PARAM_ORDER_BY_PRICE_NIGHT = "pricepernight";
    const VALID_ORDER_BY = [
        self::PARAM_ORDER_BY_PROXIMITY,
        self::PARAM_ORDER_BY_PRICE_NIGHT,
    ];
    const ERROR_IS_REQUIRED = "%s is required";
    const ERROR_FORMATTER = "Formatter error";
    private static $endpoints = [
        'source_1' => 'https://xlr8-interview-files.s3.eu-west-2.amazonaws.com/source_1.json',
        'source_2' => 'https://xlr8-interview-files.s3.eu-west-2.amazonaws.com/source_2.json',
    ];
    private static $selectedEndpoint = "source_1";

    /**
     * Function that requests the necessary data according to the parameters passed to return the hotels
     * @param float|null $latitude
     * @param float|null $longitude
     * @param string|null $orderby
     * @param int|null $page
     * @param int|null $limit
     * @param bool|null $responseJson
     * @param string|null $selectSource
     * @param array|null $addSources
     * @return void
     * @throws \XLR8\Exception\XLR8Exception
     */
    public static function getNearbyHotels(
        ? float $latitude,
        ? float  $longitude,
        ? string $orderby = "proximity",
        ? int $page = 0,
        ? int $limit = 0,
        ? bool $responseJson = false,
        ? string $selectSource = null,
        ? array $addSources = null
    ) {
        $order = in_array($orderby, self::VALID_ORDER_BY) ? $orderby : self::PARAM_ORDER_BY_PROXIMITY;
        self::verifyIsNull("Latitude", $latitude);
        self::verifyIsNull("Longitude", $longitude);

        if ($addSources) {
            self::addSources($addSources);
        }

        if ($selectSource) {
            self::selectEndPoint($selectSource);
        }

        $data = self::getDataFromSource($order);

        $dataFormated = self::addMileage($latitude, $longitude, $data);

        $dataOrderned = self::ordering($orderby, $dataFormated);

        if ($responseJson) {
            $response = ['orderby' => $orderby];
            $response = $page === 0 || $limit === 0 ? array_merge($response, ["data" => $dataOrderned]) : array_merge($response, self::pagination($page, $limit, $dataOrderned));
            return self::response($response);
        }

        return self::responseList($dataOrderned, $page, $limit);
    }

    /**
     * Function to generate pagination in the search
     * @param int|null $page
     * @param int|null $limit
     * @param array $data
     * @return array
     */
    private static function pagination(? int $page, ? int $limit, array $data) : array
    {
        $total = count($data);
        $limit = $limit ?? 20;
        $totalPages = ceil($total / $limit);
        $page = max($page, 1);
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $limit;

        if ($offset < 0) {
            $offset = 0;
        }

        return [
            'page' => $page,
            'pages' => $totalPages,
            'data' => array_slice($data, $offset, $limit),
        ];
    }

    /**
     * Function to order the information obtained according to the "proximity" or "pricepernight" parameters
     * @param string $orderby
     * @param array $data
     * @return array
     */
    private static function ordering(string $orderby, array $data) : array
    {
        if ($orderby === self::PARAM_ORDER_BY_PROXIMITY) {
            $cmp = function ($a, $b) {
                $kmA = floatval($a["km"]);
                $kmB = floatval($b["km"]);
            
                if ($kmA === $kmB) {
                    return 0;
                }
            
                return ($kmA < $kmB) ? -1 : 1;
            };
        } else {
            $cmp = function ($a, $b) {
                $priceA = floatval($a["price"]);
                $priceB = floatval($b["price"]);
            
                if ($priceA == $priceB) {
                    return 0;
                } elseif ($priceA < $priceB) {
                    return -1;
                } else {
                    return 1;
                }
            };
        }

        usort($data, $cmp);

        return $data;
    }

    /**
     * Function to generate Hotel data in relation to coordinates
     * @param float $latitude
     * @param float $longitude
     * @param array $data
     * @return array
     */
    private static function addMileage(float $latitude, float $longitude, array $data) : array
    {
        $newData = [];

        foreach ($data as $item) {
            $km = self::getDistanceBetweenPointsNew(
                abs(floatval($latitude)),
                abs(floatval($longitude)),
                abs(floatval($item[self::ITEM_PARAM_LAT])),
                abs(floatval($item[self::ITEM_PARAM_LOG])),
                'kilometers'
            );

            $newData[] = [
                'hotel' => $item[self::ITEM_PARAM_HOTEL],
                'km' => $km,
                'price' => floatval($item[self::ITEM_PARAM_PRICE]),
            ];
        }

        return $newData;
    }

    /**
     * Function to calculate and determine the distance between locations
     * @param float $latitude1
     * @param float $longitude1
     * @param float $latitude2
     * @param float $longitude2
     * @param string $unit
     * @return float
     */
    private static function getDistanceBetweenPointsNew(float $latitude1, float $longitude1, float $latitude2, float $longitude2, string $unit = 'miles') : float
    {
        $theta = floatval($longitude1) - floatval($longitude2);
        $distance = (sin(deg2rad(floatval($latitude1))) * sin(deg2rad(floatval($latitude2)))) + (cos(deg2rad(floatval($latitude1))) * cos(deg2rad(floatval($latitude2))) * cos(deg2rad($theta)));
        $distance = acos($distance);
        $distance = rad2deg($distance);
        $distance = $distance * 60 * 1.1515;

        if ($unit == 'kilometers') {
            $distance = $distance * 1.609344;
        }

        return round($distance, 2);
    }

    /**
     * Function to request data from the given source
     * @param string|null $order
     * @return mixed
     * @throws \XLR8\Exception\XLR8Exception
     */
    private static function getDataFromSource(? string $order)
    {
        self::verifyIsNull("Order", $order);
        $result = self::request('GET', self::getEndPoint());

        if (!in_array('success', $result) || !$result['success']) {
            throw new XLR8Exception("No data found");
        } else {
            return $result['message'];
        }
    }

    /**
     * Function to perform the request on XLR8 endpoints
     * @param string $method
     * @param string $uri
     * @param array $params
     * @return mixed
     * @throws \XLR8\Exception\XLR8Exception
     */
    private static function request(string $method, string $uri, array $params = [])
    {
        try {
            $client = new Client();
            $result = $client->request($method, $uri, $params);
            $json = $result->getBody()->getContents();
            return json_decode($json, true);
        } catch (GuzzleException $e) {
            throw new XLR8Exception($e->getMessage());
        }
    }

    /**
     * Function get to return the endpoint
     * @return string
     */
    private static function getEndPoint() : string
    {
        return self::$endpoints[self::$selectedEndpoint];
    }

    /**
     * Function to retrieve hotel lists through endpoints
     * @param array|null $addSources
     * @return void
     * @throws \XLR8\Exception\XLR8Exception
     */
    private static function addSources(? array $addSources = null)
    {
        self::verifyIsNull("Sources list", $addSources);
        $validSources = [];

        foreach ($addSources as $key => $value) {
            if (is_numeric($key) || empty($value) || !filter_var($value, FILTER_VALIDATE_URL)) {
                continue;
            }

            $validSources[$key] = $value;
        }

        self::$endpoints = array_merge(self::$endpoints, $validSources);
    }

    /**
     * Function to select XLR8 endpoint
     * @param string $nameEndpoint
     * @return void
     */
    private static function selectEndPoint(string $nameEndpoint)
    {
        if (in_array($nameEndpoint, self::$endpoints)) {
            self::$selectedEndpoint = self::$endpoints[$nameEndpoint];
        }
    }

    /**
     * Function to check if the returned value is not null and thus filter it
     * @param string $label
     * @param string|null $value
     * @return void
     * @throws \XLR8\Exception\XLR8Exception
     */
    private static function verifyIsNull(string $label, string $value = null)
    {
        if (is_null($value) || empty($label)) {
            throw new XLR8Exception(sprintf(self::ERROR_IS_REQUIRED, $label));
        }
    }

    /**
     * Function to convert currencies to the desired region
     * @param float $amount
     * @param string $locale
     * @param string $currency
     * @param bool $showSymbol
     * @return string
     * @throws \XLR8\Exception\XLR8Exception
     */
    private static function currencyConvert(float $amount, string $locale = 'pt', string $currency = 'EUR', bool $showSymbol = false) : string
    {
        $fmt = new NumberFormatter($locale, NumberFormatter::CURRENCY);

        if (!$showSymbol) {
            $fmt->setSymbol(NumberFormatter::CURRENCY_SYMBOL, '');
        }

        $fmtAmount = $fmt->formatCurrency($amount, $currency);

        if (intl_is_failure($fmt->getErrorCode())) {
            throw new XLR8Exception(self::ERROR_FORMATTER);
        }

        return $fmtAmount . (!$showSymbol ? " {$currency}" : null);
    }

    /**
     * Function to generate the formatted list of the response to be sent to the client
     * @return void
     * @throws \XLR8\Exception\XLR8Exception
     */
    private static function responseList(array $data, $page = 0, $pageSize = 0, $separator = " &bull; ")
    {
        $startIndex = ($page - 1) * $pageSize;
        $pagedData = $page === 0 || $pageSize === 0 ? $data : array_slice($data, $startIndex, $pageSize);

        $formatedData = [];

        foreach ($pagedData as $item) {
            $formattedItem = sprintf("%s, %s, %s", $item['hotel'], $item['km'] . " KM", self::currencyConvert($item['price']));
            $formatedData[] = $separator . $formattedItem;
        }

        echo implode('<br/>', $formatedData);
    }

    /**
     * Function to generate a formatted response to the client as JSON
     * @return void
     */
    private static function response($data)
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data);
    }
}
