<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\SellerAccounts;
use App\SellerAsin;
use App\AsinOfferSummary;
use App\AsinOfferLowest;
use App\AsinOfferBuybox;
use App\AsinOffer;
use Carbon\Carbon;
use MarketplaceWebServiceProducts_Client;
use MarketplaceWebServiceProducts_Model_GetLowestPricedOffersForASINRequest;
use MarketplaceWebServiceProducts_Exception;

class GetLowestPriceOfferForASIN implements ShouldQueue
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
                    $request = new \MarketplaceWebServiceProducts_Model_GetLowestPricedOffersForASINRequest();
                    $request->setMarketplaceId($account->mws_marketplaceid);
                    $request->setASIN($asin->asin);
                    $request->setItemCondition('New');
                    $request->setSellerId($account->mws_seller_id);
				    $request->setMWSAuthToken($account->mws_auth_token);
                    $response = $client->GetLowestPricedOffersForASIN($request);
					$objResponse = simplexml_load_string($response->toXML());
                    $resultName = 'GetLowestPricedOffersForASINResult';
                    $resultResponse = $objResponse->{$resultName};
                    $items = isset($resultResponse)?$resultResponse:[];
                    foreach($items as $item){
                        if($item->attributes()->status!="Success") continue;
                        $summary = $item->Summary;
                        $numberOfOffers = [];
                        if(isset($summary->NumberOfOffers)){
                            $objs = $summary->NumberOfOffers;
                            if(!empty($objs)){
                                foreach($objs->OfferCount as $obj){
                                    $numberOfOffers[]=[
                                        'fulfillment_channel'=>(string)$obj->attributes()->fulfillmentChannel,
                                        'count'=>(int)$obj,
                                    ];
                                }
                            }
                        }
                        $eligibleOffers = [];
                        if(isset($summary->BuyBoxEligibleOffers)){
                            $objs = $summary->BuyBoxEligibleOffers;
                            if(!empty($objs)){
                                foreach($objs->OfferCount as $obj){
                                    $eligibleOffers[]=[
                                        'fulfillment_channel'=>(string)$obj->attributes()->fulfillmentChannel,
                                        'count'=>(int)$obj,
                                    ];
                                }
                            }
                        }
                        $salesRankings = [];
                        if(isset($summary->SalesRankings)){
                            $objs = $summary->SalesRankings;
                            if(!empty($objs)){
                                foreach($objs->SalesRank as $obj){
                                    $salesRankings[]=[
                                        'product_category_id'=>(string)$obj->ProductCategoryId,
                                        'rank'=>(string)$obj->Rank,
                                    ];
                                }
                            }
                        }

                        $data = json_decode(json_encode($summary), true);
                        $summaryData['total_offer_count'] = array_get($data,'TotalOfferCount',0);
                        $summaryData['number_of_offers'] = json_encode($numberOfOffers);
                        $summaryData['list_price'] = array_get($data,'ListPrice.Amount',0);
                        $summaryData['list_price_currency_code'] = array_get($data,'ListPrice.CurrencyCode');
                        $summaryData['suggested_lower_price_plus_shipping'] = array_get($data,'SuggestedLowerPricePlusShipping.Amount',0);
                        $summaryData['suggested_lower_price_plus_shipping_currency_code'] = array_get($data,'SuggestedLowerPricePlusShipping.CurrencyCode');
                        $summaryData['buy_box_eligible_offers'] = json_encode($eligibleOffers);
                        $summaryData['sales_rankings'] = json_encode($salesRankings);

                        $result = AsinOfferSummary::updateOrCreate(
                            [
                                'asin'=>$asin->asin,
                                'marketplace_id'=>$account->mws_marketplaceid,
                                'item_condition'=>'New',
                                'date'=>$date,
                            ],
                            $summaryData
                        );

                        if(isset($summary->LowestPrices)){
                            $objs = $summary->LowestPrices;
                            if(!empty($objs)){
                                foreach($objs->LowestPrice  as $obj){
                                    $lowestPrice = json_decode(json_encode($obj), true);
                                    $lowestPrice=[
                                        'fulfillment_channel'=>(string)$obj->attributes()->fulfillmentChannel,
                                        'landed_price'=>array_get($lowestPrice,'LandedPrice.Amount'),
                                        'landed_price_currency_code'=>array_get($lowestPrice,'LandedPrice.CurrencyCode'),
                                        'listing_price'=>array_get($lowestPrice,'ListingPrice.Amount'),
                                        'listing_price_currency_code'=>array_get($lowestPrice,'ListingPrice.CurrencyCode'),
                                        'shipping'=>array_get($lowestPrice,'Shipping.Amount'),
                                        'shipping_currency_code'=>array_get($lowestPrice,'Shipping.CurrencyCode'),
                                    ];
                                    AsinOfferLowest::updateOrCreate(
                                        [
                                            'asin_offer_summary_id'=>$result->id,
                                        ],
                                        $lowestPrice
                                    );
                                }
                            }
                        }


                        if(isset($summary->BuyBoxPrices)){
                            $objs = $summary->BuyBoxPrices;
                            if(!empty($objs)){
                                foreach($objs->BuyBoxPrice  as $obj){
                                    $buyBoxPrice = json_decode(json_encode($obj), true);
                                    $buyBoxPrice=[
                                        'landed_price'=>array_get($buyBoxPrice,'LandedPrice.Amount'),
                                        'landed_price_currency_code'=>array_get($buyBoxPrice,'LandedPrice.CurrencyCode'),
                                        'listing_price'=>array_get($buyBoxPrice,'ListingPrice.Amount'),
                                        'listing_price_currency_code'=>array_get($buyBoxPrice,'ListingPrice.CurrencyCode'),
                                        'shipping'=>array_get($buyBoxPrice,'Shipping.Amount'),
                                        'shipping_currency_code'=>array_get($buyBoxPrice,'Shipping.CurrencyCode'),
                                    ];
                                    AsinOfferBuybox::updateOrCreate(
                                        [
                                            'asin_offer_summary_id'=>$result->id,
                                        ],
                                        $buyBoxPrice
                                    );
                                }
                            }
                        }

                        $offers = $item->Offers;
                        if(!empty($offers)){
                            foreach($offers->Offer as $offerObj){
								$offer = json_decode(json_encode($offerObj), true);
                                AsinOffer::updateOrCreate(
                                    [
                                        'asin_offer_summary_id'=>$result->id,
                                    ],
                                    [
                                        'seller_id'=>array_get($offer,'SellerId'),
                                        'subcondition'=>array_get($offer,'SubCondition'),
                                        'seller_positive_feedback_rating'=>round(array_get($offer,'SellerFeedbackRating.SellerPositiveFeedbackRating'),2),
                                        'feedback_count'=>intval(array_get($offer,'SellerFeedbackRating.FeedbackCount')),
                                        'available_date'=>isset($offerObj->ShippingTime->attributes()->availableDate)?(string)$offerObj->ShippingTime->attributes()->availableDate:NULL,
                                        'availability_type'=>isset($offerObj->ShippingTime->attributes()->availabilityType)?(string)$offerObj->ShippingTime->attributes()->availabilityType:NULL,
                                        'ships_from'=>array_get($offer,'ShipsFrom.Country'),
                                        'points_number'=>array_get($offer,'Points.PointsNumber'),
                                        'points_monetary_value'=>array_get($offer,'Points.PointsMonetaryValue.Amount'),
                                        'points_monetary_value_currency_code'=>array_get($offer,'Points.PointsMonetaryValue.CurrencyCode'),
                                        'is_fulfilled_by_amazon'=>(array_get($offer,'IsFulfilledByAmazon')=='true')?1:0,
                                        'is_buy_box_winner'=>(array_get($offer,'IsBuyBoxWinner')=='true')?1:0,
                                        'listing_price'=>array_get($offer,'ListingPrice.Amount'),
                                        'listing_price_currency_code'=>array_get($offer,'ListingPrice.CurrencyCode'),
                                        'shipping'=>array_get($offer,'Shipping.Amount'),
                                        'shipping_currency_code'=>array_get($offer,'Shipping.CurrencyCode'),
                                        'is_featured_merchant'=>(array_get($offer,'IsFeaturedMerchant')=='true')?1:0,
                                        'condition_notes'=>array_get($offer,'ConditionNotes'),
                                        'is_national_prime'=>(array_get($offer,'PrimeInformation.IsNationalPrime')=='true')?1:0,
                                        'is_prime'=>(array_get($offer,'PrimeInformation.IsPrime')=='true')?1:0,
                                    ]
                                );
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
