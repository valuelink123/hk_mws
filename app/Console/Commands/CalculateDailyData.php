<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\DailyStatistic;
use App\FinancesShipmentEvent;
use App\FinancesRefundEvent;
use App\SellerAccounts;
use App\CurrencyRate;
use App\SellerSku;
use App\AmazonReturn;
use App\SapAsinMatchSku;
use App\SapSkuSite;
use App\ViewCostOfSku;
use App\Asin;
use App\Order;
use App\AsinMatchRelation;
class CalculateDailyData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cal:dailydata {--date=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $date =  $this->option('date');
        if(!$date){
            $date=date('Y-m-d',strtotime('-1 days'));
        }else{
            $date=date('Y-m-d',strtotime($date));    
        }
        
        $rates = CurrencyRate::pluck('rate','currency');//增加汇率接口后取$date当日汇率
        $seller_ids = SellerAccounts::pluck('mws_seller_id','id');
		$seller_marketplace_ids = SellerAccounts::pluck('mws_marketplaceid','id');
		$seller_account_ids = SellerAccounts::selectRaw('mws_seller_id,group_concat(id) as ids')->groupBy('mws_seller_id')->pluck('ids','mws_seller_id')->toArray();
		$amount_key_arr = ['Principal','CostOfPointsGranted','GiftWrap','GiftWrapTax','PaymentMethodFee','ShippingCharge','ShippingTax','Tax','LowValueGoodsTax-Principal','LowValueGoodsTax-Shipping','MarketplaceFacilitatorTax-Other','MarketplaceFacilitatorTax-Principal','MarketplaceFacilitatorTax-Shipping','PromotionMetaDataDefinitionValue'];
		
		$fulfillment_key_arr = ['FBAPerUnitFulfillmentFee','CODChargeback','GiftwrapChargeback','ShippingChargeback'];
		
		$commission_key_arr = ['Commission','ShippingHB'];
        $insertData = [];
 
        $shipments = FinancesShipmentEvent::selectRaw("seller_account_id,seller_sku,marketplace_name,type,currency,sum(quantity_shipped) as quantity_shipped,
        sum(amount) as amount")->whereRaw("date(posted_date)='$date'")->groupBy(['seller_account_id','seller_sku','marketplace_name','type','currency'])->get()->toArray();
        foreach($shipments as $shipment){
            $seller_id = array_get($seller_ids,$shipment['seller_account_id'],$shipment['seller_account_id']);
			
            $marketplace_id = array_get(siteToMarketplaceid(),strtolower($shipment['marketplace_name']),self::match_non_amazon($shipment));
            $key = $seller_id.'-'.$marketplace_id.'-'.$shipment['seller_sku'];
            $insertData[$key]['seller_account_id']=$shipment['seller_account_id'];
            $insertData[$key]['seller_id']=$seller_id;
            $insertData[$key]['marketplace_id']=$marketplace_id;
            $insertData[$key]['seller_sku']=$shipment['seller_sku'];
			if(!isset($insertData[$key]['income'])) $insertData[$key]['income']=0;
            if(!isset($insertData[$key]['amount_income'])) $insertData[$key]['amount_income']=0;
            if(!isset($insertData[$key]['quantity_shipped'])) $insertData[$key]['quantity_shipped']=0;
			if(!isset($insertData[$key]['fulfillment_fee'])) $insertData[$key]['fulfillment_fee']=0;
            if(!isset($insertData[$key]['commission'])) $insertData[$key]['commission']=0;
			if(!isset($insertData[$key]['other_fee'])) $insertData[$key]['other_fee']=0;

			if(in_array($shipment['type'],$amount_key_arr)){
				$insertData[$key]['income']+=round($shipment['amount']*array_get($rates,$shipment['currency']),4);
				if($shipment['type'] == 'Principal'){
					$insertData[$key]['quantity_shipped']+=intval($shipment['quantity_shipped']);
				}
			}elseif(in_array($shipment['type'],$fulfillment_key_arr)){
				$insertData[$key]['fulfillment_fee']+=round($shipment['amount']*array_get($rates,$shipment['currency']),4);
				if(strtolower(substr($shipment['marketplace_name'],0,6))!='amazon' && $shipment['type']=='FBAPerUnitFulfillmentFee') $insertData[$key]['quantity_shipped']+=intval($shipment['quantity_shipped']);
			}elseif(in_array($shipment['type'],$commission_key_arr)){
				$insertData[$key]['commission']+=round($shipment['amount']*array_get($rates,$shipment['currency']),4);
			}else{
				$insertData[$key]['other_fee']+=round($shipment['amount']*array_get($rates,$shipment['currency']),4);
			}
			$insertData[$key]['amount_income']+=round($shipment['amount']*array_get($rates,$shipment['currency']),4);
        }
		
		unset($shipments);

        $refunds =  FinancesRefundEvent::selectRaw("seller_account_id,seller_sku,marketplace_name,type,currency,
        sum(amount) as amount")->whereRaw("date(posted_date)='$date'")->groupBy(['seller_account_id','seller_sku','marketplace_name','type','currency'])->get()->toArray();
        foreach($refunds as $refund){
            $seller_id = array_get($seller_ids,$refund['seller_account_id'],$refund['seller_account_id']);
            $marketplace_id = array_get(siteToMarketplaceid(),strtolower($refund['marketplace_name']),strtolower($refund['marketplace_name']));
            $key = $seller_id.'-'.$marketplace_id.'-'.$refund['seller_sku'];

            $insertData[$key]['seller_account_id']=$refund['seller_account_id'];
            $insertData[$key]['seller_id']=$seller_id;
            $insertData[$key]['marketplace_id']=$marketplace_id;
            $insertData[$key]['seller_sku']=$refund['seller_sku'];

            if(!isset($insertData[$key]['amount_refund'])) $insertData[$key]['amount_refund']=0;
			if(!isset($insertData[$key]['income_refund'])) $insertData[$key]['income_refund']=0;
			if(!isset($insertData[$key]['fulfillment_fee_refund'])) $insertData[$key]['fulfillment_fee_refund']=0;
            if(!isset($insertData[$key]['commission_refund'])) $insertData[$key]['commission_refund']=0;
			if(!isset($insertData[$key]['other_fee_refund'])) $insertData[$key]['other_fee_refund']=0;
			
			if(in_array($refund['type'],$amount_key_arr)){
				$insertData[$key]['income_refund']+=round($refund['amount']*array_get($rates,$refund['currency']),4);
			}elseif(in_array($refund['type'],$fulfillment_key_arr)){
				$insertData[$key]['fulfillment_fee_refund']+=round($refund['amount']*array_get($rates,$refund['currency']),4);		
			}elseif(in_array($refund['type'],$commission_key_arr)){
				$insertData[$key]['commission_refund']+=round($refund['amount']*array_get($rates,$refund['currency']),4);
			}else{
				$insertData[$key]['other_fee_refund']+=round($refund['amount']*array_get($rates,$refund['currency']),4);
			}
            $insertData[$key]['amount_refund']+=round($refund['amount']*array_get($rates,$refund['currency']),4);
        }
		
		unset($refunds);
		$returns =  AmazonReturn::whereRaw("date(return_date)='$date' and status<>'Reimbursed'")->get()->toArray();
		foreach($returns as $return){
			$seller_id = array_get($seller_ids,$return['seller_account_id'],$return['seller_account_id']);
			$cur_seller_id = Order::whereIn('seller_account_id',explode(',',array_get($seller_account_ids,$seller_id,$return['seller_account_id'])))->where('amazon_order_id',$return['amazon_order_id'])->value('seller_account_id');
			if(!$cur_seller_id) $cur_seller_id = $return['seller_account_id']; 
			$marketplace_id = array_get($seller_marketplace_ids,$cur_seller_id);
            $key = $seller_id.'-'.$marketplace_id.'-'.$return['seller_sku'];
			
			$insertData[$key]['seller_account_id']=$return['seller_account_id'];
            $insertData[$key]['seller_id']=$seller_id;
            $insertData[$key]['marketplace_id']=$marketplace_id;
            $insertData[$key]['seller_sku']=$return['seller_sku'];
			
			if(!isset($insertData[$key]['quantity_returned'])) $insertData[$key]['quantity_returned']=0;
			$insertData[$key]['quantity_returned']+=intval($return['quantity']);
		}
		
		unset($returns);
		
		$afn_stocks = SellerSku::whereRaw('(afn_total-afn_unsellable)>0')->get()->toArray();
		
		foreach($afn_stocks as $afn_stock){
			$seller_id = array_get($seller_ids,$afn_stock['seller_account_id'],$afn_stock['seller_account_id']);
            $marketplace_id = $afn_stock['marketplaceid'];

			$key = $seller_id.'-'.$marketplace_id.'-'.$afn_stock['seller_sku'];
			$insertData[$key]['seller_account_id']=$afn_stock['seller_account_id'];
			$insertData[$key]['seller_id']=$seller_id;
			$insertData[$key]['marketplace_id']=$marketplace_id;
			$insertData[$key]['seller_sku']=$afn_stock['seller_sku'];
			$insertData[$key]['asin']=$afn_stock['asin'];
			$insertData[$key]['afn_sellable']=intval($afn_stock['afn_sellable']);
			$insertData[$key]['afn_reserved']=intval($afn_stock['afn_reserved']);
			$insertData[$key]['afn_unsellable']=intval($afn_stock['afn_unsellable']);
			$insertData[$key]['afn_transfer']=intval($afn_stock['afn_transfer']);
			$insertData[$key]['afn_total']=intval($afn_stock['afn_total']);

            
		}
		
		unset($afn_stocks);
		
		
		
        foreach($insertData as $key=>$value){
            $match_asin = array_get($value,'asin');
			$match_sku_obj = SapAsinMatchSku::where('seller_id',$value['seller_id'])->where('seller_sku',$value['seller_sku'])->where('marketplace_id',$value['marketplace_id'])->where('actived',1)->first();
            if(empty($match_sku_obj)) $match_sku_obj = AsinMatchRelation::where('seller_id',$value['seller_id'])->where('seller_sku',$value['seller_sku'])->where('marketplace_id',$value['marketplace_id'])->first();
			
			if(!empty($match_sku_obj)){
				$match_sku = $match_sku_obj->sku;
				if(!$match_asin) $match_asin = $match_sku_obj->asin;
			}else{
				$match_sku_obj = SapAsinMatchSku::where('seller_sku',$value['seller_sku'])->whereRaw("(marketplace_id='".$value['marketplace_id']."' or seller_id='".$value['seller_id']."')")->where('actived',1)->first();
				if(empty($match_sku_obj)) $match_sku_obj = AsinMatchRelation::where('seller_sku',$value['seller_sku'])->whereRaw("(marketplace_id='".$value['marketplace_id']."' or seller_id='".$value['seller_id']."')")->first();
				if(!empty($match_sku_obj)){
					$match_sku = $match_sku_obj->sku;
					if(!$match_asin) $match_asin = $match_sku_obj->asin;
				}else{
					$match_sku = NULL;
				}
			}
			
			if($match_sku){
				$stock_where_array = array_get(getMarketplaceCode(),$value['marketplace_id'].'.fbm_factory_warehouse',[]);
				$stock_where = [];
				foreach($stock_where_array as $kfw => $fw){
					$stock_where[]="(sap_factory_code='".$fw['sap_factory_code']."' and sap_warehouse_code='".$fw['sap_warehouse_code']."')";
				}
				if($stock_where){
					$value['mfn_sellable'] = SapSkuSite::where('sku',$match_sku)->where('marketplace_id',$value['marketplace_id'])->whereRaw('('.implode(' or ',$stock_where).')')->sum('quantity');
				}else{
					$value['mfn_sellable'] = 0;
				}
					
				$stock_where_array = array_get(getMarketplaceCode(),$value['marketplace_id'].'.fba_factory_warehouse',[]);
				$stock_where = [];
				foreach($stock_where_array as $kfw => $fw){
					$stock_where[]="(sap_factory_code='".$fw['sap_factory_code']."' and sap_warehouse_code='".$fw['sap_warehouse_code']."')";
				}
				if($stock_where){
					$value['cost'] = SapSkuSite::where('sku',$match_sku)->where('marketplace_id',$value['marketplace_id'])->whereRaw('('.implode(' or ',$stock_where).')')->value('cost');
				}else{
					$value['cost'] = 0;
				}
			}
			
			$table_update_data = $done_asins =[];
			$total_in = abs(array_get($value,'other_fee'))+abs(array_get($value,'commission'))+abs(array_get($value,'fulfillment_fee'))+abs(array_get($value,'income'));
			if($total_in>0 && abs(array_get($value,'commission'))>0){
				$table_update_data['commission'] = round(abs(array_get($value,'commission'))/$total_in,4);
			}
			if(intval(array_get($value,'quantity_shipped'))>0 && abs(array_get($value,'fulfillment_fee'))>0 && array_get($rates,array_get(getSiteCur(),$value['marketplace_id']))>0){
				$table_update_data['fulfillment'] = round(abs(array_get($value,'fulfillment_fee'))/intval(array_get($value,'quantity_shipped'))/array_get($rates,array_get(getSiteCur(),$value['marketplace_id'])),4);
			}
			if($table_update_data){
				$cur_seller_account_id=SellerAccounts::where('mws_seller_id',$value['seller_id'])->where('mws_marketplaceid',$value['marketplace_id'])->value('id');
				SellerSku::where('seller_account_id',$cur_seller_account_id)->where('marketplaceid',$value['marketplace_id'])->where('seller_sku',$value['seller_sku'])->update($table_update_data);
				
				if($match_asin && !in_array($match_asin.':'.$value['marketplace_id'],$done_asins)){
					Asin::where('asin',$match_asin)->where('marketplaceid',$value['marketplace_id'])->update($table_update_data);
					$done_asins[]=$match_asin.':'.$value['marketplace_id'];
				}
				
			}
			
			unset($done_asins);unset($table_update_data);

            DailyStatistic::updateOrCreate(
                [
                    'seller_id'=>$value['seller_id'],
                    'marketplace_id' => $value['marketplace_id'],
                    'seller_sku' => $value['seller_sku'],
                    'date'=> $date
                ],
                [
                    'sku' => $match_sku,
                    'asin' => $match_asin,
                    'quantity_shipped' => intval(array_get($value,'quantity_shipped')),
                    'amount_income' => round(array_get($value,'amount_income'),4),
                    'income' => round(array_get($value,'income'),4),
					'fulfillment_fee' => round(array_get($value,'fulfillment_fee'),4),
                    'commission' => round(array_get($value,'commission'),4),
                    'other_fee' => round(array_get($value,'other_fee'),4),
					
					'amount_refund' => round(array_get($value,'amount_refund'),4),
                    'income_refund' => round(array_get($value,'income_refund'),4),
					'fulfillment_fee_refund' => round(array_get($value,'fulfillment_fee_refund'),4),
                    'commission_refund' => round(array_get($value,'commission_refund'),4),
                    'other_fee_refund' => round(array_get($value,'other_fee_refund'),4),
					
					'quantity_returned' => intval(array_get($value,'quantity_returned')),
					'cost' => round((intval(array_get($value,'quantity_shipped'))-intval(array_get($value,'quantity_returned',0)))*array_get($value,'cost'),4),
					'afn_sellable' => intval(array_get($value,'afn_sellable')),
                    'afn_reserved' => intval(array_get($value,'afn_reserved')),
					'afn_unsellable' => intval(array_get($value,'afn_unsellable')),
					'afn_transfer' => intval(array_get($value,'afn_transfer')),
					'afn_total' => intval(array_get($value,'afn_total')),
					
					'mfn_sellable' => intval(array_get($value,'mfn_sellable')),
                ]
            );

        }
		
		//统计过去28天 14天 7天销量
		$date_4_weeks = date('Y-m-d',strtotime('- 28days'));
		$date_2_weeks = date('Y-m-d',strtotime('- 14days'));
		$date_1_weeks = date('Y-m-d',strtotime('- 7days'));
		$sellersku_sales_data = $asin_sales_data =[];
		$sales_history = DailyStatistic::selectRaw("seller_accounts.id as seller_account_id,daily_statistics.marketplace_id,daily_statistics.seller_sku,daily_statistics.asin,daily_statistics.date,daily_statistics.quantity_shipped")->leftjoin('seller_accounts',function($q){
			$q->on('daily_statistics.seller_id', '=', 'seller_accounts.mws_seller_id')
			  ->on('daily_statistics.marketplace_id', '=', 'seller_accounts.mws_marketplaceid');
		})->where('date','>=',$date_4_weeks)->get()->toArray();
		foreach($sales_history as $sale){
		
			if(!isset($sellersku_sales_data[$sale['seller_account_id'].':'.$sale['seller_sku'].':'.$sale['marketplace_id']]['sales_4_weeks'])) $sellersku_sales_data[$sale['seller_account_id'].':'.$sale['seller_sku'].':'.$sale['marketplace_id']]['sales_4_weeks']=0;
			
			$sellersku_sales_data[$sale['seller_account_id'].':'.$sale['seller_sku'].':'.$sale['marketplace_id']]['sales_4_weeks']+=$sale['quantity_shipped'];
			
			if(!isset($asin_sales_data[$sale['asin'].':'.$sale['marketplace_id']]['sales_4_weeks'])) $asin_sales_data[$sale['asin'].':'.$sale['marketplace_id']]['sales_4_weeks']=0;
			
			$asin_sales_data[$sale['asin'].':'.$sale['marketplace_id']]['sales_4_weeks']+=$sale['quantity_shipped'];
			
			if($sale['date']>=$date_2_weeks){
				if(!isset($sellersku_sales_data[$sale['seller_account_id'].':'.$sale['seller_sku'].':'.$sale['marketplace_id']]['sales_2_weeks'])) $sellersku_sales_data[$sale['seller_account_id'].':'.$sale['seller_sku'].':'.$sale['marketplace_id']]['sales_2_weeks']=0;
			
				$sellersku_sales_data[$sale['seller_account_id'].':'.$sale['seller_sku'].':'.$sale['marketplace_id']]['sales_2_weeks']+=$sale['quantity_shipped'];
				
				if(!isset($asin_sales_data[$sale['asin'].':'.$sale['marketplace_id']]['sales_2_weeks'])) $asin_sales_data[$sale['asin'].':'.$sale['marketplace_id']]['sales_2_weeks']=0;
				
				$asin_sales_data[$sale['asin'].':'.$sale['marketplace_id']]['sales_2_weeks']+=$sale['quantity_shipped'];		
			}
			
			if($sale['date']>=$date_1_weeks){
				if(!isset($sellersku_sales_data[$sale['seller_account_id'].':'.$sale['seller_sku'].':'.$sale['marketplace_id']]['sales_1_weeks'])) $sellersku_sales_data[$sale['seller_account_id'].':'.$sale['seller_sku'].':'.$sale['marketplace_id']]['sales_1_weeks']=0;
			
				$sellersku_sales_data[$sale['seller_account_id'].':'.$sale['seller_sku'].':'.$sale['marketplace_id']]['sales_1_weeks']+=$sale['quantity_shipped'];
				
				if(!isset($asin_sales_data[$sale['asin'].':'.$sale['marketplace_id']]['sales_1_weeks'])) $asin_sales_data[$sale['asin'].':'.$sale['marketplace_id']]['sales_1_weeks']=0;
				
				$asin_sales_data[$sale['asin'].':'.$sale['marketplace_id']]['sales_1_weeks']+=$sale['quantity_shipped'];
			}
			
		}
		unset($sales_history);
		$requestTime=date('Y-m-d H:i:s');
		if($sellersku_sales_data){
		
			foreach($sellersku_sales_data as $k=>$v){
				$key = explode(':',$k);
				if(count($key)!=3) continue;
				$v['sales_updated_at']=$requestTime;
				SellerSku::where('seller_account_id',$key[0])->where('seller_sku',$key[1])->where('marketplaceid',$key[2])->update($v);
			}
			
			SellerSku::where('sales_updated_at','<',$requestTime)->update(['sales_4_weeks'=>0,'sales_2_weeks'=>0,'sales_1_weeks'=>0,'sales_updated_at'=>$requestTime]);
		}
		
		if($asin_sales_data){
			foreach($asin_sales_data as $k=>$v){
				$key = explode(':',$k);
				if(count($key)!=2) continue;
				$v['sales_updated_at']=$requestTime;
				Asin::where('asin',$key[0])->where('marketplaceid',$key[1])->update($v);
			}
			Asin::where('sales_updated_at','<',$requestTime)->update(['sales_4_weeks'=>0,'sales_2_weeks'=>0,'sales_1_weeks'=>0,'sales_updated_at'=>$requestTime]);
		}
		
        
    }
	public function match_non_amazon($shipment){
		$match_site = FinancesShipmentEvent::where('seller_account_id',$shipment['seller_account_id'])->where('seller_sku',$shipment['seller_sku'])->where('currency',$shipment['currency'])->where('marketplace_name','<>','Non-Amazon')->value('marketplace_name');
		if(!$match_site){
			$match_site = SellerAccounts::where('id',$shipment['seller_account_id'])->where('primary',1)->value('mws_marketplaceid');
		}else{
			$match_site = array_get(siteToMarketplaceid(),strtolower($match_site));
		}
		return $match_site;
	}
	
}
