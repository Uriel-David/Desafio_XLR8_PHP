<?php

namespace XLR8;

use XLR8\Cache;
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
    private $selectedEndpoint = "source_2";
    private $configFile;
    private $config;
    private $client;

    public function __construct(Client $client = new Client()) {
        $this->client = $client;
        $this->configFile = file_get_contents('config.json');
        $this->config = json_decode($this->configFile, true);
    }

    /**
     * Function that requests the necessary data according to the parameters passed to return the hotels
     * @param float $latitude
     * @param float $longitude
     * @param string $orderby
     * @param array|null $options
     * @return void
     * @throws \XLR8\Exception\XLR8Exception
     */
    public function getNearbyHotels(
        float $latitude,
        float  $longitude,
        string $orderby = "proximity",
        ? array $options = [
            'page' => 0,
            'limit' => 0,
            'responseJson' => false,
            'selectSource' => null
        ]
    ) {
        $this->verifyIsNull($latitude, $longitude);
        $order = $this->validateOrderBy($orderby);

        if ($options["selectSource"]) {
            $this->selectEndPoint($this->config, $options["selectSource"]);
        }

        $data = $this->getDataFromSource($order, $this->config);
        $dataFormated = $this->addMileage($latitude, $longitude, $data);
        $dataOrderned = $this->ordering($orderby, $dataFormated);

        if ($options["responseJson"]) {
            return $this->response($dataOrderned, $orderby, $options);
        }

        return $this->responseList($dataOrderned, $options["page"], $options["limit"]);
    }

    /**
     * Function to generate pagination in the search
     * @param string|null $orderby
     * @return string|null
     */
    private function validateOrderBy($orderby)
    {
        $validOrderBy = ['proximity', 'pricepernight'];
        return in_array($orderby, $validOrderBy) ? $orderby : 'proximity';
    }

    /**
     * Function to generate pagination in the search
     * @param int|null $page
     * @param int|null $limit
     * @param array $data
     * @return array
     */
    private function pagination(?int $page, ?int $limit, array $data): array
    {
        $total = count($data);
        $limit = $limit ?? 20;
        $page = max(1, min($page, ceil($total / $limit)));
        $offset = max(0, ($page - 1) * $limit);

        return [
            'page' => $page,
            'pages' => ceil($total / $limit),
            'data' => array_slice($data, $offset, $limit),
        ];
    }

    /**
     * Function to order the information obtained according to the "proximity" or "pricepernight" parameters
     * @param string $orderby
     * @param array $data
     * @return array
     */
    private function ordering(string $orderby, array $data): array
    {
        usort($data, function ($a, $b) use ($orderby) {
            if ($orderby === self::PARAM_ORDER_BY_PROXIMITY) {
                $valueA = floatval($a['km']);
                $valueB = floatval($b['km']);
            } else {
                $valueA = floatval($a['price']);
                $valueB = floatval($b['price']);
            }

            return abs($valueA) <=> abs($valueB);
        });

        return $data;
    }

    /**
     * Function to generate Hotel data in relation to coordinates
     * @param float $latitude
     * @param float $longitude
     * @param array $data
     * @return array
     */
    private function addMileage(float $latitude, float $longitude, array $data) : array
    {
        $newData = [];

        foreach ($data as $item) {
            $km = $this->getDistanceBetweenPointsNew(
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
    private function getDistanceBetweenPointsNew(float $latitude1, float $longitude1, float $latitude2, float $longitude2, string $unit = 'miles'): float
    {
        $radLatitude1 = deg2rad($latitude1);
        $radLatitude2 = deg2rad($latitude2);
        $radLongitudeDiff = deg2rad($longitude1 - $longitude2);

        $distance = acos(
            sin($radLatitude1) * sin($radLatitude2) + cos($radLatitude1) * cos($radLatitude2) * cos($radLongitudeDiff)
        );

        $distance = rad2deg($distance) * 60 * 1.1515;

        if ($unit === 'kilometers') {
            $distance *= 1.609344;
        }

        return round($distance, 2);
    }

    /**
     * Function to request data from the given source
     * @param string|null $order
     * @param array|null $config
     * @return mixed
     * @throws \XLR8\Exception\XLR8Exception
     */
    public function getDataFromSource(string $order, array $config)
    {
        $this->verifyIsNull("Order", $order);

        $cachedData = Cache::get('api_data_' . $order);
        if ($cachedData !== null) {
            return $cachedData;
        }

        $result = $this->request('GET', $this->getEndPoint($config));

        if (!in_array('success', $result) || !$result['success']) {
            throw new XLR8Exception("No data found");
        } else {
            Cache::set('api_data_' . $order, $result['message']);
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
    private function request(string $method, string $uri, array $params = [])
    {
        try {
            $result = $this->client->request($method, $uri, $params);
            $json = $result->getBody()->getContents();
            return json_decode($json, true);
        } catch (GuzzleException $e) {
            throw new XLR8Exception($e->getMessage());
        }
    }

    /**
     * Function get to return the endpoint
     * @param array $config
     * @return string
     */
    private function getEndPoint(array $config) : string
    {
        return $config[$this->selectedEndpoint];
    }

    /**
     * Function to select XLR8 endpoint
     * @param array $config
     * @param string $nameEndpoint
     * @return void
     */
    private function selectEndPoint(array $config, string $nameEndpoint)
    {
        if (in_array($nameEndpoint, $config)) {
            $this->selectedEndpoint = $config[$nameEndpoint];
        }
    }

    /**
     * Function to check if the returned value is not null and thus filter it
     * @param string $lat
     * @param string $lng
     * @return void
     * @throws \XLR8\Exception\XLR8Exception
     */
    private function verifyIsNull(string $lat = null, string $lng = null)
    {
        if (is_null($lat) || empty($lat)) {
            throw new XLR8Exception(sprintf(self::ERROR_IS_REQUIRED, "Latitude"));
        }

        if (is_null($lng) || empty($lng)) {
            throw new XLR8Exception(sprintf(self::ERROR_IS_REQUIRED, "Longitude"));
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
    private function currencyConvert(float $amount, string $locale = 'pt', string $currency = 'EUR', bool $showSymbol = false) : string
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
     * Function to generate data otimize
     * @param array $data
     * @return array
     */
    private function processLargeData(array $data)
    {
        foreach ($data as $item) {
            yield $item;
        }
    }

    /**
     * Function to generate the formatted list of the response to be sent to the client
     * @param array $data
     * @param int $page
     * @param int $pageSize
     * @param string $separator
     * @return void
     * @throws \XLR8\Exception\XLR8Exception
     */
    private function responseList(array $data, int $page = 0, int $pageSize = 0, string $separator = " &bull; ")
    {
        $startIndex = ($page - 1) * $pageSize;
        $pagedData = $page === 0 || $pageSize === 0 ? $data : array_slice($data, $startIndex, $pageSize);

        $dataOtimize = $this->processLargeData($pagedData);
        $formatedData = [];

        foreach ($dataOtimize as $item) {
            if ($item['price'] > 0) {
                $formattedItem = sprintf("%s, %s, %s", $item['hotel'], $item['km'] . " KM", $this->currencyConvert($item['price']));
                $formatedData[] = $separator . $formattedItem;
            }
        }

        echo implode('<br/>', $formatedData);
    }

    /**
     * Function to generate a formatted response to the client as JSON
     * @param array $data
     * @param string $orderby
     * @param array $options
     * @return void
     */
    private function response(array $data, string $orderby, array $options)
    {
        $response = ['orderby' => $orderby];
        $response = $options["page"] === 0 || $options["limit"] === 0 
            ? array_merge($response, ["data" => $data])
            : array_merge($response, $this->pagination($options["page"], $options["limit"], $data));
        
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($response);
    }
}
