<?php

namespace kulikov_dev\connectors\shipstation;

/**
 * Class shipstation_data_converter required to process data from your CMS data object to ShipStatoin and vice versa.
 * (!) Need to adapt each CMS arrays for specific data model
 * @package kulikov_dev\connectors\shipstation
 */
class shipstation_data_converter
{
    /** Convert CMS order array to ShipStation order array
     * @param array $cms_order_info CMS order array
     * @return array ShipStation order info
     */
    public static function convert_order_to_shipstation($cms_order_info)
    {
        $shipstation_result = [];

        $zipcode = str_pad($cms_order_info["SHIPZIPCODE"], 5, "0", STR_PAD_LEFT);
        $shipstation_result["orderNumber"] = $cms_order_info["ID"];                                                 // A user-defined order number used to identify an order (ADRECOM order id). Required
        $shipstation_result["orderDate"] = $cms_order_info["CREATION_TIME"];                                        // The date the order was placed. Required
        $shipstation_result["orderStatus"] = "awaiting_shipment";                                               // The order's status. Possible values: awaiting_payment, awaiting_shipment, shipped, on_hold, cancelled. Required
        $shipstation_result["customerEmail"] = $cms_order_info["EMAIL"];                                           // The customer's email address. Optional

        $shipstation_result["shipTo"]["name"] = $cms_order_info["SHIPFNAME"] . ' ' . $cms_order_info["SHIPLNAME"];      // The recipient's shipping address. Required
        $shipstation_result["shipTo"]["company"] = $cms_order_info["SHIPCOMPANY"];
        $shipstation_result["shipTo"]["street1"] = $cms_order_info["SHIPADDRESS"];
        $shipstation_result["shipTo"]["city"] = $cms_order_info["SHIPCITY"];
        $shipstation_result["shipTo"]["state"] = $cms_order_info["SHIPSTATE"];
        $shipstation_result["shipTo"]["postalCode"] = $zipcode;
        $shipstation_result["shipTo"]["country"] = $cms_order_info['SHIPCOUNTRY']; // The two-character ISO country code is required.
        $shipstation_result["shipTo"]["phone"] = $cms_order_info["SHIPPHONE"];

        $shipstation_result["billTo"]["name"] = $cms_order_info["FNAME"] . ' ' . $cms_order_info["LNAME"];              // The recipients billing address. Required
        $shipstation_result["billTo"]["company"] = $cms_order_info["COMPANY"];
        $shipstation_result["billTo"]["street1"] = $cms_order_info["ADDRESS"];
        $shipstation_result["billTo"]["city"] = $cms_order_info["CITY"];
        $shipstation_result["billTo"]["state"] = $cms_order_info["STATE"];
        $shipstation_result["billTo"]["postalCode"] = $zipcode;
        $shipstation_result["billTo"]["country"] = $cms_order_info['COUNTRY'];
        $shipstation_result["billTo"]["phone"] = $cms_order_info["PHONE"];

        $shipstation_result["amountPaid"] = $cms_order_info["TOTAL"];                                               // The total amount paid for the Order. Optional
        $shipstation_result["shippingAmount"] = $cms_order_info["SHIPPING"];                                        // Shipping amount paid by the customer, if any. Optional
        $shipstation_result["taxAmount"] = $cms_order_info["TAX"];                                                  // The total tax amount for the Order. Optional

        $items = [];
        foreach ($cms_order_info["LINEITEMS"] as $line_item) {
            array_push($items, [
                "lineItemKey" => $line_item["ID"],                                                  // An identifier for the OrderItem in the originating system (ADRECOM line item id).
                "sku" => $line_item["SKU"],                                                 // The SKU (stock keeping unit) identifier for the product associated with this line item.
                "name" => $line_item["NAME"] ?: "",                                           // The name of the product associated with this line item. Cannot be null
                "quantity" => $line_item["QUANTITY"],                                               // The quantity of product ordered.
                "unitPrice" => $line_item["ITEM_PRICE"]                                             // The sell price of a single item specified by the order source.
            ]);
        }

        $shipstation_result["items"] = $items;
        return $shipstation_result;
    }

    public static function set_shipment_to_cms_order($cms_order_info, $shipstation_webhook_info, $is_item_hook)
    {
        $cms_order_info["DELIVERY_STATUS"] = 2;
        $cms_order_info["TRACKING_NUMBER"] = $shipstation_webhook_info->trackingNumber;

        if (stripos($shipstation_webhook_info->carrierCode, "ups") !== false || stripos($shipstation_webhook_info->serviceCode, "ups") !== false) {
            $cms_order_info["PROVIDER"] = "UPS";
        } elseif (stripos($shipstation_webhook_info->carrierCode, "usps") !== false || stripos($shipstation_webhook_info->serviceCode, "usps") !== false) {
            $cms_order_info["PROVIDER"] = "USPS";
        } elseif (stripos($shipstation_webhook_info->carrierCode, "fedex") !== false || stripos($shipstation_webhook_info->serviceCode, "fedex") !== false) {
            $cms_order_info["PROVIDER"] = "FEDEX";
        } else {
            $cms_order_info["PROVIDER"] = "CUSTOM";
        }

        $cms_order_info["TRACKING_NUMBER"] = $shipstation_webhook_info->trackingNumber;
        $cms_order_info["ORDER_ID"] = $shipstation_webhook_info->orderNumber;
        if (!empty($shipstation_webhook_info->weight)) {
            if ($shipstation_webhook_info->weight->units == 'ounces') {
                $cms_order_info["WEIGHT_OZ"] = $shipstation_webhook_info->weight->value ?: 0;
            } elseif ($shipstation_webhook_info->weight->units == 'pounds') {
                $cms_order_info["WEIGHT_LB"] = $shipstation_webhook_info->weight->value ?: 0;
            } else {
                // Gram's
                $cms_order_info["WEIGHT_OZ"] = ($shipstation_webhook_info->weight->value ?: 0) / 28.35;
            }
        }

        $cms_order_info["LENGTH"] = empty($shipstation_webhook_info->dimensions) ? 0 : $shipstation_webhook_info->dimensions->length ?: 0;
        $cms_order_info["WIDTH"] = empty($shipstation_webhook_info->dimensions) ? 0 : $shipstation_webhook_info->dimensions->width ?: 0;
        $cms_order_info["METHOD_NAME"] = $shipstation_webhook_info->serviceCode;
        $cms_order_info["PACKAGE"] = $shipstation_webhook_info->packageCode;
        $cms_order_info["PRICE"] = $shipstation_webhook_info->shipmentCost ?: 0;
        $cms_order_info["THE_TIME"] = $shipstation_webhook_info->shipDate;
        $cms_order_info["STATUS"] = 'SHIPPED';

        if ($is_item_hook) {
            foreach ($shipstation_webhook_info->shipmentItems as $shipment_item) {
                if (empty($shipment_item->lineItemKey)) {
                    continue;
                }

                foreach ($cms_order_info["LINEITEMS"] as $line_item) {
                    if ($line_item->id != $shipstation_webhook_info->lineItemKey){
                        continue;
                    }

                    $line_item["ORDER_ID"] = $shipstation_webhook_info->orderNumber;
                    $line_item["ORDERED_ITEM_ID"] = $shipment_item->lineItemKey;
                    $line_item["THE_TIME"] = $shipstation_webhook_info->shipDate;
                    break;
                }
            }
        }

        return $cms_order_info;
    }
}
