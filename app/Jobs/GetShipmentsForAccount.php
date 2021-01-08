<?php

namespace App\Jobs;
use App\SellerAccounts;
use App\AmazonShipmentItem;
use App\AmazonShipment;
use Carbon\Carbon;
use FBAInboundServiceMWS_Client;
use FBAInboundServiceMWS_Model_ListInboundShipmentsByNextTokenRequest;
use FBAInboundServiceMWS_Model_ListInboundShipmentsRequest;
use FBAInboundServiceMWS_Model_ListInboundShipmentItemsByNextTokenRequest;
use FBAInboundServiceMWS_Model_ListInboundShipmentItemsRequest;
use FBAInboundServiceMWS_Model_ShipmentStatusList;
use FBAInboundServiceMWS_Exception;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class GetShipmentsForAccount implements ShouldQueue
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
		$afterDate_init = ($account->last_update_shipment_date)?$account->last_update_shipment_date:date('c', strtotime('-1 day'));
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
            $this->client = new FBAInboundServiceMWS_Client(
                $account->mws_access_keyid,
                $account->mws_secret_key,
                'VLMWS',
                '1.0.0',
                ['ServiceURL' => $siteConfig[$account->mws_marketplaceid]['serviceUrl']."/FulfillmentInboundShipment/2010-10-01",
                    'ProxyHost' => null,
                    'ProxyPort' => -1,
                    'MaxErrorRetry' => 3]

            );
            $notEnd = false;
            $nextToken = null;
			$insertItemData=$insertData=array();
            do {
                if ($nextToken) {
                    $request = new FBAInboundServiceMWS_Model_ListInboundShipmentsByNextTokenRequest();
                    $request->setNextToken($nextToken);
                    $resultName = 'ListInboundShipmentsByNextTokenResult';
                } else {
                    $request = new FBAInboundServiceMWS_Model_ListInboundShipmentsRequest();
                    $request->setLastUpdatedAfter(date('c',strtotime($this->afterDate)));
					$request->setLastUpdatedBefore(date('c',strtotime($this->beforeDate)));
                    $status = new FBAInboundServiceMWS_Model_ShipmentStatusList();
					$status->setmember(getShipmentStatus());
					$request->setShipmentStatusList($status);
                    $resultName = 'ListInboundShipmentsResult';
                }
                $request->setSellerId($account->mws_seller_id);
                $request->setMWSAuthToken($account->mws_auth_token);

                try {
                    $response = $nextToken?$this->client->ListInboundShipmentsByNextToken($request):$this->client->ListInboundShipments($request);
                    $objResponse = processResponse($response);
                    $resultResponse = $objResponse->{$resultName};
                    $nextToken = isset($resultResponse->NextToken)?$resultResponse->NextToken:null;
                    $lastShipments = isset($resultResponse->ShipmentData->member)?$resultResponse->ShipmentData->member:[];
                    $notEnd = !empty($nextToken);
                    foreach($lastShipments as $shipment)
                    {
						$shipment = json_decode(json_encode($shipment), true);
						$arrayShipment['shipment_id'] = array_get($shipment,'ShipmentId');
						$arrayShipment['seller_id']=$account->mws_seller_id;
						$arrayShipment['updated_at']=Carbon::parse($this->beforeDate)->toDateTimeString();
						$arrayShipment['destination_fulfillment_center_id'] = array_get($shipment,'DestinationFulfillmentCenterId');
						$arrayShipment['label_prep_type'] = array_get($shipment,'LabelPrepType');
						$arrayShipment['city'] = array_get($shipment,'ShipFromAddress.City');
						$arrayShipment['country_code'] = array_get($shipment,'ShipFromAddress.CountryCode');
						$arrayShipment['postal_code'] = array_get($shipment,'ShipFromAddress.PostalCode');
						$arrayShipment['name'] = array_get($shipment,'ShipFromAddress.Name');
						$arrayShipment['address_line1'] = array_get($shipment,'ShipFromAddress.AddressLine1');
						$arrayShipment['address_line2'] = array_get($shipment,'ShipFromAddress.AddressLine2');
						$arrayShipment['state_or_province_code'] = array_get($shipment,'ShipFromAddress.StateOrProvinceCode');
						$arrayShipment['are_cases_required'] = (array_get($shipment,'AreCasesRequired')=='false')?0:1;
						$arrayShipment['box_contents_source'] = array_get($shipment,'BoxContentsSource');
						$arrayShipment['shipment_name'] = array_get($shipment,'ShipmentName');
						$arrayShipment['shipment_status'] = array_get($shipment,'ShipmentStatus');
						
						$items = self::getShipmentItems(array_get($shipment,'ShipmentId'));
						foreach($items as $shipmentItem){
							$insertItemArray['seller_id'] = $account->mws_seller_id;
							$insertItemArray['shipment_id'] = array_get($shipmentItem,'ShipmentId');
							$insertItemArray['quantity_shipped'] = intval(array_get($shipmentItem,'QuantityShipped',0));
							$insertItemArray['fnsku'] = array_get($shipmentItem,'FulfillmentNetworkSKU');
							$insertItemArray['seller_sku'] = array_get($shipmentItem,'SellerSKU');
							$insertItemArray['quantity_received'] = intval(array_get($shipmentItem,'QuantityReceived',0));
							$insertItemArray['quantity_incase'] = intval(array_get($shipmentItem,'QuantityInCase',0));
							$insertItemArray['updated_at']=Carbon::parse($this->beforeDate)->toDateTimeString();
							$insertItemData[] = $insertItemArray;
						}

						$insertData[]=$arrayShipment;
                    }
                    if (count($insertData)>100 || !$notEnd)
                    {
						AmazonShipmentItem::insertOnDuplicateWithDeadlockCatching($insertItemData,['quantity_shipped','quantity_received','quantity_incase','updated_at']);
						AmazonShipment::insertOnDuplicateWithDeadlockCatching($insertData,['updated_at','destination_fulfillment_center_id','label_prep_type','city','country_code','postal_code','name','address_line1','address_line2','state_or_province_code','are_cases_required','box_contents_source','shipment_name','shipment_status']);			
                        $insertData = [];
                        $insertItemData = [];
                    }
					

                } catch (FBAInboundServiceMWS_Exception $ex) {
                    if (getExRetry($ex)) {
						$notEnd = true;
						sleep(60);
					}else{
						throw $ex;
					}
                }
				
            } while ($notEnd);
			$account->last_update_shipment_date  = Carbon::parse($this->beforeDate)->toDateTimeString();
			$account->save();
        }
	}

    public function getShipmentItems($shipmentId)
    {
        $nextToken = null;
        $timestamp = null;
        $shipmentItems = [];
        $notEnd = false;
        do {
            sleep(2);
            if ($nextToken) {
                $request = new FBAInboundServiceMWS_Model_ListInboundShipmentItemsByNextTokenRequest();
                $request->setNextToken($nextToken);
                $resultName = 'ListInboundShipmentItemsByNextTokenResult';
            }
            else{
                $request = new FBAInboundServiceMWS_Model_ListInboundShipmentItemsRequest();
                $resultName = 'ListInboundShipmentItemsResult';
				$request->setShipmentId($shipmentId);
            }
            $request->setSellerId($this->account->mws_seller_id);
            $request->setMWSAuthToken($this->account->mws_auth_token);
            try {
                $response = $nextToken?$this->client->ListInboundShipmentItemsByNextToken($request):$this->client->ListInboundShipmentItems($request);
                $objResponse = processResponse($response);
                $resultResponse = $objResponse->{$resultName};
                $nextToken = isset($resultResponse->NextToken)?$resultResponse->NextToken:null;
                $lastShipmentItems = isset($resultResponse->ItemData->member)?$resultResponse->ItemData->member:[];
                $notEnd = !empty($nextToken);
                foreach($lastShipmentItems as $item)
                {
                    $shipmentItems[] = json_decode(json_encode($item), true);
                }

            } catch (FBAInboundServiceMWS_Exception $ex) {
                if (getExRetry($ex)) {
					$notEnd = true;
					sleep(60);
				}else{
					throw $ex;
				}
            }
        }
        while($notEnd);
        return $shipmentItems;
    }
}
