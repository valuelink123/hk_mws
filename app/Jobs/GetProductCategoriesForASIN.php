<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\SellerAccounts;
use App\SellerAsin;
use App\AmazonCategory;
use Carbon\Carbon;
use MarketplaceWebServiceProducts_Client;
use MarketplaceWebServiceProducts_Model_GetProductCategoriesForASINRequest;
use MarketplaceWebServiceProducts_Model_ASINListType;
use MarketplaceWebServiceProducts_Exception;

class GetProductCategoriesForASIN implements ShouldQueue
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
                    $request = new \MarketplaceWebServiceProducts_Model_GetProductCategoriesForASINRequest();
                    $request->setMarketplaceId($account->mws_marketplaceid);
                    $request->setASIN($asin->asin);
                    $request->setSellerId($account->mws_seller_id);
				    $request->setMWSAuthToken($account->mws_auth_token);
                    $response = $client->GetProductCategoriesForASIN($request);
					$objResponse = simplexml_load_string($response->toXML());
                    $resultName = 'GetProductCategoriesForASINResult';
                    $resultResponse = $objResponse->{$resultName};
                    $items = isset($resultResponse)?$resultResponse:[];
                    foreach($items as $item){
                        $self = $item->Self;
                        if(!empty($self)){
                            $asin->product_category_id = $self->ProductCategoryId;
                            $asin->save();
                            $notEnd = isset($self->Parent)?true:false;
                            while($notEnd){
                                if(!isset($self->Parent))  $notEnd = false;
                                $result = AmazonCategory::updateOrCreate(
                                    [
                                        'product_category_id'=>$self->ProductCategoryId,
                                    ],
                                    [
                                        'parent_product_category_id'=>isset($self->Parent->ProductCategoryId)?$self->Parent->ProductCategoryId:0,
                                        'product_category_name'=>$self->ProductCategoryName,
                                    ]
                                );
                                $self = $self->Parent;
                            }
                        }
                    }
                    sleep(5);
                }
            } catch (MarketplaceWebServiceProducts_Exception $ex) {
                throw $ex;  
            }   
		}
    }
}
