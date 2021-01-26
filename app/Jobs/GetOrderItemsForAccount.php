<?php

namespace App\Jobs;
use App\SellerAccounts;
use App\OrderItem;
use App\Order;
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

class GetOrderItemsForAccount implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    protected $account;

	public function __construct($account)
	{
		$this->account = $account;
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
            try {
                $startTime=microtime(true);
                $orders = Order::where('seller_account_id',$account->id)->where('vop_flag',0)->take(30)->orderBy('last_update_date','asc')->get();
                foreach($orders as $order){
                    $asins = $seller_skus = Null;
                    $insertItemData = array();
                    $arrayOrderItems = self::getOrderItems($order->amazon_order_id);
                    foreach($arrayOrderItems as $ordersItem){
                        unset($insertItemArray);
                        $asins.=array_get($ordersItem,'ASIN').'*'.array_get($ordersItem,'QuantityOrdered').';';
                        $seller_skus.=array_get($ordersItem,'SellerSKU').'*'.array_get($ordersItem,'QuantityOrdered').';';	
                        $insertItemArray['user_id'] = $account->user_id;
                        $insertItemArray['seller_account_id'] = (string) $account->id;
                        $insertItemArray['purchase_date'] = $order->purchase_date;
                        $insertItemArray['fulfillment_channel'] = $order->fulfillment_channel;
                        $insertItemArray['amazon_order_id'] = $order->amazon_order_id;
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
                    }
                    if($insertItemData) OrderItem::insertOnDuplicateWithDeadlockCatching($insertItemData,['asin','seller_sku','purchase_date','fulfillment_channel','title','quantity_ordered','quantity_shipped','gift_wrap_level','gift_message_text','item_price_amount','item_price_currency_code','shipping_price_amount','shipping_price_currency_code','gift_wrap_price_amount','gift_wrap_price_currency_code','item_tax_amount','item_tax_currency_code','shipping_tax_amount','shipping_tax_currency_code','gift_wrap_tax_amount','gift_wrap_tax_currency_code','shipping_discount_amount','shipping_discount_currency_code','promotion_discount_amount','promotion_discount_currency_code','promotion_ids','cod_fee_amount','cod_fee_currency_code','cod_fee_discount_amount','cod_fee_discount_currency_code']);  
                    $orderUpdate['asins'] = $asins;
                    $orderUpdate['seller_skus'] = $seller_skus;  
                    $orderUpdate['vop_flag'] = 1; 
                    Order::find($order->id)->update($orderUpdate);
                    $endTime=microtime(true);
                    if($endTime-$startTime>=60) exit;
                }
            } catch (MarketplaceWebServiceOrders_Exception $ex) {
                throw $ex;
            }
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
            
			sleep(2);
        }
        while($notEnd);
        return $orderItems;
    }
}
