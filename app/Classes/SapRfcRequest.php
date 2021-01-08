<?php
namespace App\Classes;

/**
 * Usage:
 *
 * $sap = new SapRfcRequest($appid, $appsecret, $host);
 *
 * $data = $sap->getOrder(['orderId' => '000-000-01']);
 *
 * $data = $sap->getAccessories(['sku' => 'TM0426']);
 *
 */

/**
 * Class SapRfcRequest
 * @package App\Classes
 * @method array getOrder(array $arguments)
 * @method array getAccessories(array $arguments)
 */
class SapRfcRequest {

    private $host;
    private $appid;
    private $appsecret;

    public function __construct() {
        $this->host = env("SAP_RFC_HOST");
        $this->appid = env("SAP_RFC_ID");
        $this->appsecret = env("SAP_RFC_PWD");
    }

    public function __call($method, $arguments) {
        $arguments=$arguments[0];
        $arguments['appid'] = $this->appid;
        $arguments['method'] = $method;
        
        ksort($arguments);

        $authstr = "";
        foreach ($arguments as $k => $v) {
            $authstr.= $k;
        }


        $authstr.= $this->appsecret;

        $arguments['sign'] =  strtoupper(sha1($authstr));

        try {

            $json = curl_request($this->host,$arguments,600);

        } catch (\Exception $ex) {

            throw $ex;
        }

        $json = json_decode($json, true);
        return $json;
    }
}
