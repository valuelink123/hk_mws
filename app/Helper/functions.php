<?php

function getSiteCode(){
     return array(
         'United States' =>'ATVPDKIKX0DER',
         'Canada' =>'A2EUQ1WTGCTBG2',
         'Mexico' =>'A1AM78C64UM0Y8',
         'United Kingdom' =>'A1F83G8C2ARO7P',
         'Germany' =>'A1PA6795UKMFR9',
         'France' =>'A13V1IB3VIYZZH',
         'Italy' =>'APJ6JRA9NG5V4',
         'Spain' =>'A1RKKUPIHCS9HS',
         'NL' =>'A1805IZSGTT6HS',
         'SE' =>'A2NODRKZP88ZB9',
         'Japan' =>'A1VC38T7YXB528'
     );
}

function getExRetry(\Exception $ex){
     if($ex->getStatusCode() == 503 || $ex->getMessage() == 'Empty reply from server' || substr($ex->getMessage(),20) == 'Failed to connect to'){
        return true;
     }else{
        return false;
     }
}

function getSkuStatuses(){
    return array(
        '0'=>'��̭',
        '1'=>'����',
        '2'=>'��Ʒ',
        '3'=>'���',
        '4'=>'�滻',
        '5'=>'����',
        '6'=>'ͣ��',
        '99'=>'��Ʒ�滮'
    );    
}

function getAreaMarketplaceids(){
	return  array(
		'EU'=>array('A1F83G8C2ARO7P','A1PA6795UKMFR9','A13V1IB3VIYZZH','APJ6JRA9NG5V4','A1RKKUPIHCS9HS','A1805IZSGTT6HS','A2NODRKZP88ZB9'),
		'US'=>array('ATVPDKIKX0DER'),
		'JP'=>array('A1VC38T7YXB528')
	);
}
function getSiteConfig(){

    $configUS=array(
        'serviceUrl'=>'https://mws.amazonservices.com'
    );
    $configEU=array(
        'serviceUrl'=>'https://mws-eu.amazonservices.com'
    );
    $configJP=array(
        'serviceUrl'=>'https://mws.amazonservices.jp'
    );
    return array(
        'ATVPDKIKX0DER' =>$configUS,
        'A2EUQ1WTGCTBG2' =>$configUS,
        'A1AM78C64UM0Y8' =>$configUS,
        'A1F83G8C2ARO7P' =>$configEU,
        'A1PA6795UKMFR9' =>$configEU,
        'A13V1IB3VIYZZH' =>$configEU,
        'APJ6JRA9NG5V4' =>$configEU,
        'A1RKKUPIHCS9HS' =>$configEU,
        'A1805IZSGTT6HS' =>$configEU,
        'A2NODRKZP88ZB9' =>$configEU,
        'A1VC38T7YXB528' =>$configJP
    );
}

function processResponse($response)
{
    return simplexml_load_string($response->toXML());
}


function getSiteUrl(){
    return array(
        'A2EUQ1WTGCTBG2'=>'amazon.ca',
        'A1PA6795UKMFR9'=>'amazon.de',
        'A1RKKUPIHCS9HS'=>'amazon.es',
        'A13V1IB3VIYZZH'=>'amazon.fr',
        'A21TJRUUN4KGV'=>'amazon.in',
        'APJ6JRA9NG5V4'=>'amazon.it',
        'A1805IZSGTT6HS' =>'amazon.nl',
        'A2NODRKZP88ZB9' =>'amazon.se',
        'A1VC38T7YXB528'=>'amazon.co.jp',
        'A1F83G8C2ARO7P'=>'amazon.co.uk',
        'A1AM78C64UM0Y8'=>'amazon.com.mx',
        'ATVPDKIKX0DER'=>'amazon.com'
    );
}


function getMarketNameId(){
    return array(
        'amazon.com'=>'ATVPDKIKX0DER',
        'amazon.ca'=>'A2EUQ1WTGCTBG2',
        'amazon.com.mx'=>'A1AM78C64UM0Y8',
        'amazon.co.jp'=>'A1VC38T7YXB528',
        'amazon.co.uk'=>'A1F83G8C2ARO7P',
        'amazon.in'=>'A21TJRUUN4KGV',
        'amazon.it'=>'APJ6JRA9NG5V4',
        'amazon.de'=>'A1PA6795UKMFR9',
        'amazon.fr'=>'A13V1IB3VIYZZH',
        'amazon.es'=>'A1RKKUPIHCS9HS',
        'amazon.nl'=>'A1805IZSGTT6HS',
        'amazon.se'=>'A2NODRKZP88ZB9',
        'si us prod marketplace'=>'ATVPDKIKX0DER',
        'si ca prod marketplace'=>'A2EUQ1WTGCTBG2',
        'si prod es marketplace'=>'A1RKKUPIHCS9HS',
        'si prod it marketplace'=>'APJ6JRA9NG5V4',
        'si prod de marketplace'=>'A1PA6795UKMFR9',
        'si prod fr marketplace'=>'A13V1IB3VIYZZH',
        'si uk prod marketplace'=>'A1F83G8C2ARO7P'
    );
}

function getSiteCur(){
    return array(
        'A2EUQ1WTGCTBG2'=>'CAD',
        'A1PA6795UKMFR9'=>'EUR',
        'A1RKKUPIHCS9HS'=>'EUR',
        'A13V1IB3VIYZZH'=>'EUR',
        'A21TJRUUN4KGV'=>'INR',
        'APJ6JRA9NG5V4'=>'EUR',
        'A1805IZSGTT6HS' =>'EUR',
        'A2NODRKZP88ZB9' =>'EUR',
        'A1VC38T7YXB528'=>'JPY',
        'A1F83G8C2ARO7P'=>'GBP',
        'A1AM78C64UM0Y8'=>'MXN',
        'ATVPDKIKX0DER'=>'USD'
    );
}

function getSiteCountryCode(){
    return array(
        'A2EUQ1WTGCTBG2'=>'CA',
        'A1PA6795UKMFR9'=>'DE',
        'A1RKKUPIHCS9HS'=>'ES',
        'A13V1IB3VIYZZH'=>'FR',
        'A21TJRUUN4KGV'=>'IN',
        'APJ6JRA9NG5V4'=>'IT',
        'A1805IZSGTT6HS' =>'NL',
        'A2NODRKZP88ZB9' =>'SE',
        'A1VC38T7YXB528'=>'JP',
        'A1F83G8C2ARO7P'=>'GB',
        'A1AM78C64UM0Y8'=>'MX',
        'ATVPDKIKX0DER'=>'US'
    );
}

function getMarketplaceCode(){
    return array(
        'A2EUQ1WTGCTBG2'=>array(
			'fba_factory_warehouse'=>array(
				'0'=>array('sap_factory_code'=>'CA01','sap_warehouse_code'=>'AC2')
			),
			'fbm_factory_warehouse'=>array(
				'0'=>array('sap_factory_code'=>'CA04','sap_warehouse_code'=>'GA4'),
				'1'=>array('sap_factory_code'=>'CA02','sap_warehouse_code'=>'GA1')
			),
			'site_code'=>'amazon.ca',
			'country_code'=>'CA',
			'currency_code'=>'CAD'
		),
		
		'A1PA6795UKMFR9'=>array(
			'fba_factory_warehouse'=>array(
				'0'=>array('sap_factory_code'=>'GR01','sap_warehouse_code'=>'AG2')
			),
			'fbm_factory_warehouse'=>array(
				'0'=>array('sap_factory_code'=>'GR04','sap_warehouse_code'=>'GR4'),
				'1'=>array('sap_factory_code'=>'GR02','sap_warehouse_code'=>'GR1')
			),
			'site_code'=>'amazon.de',
			'country_code'=>'DE',
			'currency_code'=>'EUR'
		),
		
		'A1RKKUPIHCS9HS'=>array(
			'fba_factory_warehouse'=>array(
				'0'=>array('sap_factory_code'=>'ES01','sap_warehouse_code'=>'AS2')
			),
			'fbm_factory_warehouse'=>array(
				'0'=>array('sap_factory_code'=>'ES02','sap_warehouse_code'=>'ES2')
			),
			'site_code'=>'amazon.es',
			'country_code'=>'ES',
			'currency_code'=>'EUR'
		),
		
		'A13V1IB3VIYZZH'=>array(
			'fba_factory_warehouse'=>array(
				'0'=>array('sap_factory_code'=>'FR01','sap_warehouse_code'=>'AF2')
			),
			'fbm_factory_warehouse'=>array(
				'0'=>array('sap_factory_code'=>'FR02','sap_warehouse_code'=>'FR1')
			),
			'site_code'=>'amazon.fr',
			'country_code'=>'FR',
			'currency_code'=>'EUR'
		),
		
		'APJ6JRA9NG5V4'=>array(
			'fba_factory_warehouse'=>array(
				'0'=>array('sap_factory_code'=>'IT01','sap_warehouse_code'=>'AI2')
			),
			'fbm_factory_warehouse'=>array(
				'0'=>array('sap_factory_code'=>'IT02','sap_warehouse_code'=>'IT2')
			),
			'site_code'=>'amazon.it',
			'country_code'=>'IT',
			'currency_code'=>'EUR'
		),
		
		'A1VC38T7YXB528'=>array(
			'fba_factory_warehouse'=>array(
				'0'=>array('sap_factory_code'=>'JP01','sap_warehouse_code'=>'AJ2')
			),
			'fbm_factory_warehouse'=>array(
				'0'=>array('sap_factory_code'=>'JP02','sap_warehouse_code'=>'CJP2')
			),
			'site_code'=>'amazon.co.jp',
			'country_code'=>'JP',
			'currency_code'=>'JPY'
		),
		
		'A1F83G8C2ARO7P'=>array(
			'fba_factory_warehouse'=>array(
				'0'=>array('sap_factory_code'=>'UK01','sap_warehouse_code'=>'AE3')
			),
			'fbm_factory_warehouse'=>array(
				'0'=>array('sap_factory_code'=>'UK04','sap_warehouse_code'=>'UK4'),
				'1'=>array('sap_factory_code'=>'UK02','sap_warehouse_code'=>'UK2')
			),
			'site_code'=>'amazon.co.uk',
			'country_code'=>'GB',
			'currency_code'=>'GBP'
		),
		
		'ATVPDKIKX0DER'=>array(
			'fba_factory_warehouse'=>array(
				'0'=>array('sap_factory_code'=>'US01','sap_warehouse_code'=>'AA1')
			),
			'fbm_factory_warehouse'=>array(
				'0'=>array('sap_factory_code'=>'US02','sap_warehouse_code'=>'US2'),
				'1'=>array('sap_factory_code'=>'US04','sap_warehouse_code'=>'US1'),
				'2'=>array('sap_factory_code'=>'US06','sap_warehouse_code'=>'US1')
			),
			'site_code'=>'amazon.com',
			'country_code'=>'US',
			'currency_code'=>'USD'
		)
    );
}


function getReportById($client,$id, $sellerId, $auth_token) {
    ob_start();
    $fileHandle = @fopen('php://memory', 'rw+');
    $parameters = array (
        'Merchant' => $sellerId,
        'Report' => $fileHandle,
        'ReportId' => $id,
        'MWSAuthToken' => $auth_token, // Optional
    );
    $request = new \MarketplaceWebService_Model_GetReportRequest($parameters);
    $response = $client->getReport($request);
    $getReportResult = $response->getGetReportResult();
    $responseMetadata = $response->getResponseMetadata();
    rewind($fileHandle);
    $responseStr = stream_get_contents($fileHandle);
    @fclose($fileHandle);
    ob_end_clean();
    return csv_to_array($responseStr, PHP_EOL, "\t");
}


function csv_to_array($string='', $row_delimiter=PHP_EOL, $delimiter = "," , $enclosure = '"' , $escape = "\\" )
{
    $rows = array_filter(explode($row_delimiter, $string));
    $header = NULL;
    $data = array();

    foreach($rows as $row)
    {
        $row = str_getcsv ($row, $delimiter, $enclosure , $escape);

        if(!$header)
            $header = $row;
        else
            $data[] = array_combine($header, $row);
    }

    return $data;
}

function format_num($string){
    $string=trim($string);
    $d = substr($string,-3,1);
    $string = str_replace(array(',','.'),'',$string);
    if($d==',' || $d=='.'){
        $string = substr_replace($string,'.',-2,0);
    }
    return round($string,2);
}

function filterEmoji($str)
{
    $str = preg_replace_callback(
        '/./u',
        function (array $match) {
            return strlen($match[0]) >= 4 ? '' : $match[0];
        },
        $str);
    $str = str_replace(PHP_EOL, '', $str);
    return $str;
}


function getMcfOrderStatus(){
    return array(
        'RECEIVED','INVALID','PLANNING','PROCESSING','CANCELLED','COMPLETE','COMPLETE_PARTIALLED','UNFULFILLABLE'
    );
}

function getOrderStatus(){
    return array(
        'Shipped','Unshipped','PartiallyShipped'
    );
}

function getShipmentStatus(){
    return array(
        'WORKING','SHIPPED','IN_TRANSIT','DELIVERED','CHECKED_IN','RECEIVING','CLOSED','CANCELLED','DELETED','ERROR'
    );
}



function getAccountId(\Illuminate\Database\Eloquent\Model $model,$user_id=0)
{
    $accounts = $model::where('user_id',$user_id)->whereNull('deleted_at')->get()->toArray();
    $account_id_arr=$account_arr=[];
    foreach($accounts as $account){
        $account_arr[$account['id']] = $account['label'];
        $account_id_arr[] = $account['id'];
    }
    $return=['account_arr' =>  $account_arr,'account_id_arr' =>  $account_id_arr];
    return $return;
}

function time_diff($timestamp1, $timestamp2)
{
    if ($timestamp2 <= $timestamp1)
    {
        return 'TimeOut';
    }
    $timediff = $timestamp2 - $timestamp1;
    $days = intval($timediff/86400);
    if( $days>1 ) return $days.' Days Left';
    if( $days>0 ) return $days.' Day Left';
    $remain = $timediff%86400;
    $hours = intval($remain/3600);
    if( $hours>1 ) return $hours.' Hours Left';
    if( $hours>0 ) return $hours.' Hour Left';
    $remain = $timediff%3600;
    $mins = intval($remain/60);
    if( $mins>1 ) return $mins.' Mins Left';
    if( $mins>0 ) return $mins.' Min Left';
    $secs = $remain%60;
    if( $secs>0 ) return $secs.' Secs Left';
    return 'TimeOut';
}

function curl_request($url,$post='',$timeout=30,$cookie='', $returnCookie=0){
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($curl, CURLOPT_AUTOREFERER, 1);
        if($post) {
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($post));
        }
        if($cookie) {
            curl_setopt($curl, CURLOPT_COOKIE, $cookie);
        }
        curl_setopt($curl, CURLOPT_HEADER, $returnCookie);
        curl_setopt($curl, CURLOPT_TIMEOUT, intval($timeout));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $data = curl_exec($curl);
        if (curl_errno($curl)) {
            return curl_error($curl);
        }
        curl_close($curl);
        if($returnCookie){
            list($header, $body) = explode("\r\n\r\n", $data, 2);
            preg_match_all("/Set\-Cookie:([^;]*);/", $header, $matches);
            $info['cookie']  = substr($matches[1][0], 1);
            $info['content'] = $body;
            return $info;
        }else{
            return $data;
        }
}

function siteToMarketplaceid(){
    return array(
             'amazon.com' =>'ATVPDKIKX0DER',
             'www.amazon.com' =>'ATVPDKIKX0DER',
             'www.amazon.ca' =>'A2EUQ1WTGCTBG2',
             'amazon.ca' =>'A2EUQ1WTGCTBG2',
             'www.amazon.com.mx' =>'A1AM78C64UM0Y8',
             'amazon.com.mx' =>'A1AM78C64UM0Y8',
             'www.amazon.co.uk' =>'A1F83G8C2ARO7P',
             'www.amazon.uk' =>'A1F83G8C2ARO7P',
             'amazon.co.uk' =>'A1F83G8C2ARO7P',
             'amazon.uk' =>'A1F83G8C2ARO7P',
             'amazon.de' =>'A1PA6795UKMFR9',
             'www.amazon.de' =>'A1PA6795UKMFR9',
             'amazon.fr' =>'A13V1IB3VIYZZH',
             'www.amazon.fr' =>'A13V1IB3VIYZZH',
             'www.amazon.it' =>'APJ6JRA9NG5V4',
             'amazon.it' =>'APJ6JRA9NG5V4',
             'www.amazon.es' =>'A1RKKUPIHCS9HS',
             'amazon.es' =>'A1RKKUPIHCS9HS',
             'www.amazon.co.jp' =>'A1VC38T7YXB528',
             'www.amazon.jp' =>'A1VC38T7YXB528',
             'amazon.co.jp' =>'A1VC38T7YXB528',
             'amazon.jp' =>'A1VC38T7YXB528',
             'amazon.nl'=>'A1805IZSGTT6HS',
             'amazon.se'=>'A2NODRKZP88ZB9',
             'si us prod marketplace'=>'ATVPDKIKX0DER',
             'si ca prod marketplace'=>'A2EUQ1WTGCTBG2',
             'si uk prod marketplace'=>'A1F83G8C2ARO7P',
             'si prod es marketplace'=>'A1RKKUPIHCS9HS',
             'si prod it marketplace'=>'APJ6JRA9NG5V4',
             'si prod de marketplace'=>'A1PA6795UKMFR9',
             'si prod fr marketplace'=>'A13V1IB3VIYZZH',
			 'US'=>'ATVPDKIKX0DER',
             'CA'=>'A2EUQ1WTGCTBG2',
             'MX'=>'A1AM78C64UM0Y8',
			 'JP'=>'A1VC38T7YXB528',
             'GB'=>'A1F83G8C2ARO7P',
             'ES'=>'A1RKKUPIHCS9HS',
             'IT'=>'APJ6JRA9NG5V4',
             'DE'=>'A1PA6795UKMFR9',
             'NL'=>'A1805IZSGTT6HS',
             'SE'=>'A2NODRKZP88ZB9',
             'FR'=>'A13V1IB3VIYZZH',
			 '1007'=>'ATVPDKIKX0DER',
             '1008'=>'A2EUQ1WTGCTBG2',
			 '1014'=>'A1VC38T7YXB528',
             '1013'=>'A1F83G8C2ARO7P',
             '1012'=>'A1RKKUPIHCS9HS',
             '1011'=>'APJ6JRA9NG5V4',
			 '1009'=>'A1PA6795UKMFR9',
             '1010'=>'A13V1IB3VIYZZH',
             '1015'=>'A1AM78C64UM0Y8'
         );
}



function add($num1, $num2, $scale = 9)
{
    return bcadd($num1, $num2, $scale);
}

function sub($num1, $num2, $scale = 9)
{
    return bcsub($num1, $num2, $scale);
}

function mul($num1, $num2, $scale = 9)
{
    return bcmul($num1, $num2, $scale);
}
function div($num1, $num2, $scale = 9)
{
    return bcdiv($num1, $num2, $scale);
}

function array_avg(array $nums)
{
    return div(array_sum($nums), count($nums));
}

function slope(array $y_ls, array $x_ls)
{
    $avg_y = array_avg($y_ls);
    $avg_x = array_avg($x_ls);
    $E_top = 0;
    foreach ($x_ls as $i => $x) {
        $E_top = add($E_top, mul(sub($x, $avg_x), sub($y_ls[$i], $avg_y)));
    }
    $E_bottom = 0;
    foreach ($x_ls as $i => $x) {
        $tmp = sub($x, $avg_x);
        $E_bottom = add($E_bottom, mul($tmp, $tmp));
    }
    if ($E_bottom == 0) {
        return null;
    }
    return div($E_top, $E_bottom);
}

function intercept(array $y_ls, array $x_ls)
{
    $avg_y = array_avg($y_ls);
    $avg_x = array_avg($x_ls);
    $rate = slope($y_ls, $x_ls);
    return sub($avg_y, mul($rate, $avg_x), 7);
}