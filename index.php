<?php

use XLR8\Exception\XLR8Exception;
use XLR8\Search;

require implode(DIRECTORY_SEPARATOR, [__DIR__, "vendor", "autoload.php"]);

try {
    Search::getNearbyHotels(41.157944, -8.629105);
} catch (XLR8Exception $e) {
    echo $e->getMessage();
}
