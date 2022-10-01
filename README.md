### Connector to the shipping system [ShipStation](https://www.shipstation.com/docs/api/) for PHP 5.5 >=

The connector allows to communicate with the ShipStation through the REST API, realizing operations of obtaining and updating data. Using the connector it's easy to upload new orders to the ShipStation and track shipments using [WebHooks](https://help.shipstation.com/hc/en-us/articles/360025856252-ShipStation-Webhooks#using-webhook-payloads-0-4).

There are four classes here:

<b>shipstation_connector</b> - low-level API. Allow to send queries and get a response. Before work it's necessary to setup credentials:
``` php
	 /**
     * Open connection to ShipStation
     */
    public function open_connection()
    {
        // TODO: Load key, password from your config.
        $key = "";
        $secret = "";
        $this->entry_point = "https://ssapi.shipstation.com";
```

<b>shipstation_api</b> - top-level API:
  * Create a CMS order in the ShipStation;
``` php
  public static function set_order($shipstation_order)
```
  * WebHook event from ShipStation when order/order items are shipped. Update information in CMS;
``` php
  public static function on_shipstation_order_webhook($call_url, $is_item_hook)
```
  * Create webhooks in ShipStation on first connection.
``` php
  private static function subscribe_to_webhooks()
```

<b>shipstation_data_converter</b> - used to convert data from your CMS order data object to the ShipStation and vice versa.

<b>shipstation_webhook</b> - webhook for tracking shipment information from the ShipStation.

#### Small useful tooltips:
  * Use [link](https://pipedream.com/) for webhooks debugging;
  * Easy way to test webhook labels is to create in your ShipStation personal account the [FedEx account](https://help.shipstation.com/hc/en-us/articles/360025856072-FedEx?queryID=d537e7c70ed8a1c6240b6f5f28b31294#connect-a-fedex-account-to-shipstation-0-1). It's free;