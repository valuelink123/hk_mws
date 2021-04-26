<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\SellerAccounts;
use App\SellerAsin;
use App\SellerAsinPrice;
use Carbon\Carbon;
use MarketplaceWebServiceProducts_Client;
use MarketplaceWebServiceProducts_Model_GetMyPriceForASINRequest;
use MarketplaceWebServiceProducts_Model_ASINListType;
use MarketplaceWebServiceProducts_Exception;

class GetMyPriceForASIN implements ShouldQueue
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
            $client = new MarketplaceWebServiceProducts_Client(
                $account->mws_access_keyid,
                $account->mws_secret_key,
                'VLMWS',
                '1.0.0',
                ['ServiceURL' => $siteConfig[$account->mws_marketplaceid]['serviceUrl']."/Products/2011-10-01",
                    'ProxyHost' => null,
                    'ProxyPort' => -1,
                    'MaxErrorRetry' => 3]

            );
            $marketPlaceId = $account->mws_marketplaceid;
            try {
                $asins = SellerAsin::where('seller_account_id',$account->id)->get();
                $date = date('Y-m-d');
                foreach($asins as $asin){
                    $request = new \MarketplaceWebServiceProducts_Model_GetMyPriceForASINRequest();
                    $request->setMarketplaceId($account->mws_marketplaceid);
                    $requestAsins = new \MarketplaceWebServiceProducts_Model_ASINListType();
                    $asinList = [];
                    $asinList[] = $asin->asin;
                    $requestAsins->setAsin($asinList);
                    $request->setASINList($requestAsins);
                    $request->setSellerId($account->mws_seller_id);
				    $request->setMWSAuthToken($account->mws_auth_token);
                    $response = $client->GetMyPriceForASIN($request);
					$objResponse = simplexml_load_string($response->toXML());
                    $resultName = 'GetMyPriceForASINResult';
                    $resultResponse = $objResponse->{$resultName};
                    $items = isset($resultResponse)?$resultResponse:[];
                    foreach($items as $item){
                        if($item->attributes()->status!="Success") continue;
                        //$asin = $item->attributes()->ASIN;
                        $offers = $item->Product->Offers;
                        if(!empty($offers)){
                            $reserveIds = [];
                            foreach($offers->Offer as $offer){
								$offer = json_decode(json_encode($offer), true);
                                $result = SellerAsinPrice::updateOrCreate(
                                    [
                                        'seller_asin_id'=>$asin->id,
                                        'seller_sku'=>array_get($offer,'SellerSKU'),
                                        'date'=>$date,
                                    ],
                                    [
                                        'fulfillment_channel'=>array_get($offer,'FulfillmentChannel'),
                                        'item_condition'=>array_get($offer,'ItemCondition'),
                                        'item_sub_condition'=>array_get($offer,'ItemSubCondition'),
                                        'landed_price'=>array_get($offer,'BuyingPrice.LandedPrice.Amount'),
                                        'listing_price'=>array_get($offer,'BuyingPrice.ListingPrice.Amount'),
                                        'shipping_price'=>array_get($offer,'BuyingPrice.Shipping.Amount'),
                                        'regular_price'=>array_get($offer,'RegularPrice.Amount'),
                                        'currency'=>array_get($offer,'RegularPrice.CurrencyCode'),
                                    ]
                                );
                                $reserveIds[] = $result->id;
                            }
                        }
                    }
					sleep(1);
                }
            } catch (MarketplaceWebServiceProducts_Exception $ex) {
                throw $ex;  
            }   
		}
    }
}
