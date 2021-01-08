<?php

namespace App\Jobs;
use Illuminate\Bus\Queueable;
//use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Asin;
use App\SellerAccounts;
use Carbon\Carbon;
use Keepa\API\Request;
use Keepa\API\ResponseStatus;
use Keepa\helper\CSVType;
use Keepa\helper\CSVTypeWrapper;
use Keepa\helper\KeepaTime;
use Keepa\helper\ProductAnalyzer;
use Keepa\helper\ProductType;
use Keepa\KeepaAPI;
use Keepa\objects\AmazonLocale;
use DB;

class KeepaRequest implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /**
     * Create a new job instance.
     *
     * @return void
     */
	protected $asin;
	
    public function __construct($asin)
    {
		$this->asin = $asin;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $api = new KeepaAPI(env('KEEPA_API_KEY'));
		$domain_ids = array(
			'A2EUQ1WTGCTBG2'=>6,
			'A1PA6795UKMFR9'=>3,
			'A1RKKUPIHCS9HS'=>9,
			'A13V1IB3VIYZZH'=>4,
			'A21TJRUUN4KGV'=>10,
			'APJ6JRA9NG5V4'=>8,
			'A1VC38T7YXB528'=>5,
			'A1F83G8C2ARO7P'=>2,
			'A1AM78C64UM0Y8'=>11,
			'ATVPDKIKX0DER'=>1
		);
        $r = Request::getProductRequest(array_get($domain_ids,$this->asin->marketplaceid), 0, date('Y-m-d',strtotime('-2day')), date('Y-m-d'), 0, 0, ['0'=>$this->asin->asin],['rating'=>1,'buybox'=>1]);
		$data=[];
		$buybox_sellerid = '';
		try{
			$response = $api->sendRequestWithRetry($r);
			if($response->status=='OK' && count($response->products)==1){
					$data['listed_at'] = date('Y-m-d H:i:s',KeepaTime::keepaMinuteToUnixInMillis($response->products[0]->listedSince)/1000);
					$data['title'] = $response->products[0]->title;
					$data['images'] = $response->products[0]->imagesCSV;
					$data['parent_asin'] = $response->products[0]->parentAsin;
					$data['upc'] = $response->products[0]->upc;
					$data['ean'] = $response->products[0]->ean;
					$data['mpn'] = $response->products[0]->mpn;
					$data['status'] = $response->products[0]->productType;
					$data['manufacturer'] = $response->products[0]->manufacturer;
					$data['brand'] = $response->products[0]->brand;
					if(!empty($response->products[0]->categoryTree) && count($response->products[0]->categoryTree)>0) $data['category_tree'] = json_encode(json_decode(json_encode($response->products[0]->categoryTree), true));
					$data['product_group'] = $response->products[0]->productGroup;
					$data['model'] = $response->products[0]->model;
					$data['color'] = $response->products[0]->color;
					if(!empty($response->products[0]->features) && count($response->products[0]->features)>0) $data['features'] = json_encode($response->products[0]->features);
					$data['description'] = $response->products[0]->description;
					$data['rating_updated_at'] = date('Y-m-d H:i:s',KeepaTime::keepaMinuteToUnixInMillis($response->products[0]->lastRatingUpdate)/1000);
					$data['list_updated_at'] = date('Y-m-d H:i:s',KeepaTime::keepaMinuteToUnixInMillis($response->products[0]->lastUpdate)/1000);
					$data['bsr'] =  (array_get($response->products[0]->stats->current,'3')>0)?array_get($response->products[0]->stats->current,'3'):0;
					
					$data['seller_count'] =  (array_get($response->products[0]->stats->current,'11')>0)?array_get($response->products[0]->stats->current,'11'):0;
					
					$data['price'] =  (array_get($response->products[0]->stats->current,'1')>0)?round(array_get($response->products[0]->stats->current,'1')/(in_array($this->asin->marketplaceid,['A1VC38T7YXB528'])?1:100),2):0;
					$data['reviews'] =  (array_get($response->products[0]->stats->current,'17')>0)?array_get($response->products[0]->stats->current,'17'):0;
					$data['rating'] =  (array_get($response->products[0]->stats->current,'16')>0)?round(array_get($response->products[0]->stats->current,'16')/10,1):0;
					if(!empty($response->products[0]->promotions) && count($response->products[0]->promotions)>0) $data['promotions'] = json_encode(json_decode(json_encode($response->products[0]->promotions), true));
					
					if(!empty($response->products[0]->coupon) && count($response->products[0]->coupon)>0) $data['coupon'] = json_encode(json_decode(json_encode($response->products[0]->coupon), true));
					if(!empty($response->products[0]->buyBoxSellerIdHistory) && count($response->products[0]->buyBoxSellerIdHistory)>0) $data['buybox_sellerid'] = end($response->products[0]->buyBoxSellerIdHistory);
				}
		}catch(Exception $ex){
			throw $ex;
		}
		$data['updated_at'] = Carbon::now()->toDateTimeString();
		Asin::where('asin',$this->asin->asin)->where('marketplaceid',$this->asin->marketplaceid)->update($data);	
    }
}
