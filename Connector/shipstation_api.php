<?php

namespace kulikov_dev\connectors\shipstation;

/**
 * Class shipstation_api for processing ShipStation integration logic
 * @package kulikov_dev\connectors\shipstation
 */
class shipstation_api
{
    /**
     * @var shipstation_connector Connector to ShipStation
     */
    private static $connector;

    /** Create new order info in ShipStation
     * https://www.shipstation.com/docs/api/orders/create-update-order/
     * @param array $shipstation_order Order info in ShipStation format
     * @return string/null Shipstation record ID
     */
    public static function set_order($shipstation_order)
    {
        shipstation_api::check_connection();

        if (empty($shipstation_order)) {
            return null;
        }

        $call_url = 'orders/createorder';
        $records = shipstation_api::$connector->upload_record($call_url, $shipstation_order);

        if ($records == null || empty($records) || isset($records[0]->ExceptionMessage)) {
            $error_message = !empty($records) && isset($records[0]->ExceptionMessage) ? $records[0]->ExceptionMessage : "unknown";
            print('Error occurred during order creating in ShipStation: ' . $error_message);
            return null;
        }

        return $records[0]->orderId;
    }

    /** On getting ShipStation webhook of order shipped
     *  https://www.shipstation.com/docs/api/shipments/list/
     * @param string $call_url webhook resource_url
     * "resource_url":"https://ssapiX.shipstation.com/shipments?storeID=123456&batchId=12345678&includeShipmentItems=False"
     */
    public static function on_shipstation_order_webhook($call_url, $is_item_hook)
    {
        shipstation_api::check_connection();
        $search_url = preg_replace('/^.*\/\s*/', '', $call_url);
        $records = shipstation_api::$connector->upload_record($search_url, null);

        if ($records == null || empty($records) || isset($records[0]->ExceptionMessage)) {
            $message = 'Error occurred during order webhook processing in ShipStation: ' . $records[0]->ExceptionMessage;
            print($message);
            return;
        }

        foreach ($records[0]->shipments as $shipment_info) {
            $order_info = [];       // TODO get your CMS order and label info by $shipment_info->orderNumber
            $label_info = [];
            if (empty($order_info)) {
                continue;
            }

            shipstation_data_converter::set_shipment_to_cms_order($order_info, $label_info, $shipment_info, $is_item_hook);
        }
    }

    /** Check if webhooks has already assigned to ShipStation
     * @return bool Webhooks were assign
     */
    private static function has_webhooks()
    {
        $call_url = 'webhooks';
        $records = shipstation_api::$connector->upload_record($call_url, null);
        return !empty($records[0]->webhooks);
    }

    /**
     * Create webhook links in ShipStation
     */
    private static function subscribe_to_webhooks()
    {
        $homepage = '';         // TODO Link to your CMS homepage
        $web_hook_url = $homepage.'shipstation_webhook.php';

        // https://www.shipstation.com/docs/api/webhooks/subscribe/
        $result = [];
        $result["target_url"] = $web_hook_url;                        // The URL to send the webhooks to
        $result["event"] = "SHIP_NOTIFY";                                       // The type of webhook to subscribe to. Must contain one of the following values: ORDER_NOTIFY, ITEM_ORDER_NOTIFY, SHIP_NOTIFY, ITEM_SHIP_NOTIFY
        $result["friendly_name"] = "CMS order shipped shipped notifier";    // Display name for the webhook
        $call_url = 'webhooks/subscribe';
        $records = shipstation_api::$connector->upload_record($call_url, $result);
        if (isset($records[0]->ExceptionMessage)) {
            print('Error occurred during webhook subscribing in ShipStation: ' . $records[0]->ExceptionMessage);
        }

        $result = [];
        $result["target_url"] = $web_hook_url;                        // The URL to send the webhooks to
        $result["event"] = "ITEM_SHIP_NOTIFY";                                  // The type of webhook to subscribe to. Must contain one of the following values: ORDER_NOTIFY, ITEM_ORDER_NOTIFY, SHIP_NOTIFY, ITEM_SHIP_NOTIFY
        $result["friendly_name"] = "CMS order item shipped notifier";       // Display name for the webhook
        $call_url = 'webhooks/subscribe';
        $records = shipstation_api::$connector->upload_record($call_url, $result);
        if (isset($records[0]->ExceptionMessage)) {
            print('Error occurred during webhook subscribing in ShipStation: ' . $records[0]->ExceptionMessage);
        }
    }

    /**
     * Check connection to ShipStation and update it if necessary
     */
    private static function check_connection()
    {
        if (shipstation_api::$connector == null || !shipstation_api::$connector->is_opened()) {
            shipstation_api::initialize_connection();
        }
    }

    /**
     * Initialize connection to ShipStation
     */
    private static function initialize_connection()
    {
        shipstation_api::$connector = new shipstation_connector();
        shipstation_api::$connector->open_connection();

        if (shipstation_api::$connector == null || !shipstation_api::$connector->is_opened()) {
            die('Initialize ShipStation connection before work with it.');
        }

        if (!shipstation_api::has_webhooks()) {
            shipstation_api::subscribe_to_webhooks();
        }
    }
}
