<?php

namespace kulikov_dev\connectors\shipstation;

use Exception;

/**
 * Class shipstation_connector low-level API for Shipstation
 * @package kulikov-dev\connectors\shipstation
 */
class shipstation_connector
{
    /**
     * @var string Authentication info
     * Appears after open connection
     */
    private $basic_auth;

    /**
     * @var string Entry point
     */
    private $entry_point;

    /**
     * @return bool Check if connected to ShipStation
     */
    public function is_opened()
    {
        return !empty($this->basic_auth);
    }

    /**
     * Open connection to ShipStation
     */
    public function open_connection()
    {
        // TODO: Load key, password and entry point from your config.
        $key = "";
        $password = "";
        $this->entry_point = "";

        if ($this->key == null || $this->entry_point == null) {
            view('Initialize credentials and API entry point first.');
            return;
        }

        try {
            $this->basic_auth = base64_encode($this->key . ':' . $this->secret);
        } catch (Exception $exception) {
            view('Fatal Error during ShipStation connection: ' . $exception->getMessage());
        }
    }

    /** Create or update record in ShipStation database
     * @param string $search_url . Base URL
     * @param array $query . Query info to get records
     * @return array|mixed Response
     */
    public function upload_record($search_url, $query)
    {
        if (empty($this->basic_auth)) {
            view('Open ShipStation connection before trying to find records.');
            return [];
        }

        try {
            $json_query = $query == null ? null : json_encode($query);
            $header = [
                'Content-Type: application/json',
                'Authorization: Basic ' . $this->basic_auth,
                'Accept: application/json',
                'Content-Length: ' . strlen($json_query)
            ];

            $api_url = $this->entry_point . '/' . $search_url;
            $respond_json = $this->send_request($api_url, $header, $json_query);
            if (!isset($respond_json) || $respond_json == null) {
                print('Fatal error during work with ShipStation: ' . $respond_json);
                return [];
            }

            $response = json_decode($respond_json);
            if (empty($response) || !is_object($response)) {
                print('Fatal error during work with ShipStation: ' . $respond_json);
                return [];
            }

            if (is_array($response)) {
                return $response;
            }

            return [$response];
        } catch (Exception $exception) {
            print($exception->getMessage());
            view('Fatal error during uploading record to ShipStation database: ' . $exception->getMessage());
        }

        return [];
    }

    /** Send request to the website
     * @param string $url . Url
     * @param array $header_params . Header params
     * @param string $post_fields . Request data
     * @return mixed|string. Request result
     */
    private function send_request($url, $header_params, $post_fields = '')
    {
        $command = curl_init();
        try {
            curl_setopt($command, CURLOPT_URL, $url);
            curl_setopt($command, CURLOPT_HTTPHEADER, $header_params);
            curl_setopt($command, CURLOPT_SSL_VERIFYPEER, false);
            if (isset($post_fields) && $post_fields != '') {
                curl_setopt($command, CURLOPT_POSTFIELDS, $post_fields);
            }

            curl_setopt($command, CURLOPT_RETURNTRANSFER, true);
            $result = curl_exec($command);
            curl_close($command);
            return $result;
        } catch (Exception $exception) {
            curl_close($command);
            print($exception->getMessage());
            return '';
        }
    }
}
