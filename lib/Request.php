<?php

namespace RESO;

use RESO\Error;
use RESO\Util;

abstract class Request
{
    private static $validOutputFormats = array("json", "xml");

    /**
     * Sends request and returns output in specified format.
     *
     * @param string $request
     * @param string $output_format
     * @param string $decode_json
     *
     * @return mixed API Request response in requested data format.
     */
    public static function request($request, $output_format = "xml", $decode_json = false)
    {
        \RESO\RESO::logMessage("Sending request '".$request."' to RESO API.");

        // Get variables
        $api_request_url = \RESO\RESO::getAPIRequestUrl();
        $token = \RESO\RESO::getAccessToken();

        if(!in_array($output_format, self::$validOutputFormats)) {
            $output_format = "json";
        }

        $curl = new \RESO\HttpClient\CurlClient();

        // Build request URL
        $url = $api_request_url . $request;

        $headers = array(
            'Accept: application/json',
            'Authorization: Bearer '.$token
        );

        // Send request
        $response = $curl->request("get", $url, $headers, null, false);
        if(!$response || !is_array($response) || $response[1] != 200)
            throw new Error\Api("Could not retrieve API response. Request URL: ".$api_request_url."; Request string: ".$request."; Response: ".$response[0]);

        // Decode the JSON response to PHP array, if $decode_json == true
        $is_json = Util\Util::isJson($response[0]);
        if($is_json && $output_format == "json" && $decode_json) {
            $return = json_decode($response[0], true);
            if(!is_array($response))
                throw new Error\Api("Could not decode API response. Request URL: ".$api_request_url."; Request string: ".$request."; Response: ".$response[0]);
        } elseif($is_json && $output_format == "xml") {
            $return = Util\Util::arrayToXml(json_decode($response[0], true));
        } else {
            $return = $response[0];
        }

        return $return;
    }

    /**
     * Requests RESO API output and saves the output to file.
     *
     * @param string $file_name
     * @param string $request
     * @param string $output_format
     * @param bool $overwrite
     *
     * @return True / false output saved to file.
     */
    public static function requestToFile($file_name, $request, $output_format = "xml", $overwrite = false) {
        \RESO\RESO::logMessage("Sending request '".$request."' to RESO API and storing output to file '".$file_name."'.");

        if(!$overwrite && is_file($file_name)) {
            throw new Error\Reso("File '".$file_name."' already exists. Use variable 'overwrite' to overwrite the output file.");
        }

        if(!is_dir(dirname($file_name))) {
            throw new Error\Reso("Directory '".dir($file_name)."' does not exist.");
        }

        $output_data = self::request($request, $output_format, false);
        if(!$output_data) {
            \RESO\RESO::logMessage("Request output save to file failed - empty or erroneous data.");
            return false;
        }

        file_put_contents($file_name, $output_data);
        if(!is_file($file_name)) {
            \RESO\RESO::logMessage("Request output save to file failed - could not create output file.");
            return false;
        }

        \RESO\RESO::logMessage("Request output save to file succeeded.");
        return true;
    }

    public static function requestMetadata() {
        \RESO\RESO::logMessage("Requesting resource metadata.");
        return self::request("\$metadata");
    }
}