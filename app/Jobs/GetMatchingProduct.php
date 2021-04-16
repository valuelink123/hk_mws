<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\SellerAccounts;
use App\SellerAsin;
use App\SellerAsinRanking;
use App\SellerAsinRelationship;
use Carbon\Carbon;
use MarketplaceWebServiceProducts_Client;
use MarketplaceWebServiceProducts_Model_GetMatchingProductRequest;
use MarketplaceWebServiceProducts_Model_ASINListType;
use MarketplaceWebServiceProducts_Exception;

class GetMatchingProduct implements ShouldQueue
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
                foreach($asins as $asin){
                    $request = new \MarketplaceWebServiceProducts_Model_GetMatchingProductRequest();
                    $request->setMarketplaceId($account->mws_marketplaceid);
                    $requestAsins = new \MarketplaceWebServiceProducts_Model_ASINListType();
                    $asinList = [];
                    $asinList[] = $asin->asin;
                    $requestAsins->setAsin($asinList);
                    $request->setASINList($requestAsins);
                    $request->setSellerId($account->mws_seller_id);
				    $request->setMWSAuthToken($account->mws_auth_token);
                    $response = $client->GetMatchingProduct($request);
					$objResponse = simplexml_load_string($response->toXML());
                    $resultName = 'GetMatchingProductResult';
                    $resultResponse = $objResponse->{$resultName};
                    $items = isset($resultResponse)?$resultResponse:[];
                    foreach($items as $item){
                        if($item->attributes()->status!="Success") continue;
                        //$asin = $item->attributes()->ASIN;
                        $attributeSets = $item->Product->AttributeSets;
                        $relationships = $item->Product->Relationships;
                        $salesRankings = $item->Product->SalesRankings;
                        if(!empty($attributeSets)){
                            $updateAsinData = [];
                            $itemAttributes = json_decode(json_encode($attributeSets->children('ns2', true)->ItemAttributes), true);
                            $asin->binding = array_get($itemAttributes, 'Binding');
                            $asin->brand = array_get($itemAttributes, 'Brand');
                            $asin->item_height = array_get($itemAttributes, 'ItemDimensions.Height');
                            $asin->item_width = array_get($itemAttributes, 'ItemDimensions.Width');
                            $asin->item_length = array_get($itemAttributes, 'ItemDimensions.Length');
                            $asin->item_weight = array_get($itemAttributes, 'ItemDimensions.Weight');
                            $asin->label = array_get($itemAttributes, 'Label');
                            $asin->list_price_amount = array_get($itemAttributes, 'ListPrice.Amount');
                            $asin->list_price_currency = array_get($itemAttributes, 'ListPrice.CurrencyCode');
                            $asin->manufacturer = array_get($itemAttributes, 'Manufacturer');
                            $asin->model = array_get($itemAttributes, 'Model');
                            $asin->package_height = array_get($itemAttributes, 'PackageDimensions.Height');
                            $asin->package_width = array_get($itemAttributes, 'PackageDimensions.Width');
                            $asin->package_length = array_get($itemAttributes, 'PackageDimensions.Length');
                            $asin->package_weight = array_get($itemAttributes, 'PackageDimensions.Weight');
                            $asin->package_quantity = array_get($itemAttributes, 'PackageQuantity');
                            $asin->part_number = array_get($itemAttributes, 'PartNumber');
                            $asin->product_group = array_get($itemAttributes, 'ProductGroup');
                            $asin->product_type_name = array_get($itemAttributes, 'ProductTypeName');
                            $asin->publisher = array_get($itemAttributes, 'Publisher');
                            $asin->image_url = array_get($itemAttributes, 'SmallImage.URL');
                            $asin->studio = array_get($itemAttributes, 'Studio');
                            $asin->title = array_get($itemAttributes, 'Title');
                            $asin->save();
                        }
                        
                        if(!empty($relationships)){
                            $reserveIds = [];
                            foreach($relationships->VariationParent as $variationChild){
								$variationChild = json_decode(json_encode($variationChild), true);
                                $asinRelationship = array_get($variationChild,'Identifiers.MarketplaceASIN.ASIN');
                                unset($variationChild['Identifiers']);
                                $attribute = json_encode($variationChild);
                                $result = SellerAsinRelationship::updateOrCreate(
                                    [
                                        'seller_asin_id'=>$asin->id,
                                        'asin'=>$asinRelationship,
                                    ],
                                    [
                                        'attributes'=>$attribute
                                    ]
                                );
                                $reserveIds[] = $result->id;
                            }
                            SellerAsinRelationship::where('seller_asin_id',$asin->id)->whereNotIn('id',$reserveIds)->delete();
                        }

                        if(!empty($salesRankings)){
                            $reserveIds = [];
                            foreach($salesRankings->SalesRank as $salesRank){
                                $result = SellerAsinRanking::updateOrCreate(
                                    [
                                        'seller_asin_id'=>$asin->id,
                                        'product_category_id'=>$salesRank->ProductCategoryId,
                                    ],
                                    [
                                        'rank'=>$salesRank->Rank
                                    ]
                                );
                                $reserveIds[] = $result->id;
                            }
                            SellerAsinRanking::where('seller_asin_id',$asin->id)->whereNotIn('id',$reserveIds)->delete();
                        }
                    }
                }
            } catch (MarketplaceWebServiceProducts_Exception $ex) {
                throw $ex;  
            }   
		}
    }
}
