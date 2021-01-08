<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\SellerAccounts;
use App\SellerInventorySupply;
use Carbon\Carbon;
use FBAInventoryServiceMWS_Client;
use FBAInventoryServiceMWS_Model_ListInventorySupplyByNextTokenRequest;
use FBAInventoryServiceMWS_Model_ListInventorySupplyRequest;
use FBAInventoryServiceMWS_Exception;

class GetInventoryForAccount implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
	protected $account;

    /**
     * Create a new job instance.
     *
     * @return void
     */
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
            $client = new FBAInventoryServiceMWS_Client(
                $account->mws_access_keyid,
                $account->mws_secret_key,
                ['ServiceURL' => $siteConfig[$account->mws_marketplaceid]['serviceUrl']."/FulfillmentInventory/2010-10-01",
                    'ProxyHost' => null,
                    'ProxyPort' => -1,
                    'MaxErrorRetry' => 3],
                'VLMWS',
                '1.0.0'

            );
            $marketPlaceId = $account->mws_marketplaceid;
			$nextToken = null;
			$insertData = [];
			
			$clearData = array(
				'updated_at'=>Carbon::now()->toDateTimeString(),
				'earliest_availability'=>'',
				'in_stock'=>0,
				'transfer'=>0,
				'total'=>0
			);
			SellerInventorySupply::where('seller_account_id',$account->id)->update($clearData);
			do {
				if ($nextToken) {
					$request = new FBAInventoryServiceMWS_Model_ListInventorySupplyByNextTokenRequest();
					$request->setNextToken($nextToken);
					$resultName = 'ListInventorySupplyByNextTokenResult';
				} else {
					$request = new FBAInventoryServiceMWS_Model_ListInventorySupplyRequest();
					$request->setMarketplace($marketPlaceId);
					$request->setQueryStartDateTime(date('c', strtotime('-365 days')));
					$request->setResponseGroup('Basic');
					$resultName = 'ListInventorySupplyResult';
				}
				$request->setSellerId($account->mws_seller_id);
				$request->setMWSAuthToken($account->mws_auth_token);
				try {
					$response = $nextToken ? $client->listInventorySupplyByNextToken($request) : $client->listInventorySupply($request);
					$objResponse = simplexml_load_string($response->toXML());


					$resultResponse = $objResponse->{$resultName};
					$nextToken = isset($resultResponse->NextToken) ? $resultResponse->NextToken : null;
					$members = isset($resultResponse->InventorySupplyList->member) ? $resultResponse->InventorySupplyList->member : [];
					$notEnd = !empty($nextToken);
					foreach ($members as $member) {
						$arrayMember = json_decode(json_encode($member), true);
						$insertArray= [];
						if(array_get($arrayMember,'SellerSKU')){
							$insertArray['seller_account_id'] = $account->id;
							$insertArray['user_id'] = $account->user_id;
							$insertArray['updated_at'] = Carbon::now()->toDateTimeString();
							$insertArray['asin'] = array_get($arrayMember,'ASIN');
							$insertArray['seller_sku'] = array_get($arrayMember,'SellerSKU','');
							$insertArray['earliest_availability'] = array_get($arrayMember,'EarliestAvailability.TimepointType','');
							$insertArray['in_stock'] = array_get($arrayMember,'InStockSupplyQuantity',0);
							$insertArray['transfer'] = array_get($arrayMember,'TotalSupplyQuantity',0)-array_get($arrayMember,'InStockSupplyQuantity',0);
							$insertArray['total'] = array_get($arrayMember,'TotalSupplyQuantity',0);
							$insertData[] = $insertArray;
						}
					}
					if ($insertData) {
						SellerInventorySupply::insertOnDuplicateWithDeadlockCatching($insertData, ['updated_at','earliest_availability', 'in_stock', 'transfer', 'total']);
						$insertData = [];
					}

				} catch (FBAInventoryServiceMWS_Exception $ex) {
					if (getExRetry($ex)) {
						$notEnd = true;
						sleep(60);
					}else{
						throw $ex;
					}
				}
				sleep(2);
			} while ($notEnd);
		}
    }
}
