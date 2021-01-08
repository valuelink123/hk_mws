<?php

namespace App\Jobs;
use App\SellerAccounts;
use App\AmazonMcfOrders;
use App\AmazonMcfOrdersItem;
use App\AmazonMcfShipment;
use App\AmazonMcfShipmentItem;
use App\AmazonMcfShipmentItemPackage;
use Carbon\Carbon;
use FBAOutboundServiceMWS_Client;
use FBAOutboundServiceMWS_Model_ListAllFulfillmentOrdersByNextTokenRequest;
use FBAOutboundServiceMWS_Model_ListAllFulfillmentOrdersRequest;
use FBAOutboundServiceMWS_Model_GetFulfillmentOrderRequest;
use FBAOutboundServiceMWS_Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class GetMcfOrdersForAccount implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
	protected $account;
    protected $afterDate;
    protected $beforeDate;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($account,$afterDate='',$beforeDate='')
    {
        $this->account = $account;
		$afterDate_init = ($account->last_update_mcforder_date)?$account->last_update_mcforder_date:date('c', strtotime('-1 day'));
        $this->afterDate = ($afterDate)?$afterDate:$afterDate_init;
        $this->beforeDate = ($beforeDate)?$beforeDate:date('c', strtotime('-5 min'));
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

            $this->client = new FBAOutboundServiceMWS_Client(
                $account->mws_access_keyid,
                $account->mws_secret_key,
                ['ServiceURL' => $siteConfig[$account->mws_marketplaceid]['serviceUrl']."/FulfillmentOutboundShipment/2010-10-01",
                    'ProxyHost' => null,
                    'ProxyPort' => -1,
                    'MaxErrorRetry' => 3],
                'VLMWS',
                '1.0.0'

            );
           
            $notEnd = false;
            $nextToken = null;
            $this->updateData = [];
			$lastUpdateDate = Carbon::parse($this->afterDate)->toDateTimeString();
            do {
                if ($nextToken) {
                    $request = new FBAOutboundServiceMWS_Model_ListAllFulfillmentOrdersByNextTokenRequest();
                    $request->setNextToken($nextToken);
                    $resultName = 'ListAllFulfillmentOrdersByNextTokenResult';
                } else {
                    $request = new FBAOutboundServiceMWS_Model_ListAllFulfillmentOrdersRequest();
                    $request->setQueryStartDateTime(date('c',strtotime($lastUpdateDate)));  
                    $resultName = 'ListAllFulfillmentOrdersResult';
                }
                $request->setSellerId($account->mws_seller_id);
                $request->setMWSAuthToken($account->mws_auth_token);
                try {
                    $response = $nextToken?$this->client->listAllFulfillmentOrdersByNextToken($request):$this->client->listAllFulfillmentOrders($request);
                    $objResponse = processResponse($response);
                    $resultResponse = $objResponse->{$resultName};
                    $nextToken = isset($resultResponse->NextToken)?$resultResponse->NextToken:null;
                    $lastOrders = isset($resultResponse->FulfillmentOrders->member)?$resultResponse->FulfillmentOrders->member:[];
                    $notEnd = !empty($nextToken);
                    foreach($lastOrders as $order)
                    {
                        $orderId = (string)$order->SellerFulfillmentOrderId;
                        if($lastUpdateDate < (Carbon::parse((string)$order->StatusUpdatedDateTime)->toDateTimeString())) $lastUpdateDate=Carbon::parse((string)$order->StatusUpdatedDateTime)->toDateTimeString();
                        $this->getOrder($orderId);
                    }
                    if (count(array_get($this->updateData,'orders',[]))>50 || !$notEnd)
                    {
						AmazonMcfShipmentItemPackage::insertOnDuplicateWithDeadlockCatching(array_get($this->updateData,'packages',[]),['carrier_code','tracking_number','estimated_arrival_date_time']);
						AmazonMcfShipmentItem::insertOnDuplicateWithDeadlockCatching(array_get($this->updateData,'shipmentItems',[]),['seller_sku','quantity','package_number']);
						AmazonMcfShipment::insertOnDuplicateWithDeadlockCatching(array_get($this->updateData,'shipments',[]), ['fulfillment_center_id','fulfillment_shipment_status','shipping_date_time','estimated_arrival_date_time']);
						AmazonMcfOrdersItem::insertOnDuplicateWithDeadlockCatching(array_get($this->updateData,'items',[]), ['seller_sku','quantity','gift_message','displayable_comment','fulfillment_network_sku','order_item_disposition','cancelled_quantity','unfulfillable_quantity','estimated_ship_date_time','estimated_arrival_date_time','per_unit_declared','per_unit_declared_currency_code']);
						AmazonMcfOrders::insertOnDuplicateWithDeadlockCatching(array_get($this->updateData,'orders',[]), ['displayable_order_id','displayable_order_date_time','displayable_order_comment','shipping_speed_category','name','address_line_1','address_line_2','address_line_3','city','district','state_or_region','postal_code','country_code','phone','fulfillment_policy','fulfillment_method','received_date_time','fulfillment_order_status','status_updated_date_time','notification_email_list']);
						$account->last_update_mcforder_date  = $lastUpdateDate;
						$account->save();
                        $this->updateData = [];
                    }
                } catch (FBAOutboundServiceMWS_Exception $ex) {
                    if (getExRetry($ex)) {
						$notEnd = true;
						sleep(60);
					}else{
						throw $ex;
					}
                }
            } while ($notEnd);


        }
	}

    public function getOrder($orderId){
        sleep(1);
        $request = new FBAOutboundServiceMWS_Model_GetFulfillmentOrderRequest();
        $request->setSellerId($this->account->mws_seller_id);
        $request->setMWSAuthToken($this->account->mws_auth_token);
        $request->setSellerFulfillmentOrderId($orderId);
        $resultName = 'GetFulfillmentOrderResult';
		$notEnd = false;
		do {
			try {
				$response = $this->client->getFulfillmentOrder($request);
				$objResponse = processResponse($response);
				$resultResponse = $objResponse->{$resultName};
				if(isset($resultResponse->FulfillmentOrder)){
					$NotificationEmailList = isset($resultResponse->FulfillmentOrder->NotificationEmailList->member)?$resultResponse->FulfillmentOrder->NotificationEmailList->member:[];
					$orderDetails = json_decode(json_encode($resultResponse->FulfillmentOrder),true);
					$order_data = [
						'user_id' => $this->account->user_id,
						'seller_account_id' => $this->account->id,
						'seller_fulfillment_order_id' => array_get($orderDetails,'SellerFulfillmentOrderId',''),
						'displayable_order_id' => array_get($orderDetails,'DisplayableOrderId',''),
						'displayable_order_date_time' => date('Y-m-d H:i:s',strtotime(array_get($orderDetails,'DisplayableOrderDateTime',''))),
						'displayable_order_comment' => array_get($orderDetails,'DisplayableOrderComment',''),
						'shipping_speed_category' => array_get($orderDetails,'ShippingSpeedCategory',''),
						'name' => array_get($orderDetails,'DestinationAddress.Name',''),
						'address_line_1' => array_get($orderDetails,'DestinationAddress.Line1',''),
						'address_line_2' => array_get($orderDetails,'DestinationAddress.Line2',''),
						'address_line_3' => array_get($orderDetails,'DestinationAddress.Line3',''),
						'city' => array_get($orderDetails,'DestinationAddress.City',''),
						'district' => array_get($orderDetails,'DestinationAddress.DistrictOrCounty',''),
						'state_or_region'=>array_get($orderDetails,'DestinationAddress.StateOrRegion',''),
						'postal_code' => array_get($orderDetails,'DestinationAddress.PostalCode',''),
						'country_code' => array_get($orderDetails,'DestinationAddress.CountryCode',''),
						'phone' => array_get($orderDetails,'DestinationAddress.PhoneNumber',''),
						'fulfillment_policy' => array_get($orderDetails,'FulfillmentPolicy',''),
						'fulfillment_method' => array_get($orderDetails,'FulfillmentMethod',''),
						'received_date_time' => array_get($orderDetails,'ReceivedDateTime',''),
						'fulfillment_order_status' => array_get($orderDetails,'FulfillmentOrderStatus',''),
						'status_updated_date_time' => date('Y-m-d H:i:s',strtotime(array_get($orderDetails,'StatusUpdatedDateTime',''))),
						'notification_email_list' => array_get(json_decode(json_encode($NotificationEmailList),true),0,''),
						'seller_skus'=>'',
						'quantity'=>0
					];
				}
				if(isset($resultResponse->FulfillmentOrderItem)){
					$FulfillmentOrderItem = isset($resultResponse->FulfillmentOrderItem->member)?$resultResponse->FulfillmentOrderItem->member:[];
					foreach($FulfillmentOrderItem as $itemDetails){
						$itemDetails=json_decode(json_encode($itemDetails),true);
						$order_data['seller_skus'] = $order_data['seller_skus'].array_get($itemDetails,'SellerSKU','').'*'.intval(array_get($itemDetails,'Quantity',0)).';';
						$order_data['quantity'] += intval(array_get($itemDetails,'Quantity',0));
						$this->updateData['items'][]=[
							'user_id' => $this->account->user_id,
							'seller_account_id' => $this->account->id,
							'seller_fulfillment_order_id' => array_get($orderDetails,'SellerFulfillmentOrderId',''),
							'seller_fulfillment_order_item_id'=> array_get($itemDetails,'SellerFulfillmentOrderItemId',''),
							'seller_sku' => array_get($itemDetails,'SellerSKU',''),
							'quantity' => intval(array_get($itemDetails,'Quantity',0)),
							'gift_message' => array_get($itemDetails,'GiftMessage',''),
							'displayable_comment' => array_get($itemDetails,'DisplayableComment',''),
							'fulfillment_network_sku' => array_get($itemDetails,'FulfillmentNetworkSKU',''),
							'order_item_disposition' => array_get($itemDetails,'OrderItemDisposition',''),
							'cancelled_quantity' => intval(array_get($itemDetails,'CancelledQuantity',0)),
							'unfulfillable_quantity' => intval(array_get($itemDetails,'UnfulfillableQuantity',0)),
							'estimated_ship_date_time' => array_get($itemDetails,'EstimatedShipDateTime',''),
							'estimated_arrival_date_time' => array_get($itemDetails,'EstimatedArrivalDateTime',''),
							'per_unit_declared' => round(array_get($itemDetails,'PerUnitDeclaredValue.Value',0),4),
							'per_unit_declared_currency_code' => array_get($itemDetails,'PerUnitDeclaredValue.CurrencyCode',''),
						];
	
					}
					$this->updateData['orders'][]=$order_data;
				}
				if(isset($resultResponse->FulfillmentShipment)){
					$FulfillmentShipment = isset($resultResponse->FulfillmentShipment->member)?$resultResponse->FulfillmentShipment->member:[];
					foreach($FulfillmentShipment as $orderShipmentsDetails){
						$packages = isset($orderShipmentsDetails->FulfillmentShipmentPackage->member)?$orderShipmentsDetails->FulfillmentShipmentPackage->member:[];
						$items = isset($orderShipmentsDetails->FulfillmentShipmentItem->member)?$orderShipmentsDetails->FulfillmentShipmentItem->member:[];
						$orderShipmentsDetails=json_decode(json_encode($orderShipmentsDetails),true);
						$this->updateData['shipments'][]=[
							'user_id' => $this->account->user_id,
							'seller_account_id' => $this->account->id,
							'seller_fulfillment_order_id' => array_get($orderDetails,'SellerFulfillmentOrderId',''),
							'amazon_shipment_id' => array_get($orderShipmentsDetails,'AmazonShipmentId',''),
							'fulfillment_center_id' => array_get($orderShipmentsDetails,'FulfillmentCenterId',''),
							'fulfillment_shipment_status' => array_get($orderShipmentsDetails,'FulfillmentShipmentStatus',''),
							'shipping_date_time' => array_get($orderShipmentsDetails,'ShippingDateTime',''),
							'estimated_arrival_date_time' => array_get($orderShipmentsDetails,'EstimatedArrivalDateTime',''),
						];
						foreach($items as $item){
							$item=json_decode(json_encode($item),true);
							$this->updateData['shipmentItems'][]=[
								'user_id' => $this->account->user_id,
								'seller_account_id' => $this->account->id,
								'seller_fulfillment_order_id' => array_get($orderDetails,'SellerFulfillmentOrderId',''),
								'amazon_shipment_id' => array_get($orderShipmentsDetails,'AmazonShipmentId',''),
								'seller_fulfillment_order_item_id' => array_get($item,'SellerFulfillmentOrderItemId',''),
								'package_number' => array_get($item,'PackageNumber',''),
								'seller_sku' => array_get($item,'SellerSKU',''),
								'quantity' => intval(array_get($item,'Quantity',0))
							];
						}
	
						foreach($packages as $package){
							$package=json_decode(json_encode($package),true);
							$this->updateData['packages'][]=[
								'user_id' => $this->account->user_id,
								'seller_account_id' => $this->account->id,
								'seller_fulfillment_order_id' => array_get($orderDetails,'SellerFulfillmentOrderId',''),
								'amazon_shipment_id' => array_get($orderShipmentsDetails,'AmazonShipmentId',''),
								'package_number' => array_get($package,'PackageNumber',''),
								'carrier_code' => array_get($package,'CarrierCode',''),
								'tracking_number' => array_get($package,'TrackingNumber',''),
								'estimated_arrival_date_time' => date('Y-m-d H:i:s',strtotime(array_get($package,'EstimatedArrivalDateTime','')))
							];
						}
					}
	
				}
			} catch (FBAOutboundServiceMWS_Exception $ex) {
				if (getExRetry($ex)) {
					$notEnd = true;
					sleep(60);
				}else{
					throw $ex;
				}
			}
		} while ($notEnd);
    }
}
