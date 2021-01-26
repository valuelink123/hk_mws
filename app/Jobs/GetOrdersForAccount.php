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
			$insertItemData=$insertData=$sapInsertItemData=$sapInsertData=array();
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
                    $request->setMaxResultsPerPage(30);
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
                    foreach($lastOrders as $order)
                    {
						
                        $asins=$seller_skus="";
                        $arrayOrder = json_decode(json_encode($order), true);
                        $purchaseDate=Carbon::parse(array_get($arrayOrder,'PurchaseDate',''))->toDateTimeString();
                        $lastUpdateDate=Carbon::parse(array_get($arrayOrder,'LastUpdateDate',''))->toDateTimeString();
                        if($lastOrderUpdateDate < $lastUpdateDate) $lastOrderUpdateDate = $lastUpdateDate;
                        $arrayOrderItems = self::getOrderItems(array_get($arrayOrder,'AmazonOrderId',''));
						if((array_get($arrayOrder,'OrderStatus','')=='Shipped' && array_get($arrayOrder,'FulfillmentChannel','')=='AFN') || (array_get($arrayOrder,'OrderStatus','')=='Unshipped' && array_get($arrayOrder,'FulfillmentChannel','')=='MFN')){
							$importToSap = true;
						}else{
							$importToSap = false;
						}
						$my_ordersitem = false;
                        foreach($arrayOrderItems as $ordersItem){
							unset($insertItemArray);
							
							
                            $asins.=array_get($ordersItem,'ASIN').'*'.array_get($ordersItem,'QuantityOrdered').';';
                            $seller_skus.=array_get($ordersItem,'SellerSKU').'*'.array_get($ordersItem,'QuantityOrdered').';';
							
							$insertItemArray['user_id'] = $account->user_id;
                            $insertItemArray['seller_account_id'] = (string) $account->id;
							$insertItemArray['purchase_date'] = $purchaseDate;
							$insertItemArray['fulfillment_channel'] = array_get($arrayOrder,'FulfillmentChannel','');
                            $insertItemArray['amazon_order_id'] = array_get($arrayOrder,'AmazonOrderId','');
							$insertItemArray['asin'] = array_get($ordersItem,'ASIN','');
							$insertItemArray['seller_sku'] = array_get($ordersItem,'SellerSKU','');
							$insertItemArray['order_item_id'] = array_get($ordersItem,'OrderItemId','');
							$insertItemArray['title'] = array_get($ordersItem,'Title','');
							$insertItemArray['quantity_ordered'] = intval(array_get($ordersItem,'QuantityOrdered',0));
							$insertItemArray['quantity_shipped'] = intval(array_get($ordersItem,'QuantityShipped',0));
							$insertItemArray['gift_wrap_level'] = array_get($ordersItem,'GiftWrapLevel','');
							$insertItemArray['gift_message_text'] = array_get($ordersItem,'GiftMessageText','');
							$insertItemArray['item_price_amount'] = round(array_get($ordersItem,'ItemPrice.Amount',0),2);
							$insertItemArray['item_price_currency_code'] = array_get($ordersItem,'ItemPrice.CurrencyCode','');
							$insertItemArray['shipping_price_amount'] = round(array_get($ordersItem,'ShippingPrice.Amount',0),2);
							$insertItemArray['shipping_price_currency_code'] = array_get($ordersItem,'ShippingPrice.CurrencyCode','');
							$insertItemArray['gift_wrap_price_amount'] = round(array_get($ordersItem,'GiftWrapPrice.Amount',0),2);
							$insertItemArray['gift_wrap_price_currency_code'] = array_get($ordersItem,'GiftWrapPrice.CurrencyCode','');
							$insertItemArray['item_tax_amount'] = round(array_get($ordersItem,'ItemTax.Amount',0),2);
							$insertItemArray['item_tax_currency_code'] = array_get($ordersItem,'ItemTax.CurrencyCode','');
							$insertItemArray['shipping_tax_amount'] = round(array_get($ordersItem,'ShippingTax.Amount',0),2);
							$insertItemArray['shipping_tax_currency_code'] = array_get($ordersItem,'ShippingTax.CurrencyCode','');
							$insertItemArray['gift_wrap_tax_amount'] = round(array_get($ordersItem,'GiftWrapTax.Amount',0),2);
							$insertItemArray['gift_wrap_tax_currency_code'] = array_get($ordersItem,'GiftWrapTax.CurrencyCode','');
							$insertItemArray['shipping_discount_amount'] = round(array_get($ordersItem,'ShippingDiscount.Amount',0),2);
							$insertItemArray['shipping_discount_currency_code'] = array_get($ordersItem,'ShippingDiscount.CurrencyCode','');
							$insertItemArray['promotion_discount_amount'] = round(array_get($ordersItem,'PromotionDiscount.Amount',0),2);
							$insertItemArray['promotion_discount_currency_code'] = array_get($ordersItem,'PromotionDiscount.CurrencyCode','');
							$insertItemArray['promotion_ids'] = serialize(array_get($ordersItem,'PromotionIds',''));
							$insertItemArray['cod_fee_amount'] = round(array_get($ordersItem,'CODFee.Amount',0),2);
							$insertItemArray['cod_fee_currency_code'] = array_get($ordersItem,'CODFee.CurrencyCode','');
							$insertItemArray['cod_fee_discount_amount'] = round(array_get($ordersItem,'CODFeeDiscount.Amount',0),2);
							$insertItemArray['cod_fee_discount_currency_code'] = array_get($ordersItem,'CODFeeDiscount.CurrencyCode','');
                            $insertItemData[] = $insertItemArray;
							
							if($importToSap){
								if($account->mws_seller_id == 'A386LX6UH3N4GU' && in_array(array_get($ordersItem,'ASIN'),['B07JKSL8D3','B079L4TRW1','B074W6P8XG','B07JKS27KD','B07H29G5CJ','B07XDFB5CZ','B07K47LYBT','B07K4BFKYJ','B07K3Z2426','B07K3XYJX4','B07K45QG6D','B07K3V9LY9','B07K47BR5Q','B07K438DJC','B07K43ZQ34','B07K47MMYX','B07K3RVRPX','B07JMV994B','B07JM1LXXG','B07JMVKBCB','B074W6P8XG'])) continue;
								if($account->mws_seller_id == 'A3S03OCOL3LXVH' && in_array(array_get($ordersItem,'ASIN'),['B0822X2FK6','B082NMZM23','B082QW3NDN','B082QRV83W','B082QP6KCJ','B083QBJCY8','B083QBSJVY','B083QC6C62','B083QBWKK8','B083QBVS6B','B083QC4GHB','B083QBZWR5','B083QBCHZG'])) continue;
								if($account->mws_seller_id == 'A2BDHRNM8A41K8' && in_array(array_get($ordersItem,'ASIN'),['B08F25FJLQ'])) continue;
								if($account->mws_seller_id == 'A3I3SZVYDM6YOH' && in_array(array_get($ordersItem,'ASIN'),['B08FJ9X3F7'])) continue;
								if($account->mws_seller_id == 'AN5UX6HRRCZZB' && in_array(array_get($ordersItem,'ASIN'),['B08F25FJLQ'])) continue;
								if($account->mws_seller_id == 'A1P441X6YWJXLC' && in_array(array_get($ordersItem,'ASIN'),['B086V7QBJG'])) continue;
								
								$my_ordersitem = true;
								unset($sapInsertItemArray);
								$sapInsertItemArray['SellerId'] = (string) $account->mws_seller_id;
								$sapInsertItemArray['MarketPlaceId'] = (string) $account->mws_marketplaceid;
								$sapInsertItemArray['AmazonOrderId'] = array_get($arrayOrder,'AmazonOrderId','');
								$sapInsertItemArray['ASIN'] = array_get($ordersItem,'ASIN','');
								$sapInsertItemArray['SellerSKU'] = array_get($ordersItem,'SellerSKU','');
								$sapInsertItemArray['OrderItemId'] = array_get($ordersItem,'OrderItemId','');
								$sapInsertItemArray['Title'] = array_get($ordersItem,'Title','');
								$sapInsertItemArray['QuantityOrdered'] = intval(array_get($ordersItem,'QuantityOrdered',0));
								$sapInsertItemArray['QuantityShipped'] = intval(array_get($ordersItem,'QuantityShipped',0));
								$sapInsertItemArray['GiftWrapLevel'] = array_get($ordersItem,'GiftWrapLevel','');
								$sapInsertItemArray['GiftMessageText'] = array_get($ordersItem,'GiftMessageText','');
								$sapInsertItemArray['ItemPriceAmount'] = round(array_get($ordersItem,'ItemPrice.Amount',0),2);
								$sapInsertItemArray['ItemPriceCurrencyCode'] = array_get($ordersItem,'ItemPrice.CurrencyCode','');
								$sapInsertItemArray['ShippingPriceAmount'] = round(array_get($ordersItem,'ShippingPrice.Amount',0),2);
								$sapInsertItemArray['ShippingPriceCurrencyCode'] = array_get($ordersItem,'ShippingPrice.CurrencyCode','');
								$sapInsertItemArray['GiftWrapPriceAmount'] = round(array_get($ordersItem,'GiftWrapPrice.Amount',0),2);
								$sapInsertItemArray['GiftWrapPriceCurrencyCode'] = array_get($ordersItem,'GiftWrapPrice.CurrencyCode','');
								$sapInsertItemArray['ItemTaxAmount'] = round(array_get($ordersItem,'ItemTax.Amount',0),2);
								$sapInsertItemArray['ItemTaxCurrencyCode'] = array_get($ordersItem,'ItemTax.CurrencyCode','');
								$sapInsertItemArray['ShippingTaxAmount'] = round(array_get($ordersItem,'ShippingTax.Amount',0),2);
								$sapInsertItemArray['ShippingTaxCurrencyCode'] = array_get($ordersItem,'ShippingTax.CurrencyCode','');
								$sapInsertItemArray['GiftWrapTaxAmount'] = round(array_get($ordersItem,'GiftWrapTax.Amount',0),2);
								$sapInsertItemArray['GiftWrapTaxCurrencyCode'] = array_get($ordersItem,'GiftWrapTax.CurrencyCode','');
								$sapInsertItemArray['ShippingDiscountAmount'] = round(array_get($ordersItem,'ShippingDiscount.Amount',0),2);
								$sapInsertItemArray['ShippingDiscountCurrencyCode'] = array_get($ordersItem,'ShippingDiscount.CurrencyCode','');
								$sapInsertItemArray['PromotionDiscountAmount'] = round(array_get($ordersItem,'PromotionDiscount.Amount',0),2);
								$sapInsertItemArray['PromotionDiscountCurrencyCode'] = array_get($ordersItem,'PromotionDiscount.CurrencyCode','');
								$sapInsertItemArray['PromotionIds'] = serialize(array_get($ordersItem,'PromotionIds',''));
								$sapInsertItemArray['CODFeeAmount'] = round(array_get($ordersItem,'CODFee.Amount',0),2);
								$sapInsertItemArray['CODFeeCurrencyCode'] = array_get($ordersItem,'CODFee.CurrencyCode','');
								$sapInsertItemArray['CODFeeDiscountAmount'] = round(array_get($ordersItem,'CODFeeDiscount.Amount',0),2);
								$sapInsertItemArray['CODFeeDiscountCurrencyCode'] = array_get($ordersItem,'CODFeeDiscount.CurrencyCode','');
								$sapInsertItemData[]=$sapInsertItemArray;
							}
                        }
						
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
                        $insertArray['asins'] = $asins;
                        $insertArray['seller_skus'] = $seller_skus;              
                        $insertArray['created_at'] = $insertArray['updated_at'] = Carbon::now()->toDateTimeString();
                        $insertData[] = $insertArray;
						
						if($importToSap && $my_ordersitem){
							unset($sapInsertArray);
							$sapInsertArray['SellerId'] = (string) $account->mws_seller_id;
							$sapInsertArray['MarketPlaceId'] = (string) $account->mws_marketplaceid;
							$sapInsertArray['AmazonOrderId'] = array_get($arrayOrder,'AmazonOrderId','');
							$sapInsertArray['ApiDownloadDate'] = Carbon::now()->toDateTimeString();
							$sapInsertArray['SellerOrderId'] = array_get($arrayOrder,'SellerOrderId','');
							$sapInsertArray['OrderStatus'] = array_get($arrayOrder,'OrderStatus','');
							$sapInsertArray['BuyerEmail'] = array_get($arrayOrder,'BuyerEmail','');
							$sapInsertArray['BuyerName'] = array_get($arrayOrder,'BuyerName','');
							$sapInsertArray['PurchaseDate'] = $purchaseDate;
							$sapInsertArray['FulfillmentChannel'] = array_get($arrayOrder,'FulfillmentChannel','');
							$sapInsertArray['LastUpdateDate'] = $lastUpdateDate;						
							$sapInsertArray['SalesChannel'] = array_get($arrayOrder,'SalesChannel','');
							$sapInsertArray['OrderChannel'] = array_get($arrayOrder,'OrderChannel','');
							$sapInsertArray['ShipServiceLevel'] = array_get($arrayOrder,'ShipServiceLevel','');
							$sapInsertArray['Name'] = array_get($arrayOrder,'ShippingAddress.Name','');
							$sapInsertArray['AddressLine1'] = array_get($arrayOrder,'ShippingAddress.AddressLine1','');						
							$sapInsertArray['AddressLine2'] = array_get($arrayOrder,'ShippingAddress.AddressLine2','');
							$sapInsertArray['AddressLine3'] = array_get($arrayOrder,'ShippingAddress.AddressLine3','');
							$sapInsertArray['City'] = array_get($arrayOrder,'ShippingAddress.City','');
							$sapInsertArray['County'] = array_get($arrayOrder,'ShippingAddress.County','');
							$sapInsertArray['District'] = array_get($arrayOrder,'ShippingAddress.District','');						
							$sapInsertArray['StateOrRegion'] = array_get($arrayOrder,'ShippingAddress.StateOrRegion','');
							$sapInsertArray['PostalCode'] = array_get($arrayOrder,'ShippingAddress.PostalCode','');
							$sapInsertArray['CountryCode'] = array_get($arrayOrder,'ShippingAddress.CountryCode','');
							$sapInsertArray['Phone'] = array_get($arrayOrder,'ShippingAddress.Phone','');
							$sapInsertArray['Amount'] = round(array_get($arrayOrder,'OrderTotal.Amount',0),2);						
							$sapInsertArray['CurrencyCode'] = array_get($arrayOrder,'OrderTotal.CurrencyCode','');
							$sapInsertArray['NumberOfItemsShipped'] = intval(array_get($arrayOrder,'NumberOfItemsShipped',0));
							$sapInsertArray['NumberOfItemsUnshipped'] = intval(array_get($arrayOrder,'NumberOfItemsUnshipped',0));
							$sapInsertArray['PaymentMethod'] = array_get($arrayOrder,'PaymentMethod','');
							$sapInsertArray['ShipServiceLevelCategory'] = array_get($arrayOrder,'ShipServiceLevelCategory','');						
							$sapInsertArray['EarliestShipDate'] = array_get($arrayOrder,'EarliestShipDate','');
							$sapInsertArray['LatestShipDate'] = array_get($arrayOrder,'LatestShipDate','');
							$sapInsertArray['EarliestDeliveryDate'] = array_get($arrayOrder,'EarliestDeliveryDate','');
							$sapInsertArray['LatestDeliveryDate'] = array_get($arrayOrder,'LatestDeliveryDate','');
							$sapInsertData[]=$sapInsertArray;
						}
                    }
                    if (count($insertData)>10 || !$notEnd)
                    {
						Order::insertOnDuplicateWithDeadlockCatching($insertData,['updated_at','seller_order_id','order_status','buyer_email','buyer_name','purchase_date','purchase_local_date','fulfillment_channel','last_update_date','sales_channel','order_channel','ship_service_level','name','address_line1','address_line2','address_line3','city','county','district','state_or_region','postal_code','country_code','phone','amount','currency_code','number_of_items_shipped','number_of_items_unshipped','payment_method','ship_service_level_category','earliest_ship_date','latest_ship_date','earliest_delivery_date','latest_delivery_date','order_type','asins','seller_skus']);
						OrderItem::insertOnDuplicateWithDeadlockCatching($insertItemData,['asin','seller_sku','purchase_date','fulfillment_channel','title','quantity_ordered','quantity_shipped','gift_wrap_level','gift_message_text','item_price_amount','item_price_currency_code','shipping_price_amount','shipping_price_currency_code','gift_wrap_price_amount','gift_wrap_price_currency_code','item_tax_amount','item_tax_currency_code','shipping_tax_amount','shipping_tax_currency_code','gift_wrap_tax_amount','gift_wrap_tax_currency_code','shipping_discount_amount','shipping_discount_currency_code','promotion_discount_amount','promotion_discount_currency_code','promotion_ids','cod_fee_amount','cod_fee_currency_code','cod_fee_discount_amount','cod_fee_discount_currency_code']);
						if($sapInsertItemData) SapOrderItem::insertIgnore($sapInsertItemData);
						if($sapInsertData) SapOrder::insertIgnore($sapInsertData);
						$account->last_update_order_date  = $lastOrderUpdateDate;									
						$insertData = $insertItemData = $sapInsertItemData = $sapInsertData = array();
						$endTime=microtime(true);
						if($endTime-$startTime>60*30) $notEnd=false;
                    }
                } catch (MarketplaceWebServiceOrders_Exception $ex) {
					$errorMessage = $ex->getMessage();
					$notEnd = false;
                }
			} while ($notEnd);
			$account->created_at = NULL;
			$account->last_action_result  = $errorMessage??('Success '.Carbon::now()->toDateTimeString());
			$account->save();
        }
	}

    public function getOrderItems($amazonOrderId)
    {
        $nextToken = null;
        $timestamp = null;
        $orderItems = [];
        $notEnd = false;
        do {
            if ($nextToken) {
                $request = new MarketplaceWebServiceOrders_Model_ListOrderItemsByNextTokenRequest();
                $request->setNextToken($nextToken);
                $resultName = 'ListOrderItemsByNextTokenResult';
            }
            else{
                $request = new MarketplaceWebServiceOrders_Model_ListOrderItemsRequest();
                $resultName = 'ListOrderItemsResult';
				$request->setAmazonOrderId($amazonOrderId);
            }
            $request->setSellerId($this->account->mws_seller_id);
            $request->setMWSAuthToken($this->account->mws_auth_token);
            try {
                $response = $nextToken?$this->client->listOrderItemsByNextToken($request):$this->client->listOrderItems($request);
                $objResponse = processResponse($response);
                $resultResponse = $objResponse->{$resultName};
                $nextToken = isset($resultResponse->NextToken)?$resultResponse->NextToken:null;
                $lastOrderItems = isset($resultResponse->OrderItems->OrderItem)?$resultResponse->OrderItems->OrderItem:[];
                $notEnd = !empty($nextToken);
                foreach($lastOrderItems as $item)
                {
                    $orderItems[] = json_decode(json_encode($item), true);
                }
            } catch (MarketplaceWebServiceOrders_Exception $ex) {
				throw $ex;
			}
			sleep(2);
        }
        while($notEnd);
        return $orderItems;
    }
}
