<?php

namespace App\Jobs;
use App\SellerAccounts;
use App\OrderItem;
use App\Order;
use App\SapOrderItem;
use App\SapOrder;
use Carbon\Carbon;
use MarketplaceWebServiceOrders_Client;
use MarketplaceWebServiceOrders_Model_ListOrdersByNextTokenRequest;
use MarketplaceWebServiceOrders_Model_ListOrdersRequest;
use MarketplaceWebServiceOrders_Model_ListOrderItemsByNextTokenRequest;
use MarketplaceWebServiceOrders_Exception;
use MarketplaceWebServiceOrders_Model_ListOrderItemsRequest;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class GetOrdersForAccount implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    protected $account;
	protected $afterDate;
    protected $beforeDate;

	public function __construct($account,$afterDate='',$beforeDate='')
	{
		$this->account = $account;
		$afterDate_init = ($account->last_update_order_date)??date('Y-m-d H:i:s', strtotime('-1 day'));
        $this->afterDate = $afterDate??$afterDate_init;
		$this->beforeDate = ($beforeDate)?$beforeDate:date('Y-m-d H:i:s', strtotime('-1 hour'));
	}

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
	{
        $account = $this->account;
        if ($account) {
			$startTime=microtime(true); 
            $siteConfig = getSiteConfig();
            $this->client = new MarketplaceWebServiceOrders_Client(
                $account->mws_access_keyid,
                $account->mws_secret_key,
                'VLMWS',
                '1.0.0',
                ['ServiceURL' => $siteConfig[$account->mws_marketplaceid]['serviceUrl']."/Orders/2013-09-01",
                    'ProxyHost' => null,
                    'ProxyPort' => -1,
                    'MaxErrorRetry' => 3]

            );
            $notEnd = false;
			$nextToken = $errorMessage = null;
			
			$lastOrderUpdateDate = Carbon::parse($account->last_update_order_date??$this->afterDate)->toDateTimeString();
			$siteLocalTimeDiff['Amazon.com']= -7*3600;
			$siteLocalTimeDiff['Amazon.co.jp']= 9*3600;
			$siteLocalTimeDiff['Amazon.ca']= -7*3600;
			$siteLocalTimeDiff['Amazon.com.mx']= -7*3600;
			$siteLocalTimeDiff['Amazon.co.uk']= 1*3600;
			$siteLocalTimeDiff['Amazon.de']= 2*3600;
			$siteLocalTimeDiff['Amazon.es']= 2*3600;
			$siteLocalTimeDiff['Amazon.fr']= 2*3600;
			$siteLocalTimeDiff['Amazon.it']= 2*3600;
			$siteLocalTimeDiff['Amazon.nl']= 2*3600;
			$siteLocalTimeDiff['Amazon.se']= 2*3600;
            do {
                if ($nextToken) {
                    $request = new MarketplaceWebServiceOrders_Model_ListOrdersByNextTokenRequest();
                    $request->setNextToken($nextToken);
                    $resultName = 'ListOrdersByNextTokenResult';
                } else {
                    $request = new MarketplaceWebServiceOrders_Model_ListOrdersRequest();
                    $request->setMarketplaceId($account->mws_marketplaceid);
                    $request->setLastUpdatedAfter(date('c',strtotime($this->afterDate)));
					$request->setLastUpdatedBefore(date('c',strtotime($this->beforeDate)));
                    //$request->setOrderStatus(getOrderStatus());
                    $request->setMaxResultsPerPage(100);
                    $resultName = 'ListOrdersResult';
                }
                $request->setSellerId($account->mws_seller_id);
                $request->setMWSAuthToken($account->mws_auth_token);

                try {
                    $response = $nextToken?$this->client->listOrdersByNextToken($request):$this->client->listOrders($request);
                    $objResponse = processResponse($response);
                    $resultResponse = $objResponse->{$resultName};
                    $nextToken = isset($resultResponse->NextToken)?$resultResponse->NextToken:null;
                    $lastOrders = isset($resultResponse->Orders->Order)?$resultResponse->Orders->Order:[];
                    $notEnd = !empty($nextToken);
                    $insertData=array();
                    foreach($lastOrders as $order)
                    {
                        $arrayOrder = json_decode(json_encode($order), true);
                        $purchaseDate=Carbon::parse(array_get($arrayOrder,'PurchaseDate',''))->toDateTimeString();
                        $lastUpdateDate=Carbon::parse(array_get($arrayOrder,'LastUpdateDate',''))->toDateTimeString();
                        if($lastOrderUpdateDate < $lastUpdateDate) $lastOrderUpdateDate = $lastUpdateDate;
						unset($insertArray);
						$insertArray['user_id'] = $account->user_id;
                        $insertArray['seller_account_id'] = (string) $account->id;
						$insertArray['amazon_order_id'] = array_get($arrayOrder,'AmazonOrderId','');
						$insertArray['seller_order_id'] = array_get($arrayOrder,'SellerOrderId','');
						$insertArray['order_status'] = array_get($arrayOrder,'OrderStatus','');
						$insertArray['buyer_email'] = array_get($arrayOrder,'BuyerEmail','');
						$insertArray['buyer_name'] = array_get($arrayOrder,'BuyerName','');
						$insertArray['purchase_date'] = $purchaseDate;
						$insertArray['fulfillment_channel'] = array_get($arrayOrder,'FulfillmentChannel','');
						$insertArray['last_update_date'] = $lastUpdateDate;						
						$insertArray['sales_channel'] = array_get($arrayOrder,'SalesChannel','');
						$insertArray['purchase_local_date'] = date('Y-m-d H:i:s',strtotime($purchaseDate) + array_get($siteLocalTimeDiff,$insertArray['sales_channel'],0));
						$insertArray['order_channel'] = array_get($arrayOrder,'OrderChannel','');
						$insertArray['ship_service_level'] = array_get($arrayOrder,'ShipServiceLevel','');
						$insertArray['name'] = array_get($arrayOrder,'ShippingAddress.Name','');
						$insertArray['address_line1'] = array_get($arrayOrder,'ShippingAddress.AddressLine1','');						
						$insertArray['address_line2'] = array_get($arrayOrder,'ShippingAddress.AddressLine2','');
						$insertArray['address_line3'] = array_get($arrayOrder,'ShippingAddress.AddressLine3','');
						$insertArray['city'] = array_get($arrayOrder,'ShippingAddress.City','');
						$insertArray['county'] = array_get($arrayOrder,'ShippingAddress.County','');
						$insertArray['district'] = array_get($arrayOrder,'ShippingAddress.District','');						
						$insertArray['state_or_region'] = array_get($arrayOrder,'ShippingAddress.StateOrRegion','');
						$insertArray['postal_code'] = array_get($arrayOrder,'ShippingAddress.PostalCode','');
						$insertArray['country_code'] = array_get($arrayOrder,'ShippingAddress.CountryCode','');
						$insertArray['phone'] = array_get($arrayOrder,'ShippingAddress.Phone','');
						$insertArray['amount'] = round(array_get($arrayOrder,'OrderTotal.Amount',0),2);						
						$insertArray['currency_code'] = array_get($arrayOrder,'OrderTotal.CurrencyCode','');
						$insertArray['number_of_items_shipped'] = intval(array_get($arrayOrder,'NumberOfItemsShipped',0));
						$insertArray['number_of_items_unshipped'] = intval(array_get($arrayOrder,'NumberOfItemsUnshipped',0));
						$insertArray['payment_method'] = array_get($arrayOrder,'PaymentMethod','');
						$insertArray['ship_service_level_category'] = array_get($arrayOrder,'ShipServiceLevelCategory','');						
						$insertArray['earliest_ship_date'] = array_get($arrayOrder,'EarliestShipDate','');
						$insertArray['latest_ship_date'] = array_get($arrayOrder,'LatestShipDate','');
						$insertArray['earliest_delivery_date'] = array_get($arrayOrder,'EarliestDeliveryDate','');
						$insertArray['latest_delivery_date'] = array_get($arrayOrder,'LatestDeliveryDate','');
						$insertArray['order_type'] = array_get($arrayOrder,'OrderType','');           
                        $insertArray['created_at'] = $insertArray['updated_at'] = Carbon::now()->toDateTimeString();
                        $insertArray['vop_flag']=0;
                        $insertData[] = $insertArray;
                    }
                    //if (count($insertData)>50 || !$notEnd)
                    //{
                    if($insertData) Order::insertOnDuplicateWithDeadlockCatching($insertData,['updated_at','seller_order_id','order_status','buyer_email','buyer_name','purchase_date','purchase_local_date','fulfillment_channel','last_update_date','sales_channel','order_channel','ship_service_level','name','address_line1','address_line2','address_line3','city','county','district','state_or_region','postal_code','country_code','phone','amount','currency_code','number_of_items_shipped','number_of_items_unshipped','payment_method','ship_service_level_category','earliest_ship_date','latest_ship_date','earliest_delivery_date','latest_delivery_date','order_type','asins','seller_skus','vop_flag']);
                    $account->last_update_order_date  = $lastOrderUpdateDate;								
                    $endTime=microtime(true);
                    
                    //if($endTime-$startTime>60*60) $notEnd=false;
                    //}
                } catch (MarketplaceWebServiceOrders_Exception $ex) {
					$errorMessage = $ex->getMessage();
					$notEnd = false;
                }
                sleep(60);
			} while ($notEnd);
			$account->get_lists = NULL;
			$account->last_action_result  = $errorMessage??('Success '.Carbon::now()->toDateTimeString());
			$account->save();
        }
	}
}
