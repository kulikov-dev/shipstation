<?php

namespace kulikov_dev\connectors\shipstation;

use Exception;

$contents = file_get_contents("php://input");
if (empty($contents)) {
    exit;
}

try {
    $webhook_details = json_decode($contents);
    $call_url = $webhook_details->resource_url;
    $webhook_type = $webhook_details->resource_type;
    if ($webhook_type == 'SHIP_NOTIFY') {
        shipstation_api::on_shipstation_order_webhook($call_url, false);
    } elseif ($webhook_type == 'ITEM_SHIP_NOTIFY') {
        shipstation_api::on_shipstation_order_webhook($call_url, true);
    }

} catch (Exception $exception) {
    $error_message = $exception->getMessage();
    echo $error_message;
    die($error_message);
}

exit;
