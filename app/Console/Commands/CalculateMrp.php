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
use App\Asin;
use App\AsinSalesPlan;
use App\FbmFbaTransferTime;
use App\FbaFcTransferTime;
use App\ShipmentRequest;
use App\PurchaseRequest;
use App\SapPurchaseRecord;
class CalculateMrp extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cal:mrp {--date=} {--asin=}';

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
		//取所有ASIn
        $skus = Asin::selectRaw("group_concat(asin) as asins,sku,marketplaceid as marketplace_id")->groupBy(['sku','marketplaceid'])->get()->keyBy('sku')->toArray();
		
		//取站点对应FBAM仓库库位
		$factory_warehouse = $sku_mfn_stock = [];
		foreach(getMarketplaceCode() as $k=>$v){
			foreach($v['fba_factory_warehouse'] as $k1=>$v1){
				$factory_warehouse[$k]['afn'][] ="(sap_factory_code = '".$v1['sap_factory_code']."' and sap_warehouse_code = '".$v1['sap_warehouse_code']."')";
				$factory_warehouse[$k]['afn_factory_code'] = $v1['sap_factory_code'];
				$factory_warehouse['afn'][] = $v1['sap_factory_code'];
			}
			foreach($v['fbm_factory_warehouse'] as $k1=>$v1){
				$factory_warehouse[$k]['mfn'][] = $factory_warehouse['mfn'][] ="(sap_factory_code = '".$v1['sap_factory_code']."' and sap_warehouse_code = '".$v1['sap_warehouse_code']."')";
			}
			$factory_warehouse[$k]['mfn'][] = $factory_warehouse['mfn'][] = "(left(sap_factory_code,2)='HK')";
		}
		//获取所有FBM库存
		$skus_mfn_sellable = SapSkuSite::where('quantity','>',0)->whereRaw("(".implode(' or ',$factory_warehouse['mfn']).")")->get()->toArray();
		foreach($skus_mfn_sellable as $mfn_sellable){
			$sku_mfn_stock[$mfn_sellable['sku']][$mfn_sellable['sap_factory_code']] = $mfn_sellable['quantity'];
		}
		
		//获取调拨时效顺序
		$dsts  = FbmFbaTransferTime::whereIn('sap_factory_code_inbound',$factory_warehouse['afn'])->where('transfer_time','>',0)->orderBy('transfer_time','asc')->get()->toArray();
		foreach($dsts as $dst){
			$transfer_time[$dst['sap_factory_code_inbound']][$dst['sap_factory_code_outbound']] = $dst['transfer_time'];
		}
		
		
		//获取FBA上架时效
		$dsts  = FbaFcTransferTime::get()->toArray();
		
		$sitecode_to_marketplaceid=array_flip(getSiteCountryCode());
		
		foreach($dsts as $dst){
			if($dst['estimated_month_to_fba']==0) $dst['estimated_month_to_fba']=7;
			if($dst['estimated_month_to_fba']==1) $dst['estimated_month_to_fba']=11;
			if($dst['estimated_month_to_fba']==2) $dst['estimated_month_to_fba']=12;
			if($dst['estimated_month_to_fba']==3) $dst['estimated_month_to_fba']=0;
			$fba_fc_time[array_get($sitecode_to_marketplaceid,$dst['site'])][$dst['estimated_month_to_fba']] = $dst['transfer_time'];
		}
		
		
		$date_from = date('Y-m-d');
		$date_to = date('Y-m-d',strtotime("+22 Sunday"));
		$min_p_date = date('Y-m-d',strtotime("next thursday"));
		foreach($skus as $sku=>$asins_val){
			if(!$sku) continue;
			
			
			$asins = explode(',',$asins_val['asins']);
			$marketplace_id = $asins_val['marketplace_id'];
			
			$sku_purchase_info = SapPurchaseRecord::where('sku',$sku)->orderBy('created_date','desc')->where('sap_factory_code','<>','')->first();
			if(!empty($sku_purchase_info)){
				$sku_purchase_info = $sku_purchase_info->toArray();
			}else{
				$sku_purchase_info['sap_factory_code']= array_get($factory_warehouse,$marketplace_id.'.afn_factory_code');
				$sku_purchase_info['estimated_cycle']=45;
			}
			
			
			$sku_info = SapAsinMatchSku::where('sku',$sku)->where('marketplace_id',$marketplace_id)->first()->toArray();
			$sku_status = array_get($sku_info,'sku_status');
			$plans=$distribution_data=[];
			foreach($asins as $asin){
				//是否以填写计划
				$exists_plan = AsinSalesPlan::where('asin',$asin)->where('marketplace_id',$marketplace_id)->where('date','>=',$date_from)->where('date','<=',$date_to)->where('quantity_last','>',0)->count();
				if(!$exists_plan) continue;
				//刷新销售计划其他参数值
				AsinSalesPlan::calPlans($asin,$marketplace_id,$sku,$date_from,$date_to);
				
				$afn_total = SellerSku::selectRaw('sum(afn_sellable+afn_reserved) as afn_total')->where('asin',$asin)->where('marketplaceid',$marketplace_id)->value('afn_total');
				//获取缺货日日期 及 其后一周补货数量
				$plans = AsinSalesPlan::where('asin',$asin)->where('marketplace_id',$marketplace_id)->where('date','>=',$date_from)->where('date','<=',$date_to)->get()->keyBy('date')->toArray();
				$tmp_date = $date_from;
				$distribution_cycle_date = '';
				while($tmp_date<=$date_to){
					$afn_miss = 0;
					if($tmp_date>$distribution_cycle_date){
						$distribution_cycle_date='';
					}
					
					$day_out = array_get($plans,$tmp_date.'.quantity_last',0);
					$day_in = array_get($plans,$tmp_date.'.estimated_afn',0);
					$afn_total = $afn_total-$day_out+$day_in;
					
					if($afn_total<0){
						if(!$distribution_cycle_date){
							$distribution_cycle_date = date('Y-m-d',strtotime($tmp_date)+86400*6);
							$distribution_data[$tmp_date][$asin]=0;
						}
						$afn_miss = abs($afn_total);
						$afn_total=0;	
					}
					
					if($distribution_cycle_date){
						$distribution_data[date('Y-m-d',strtotime($distribution_cycle_date)-86400*6)][$asin] += $afn_miss;
					}
					$tmp_date = date('Y-m-d',strtotime($tmp_date)+86400);
				}
			}
			
			if($distribution_data){
				ksort($distribution_data);
				//获取配货周期顺序
				$transfer_order = array_get($transfer_time,array_get($factory_warehouse,$marketplace_id.'.afn_factory_code'));
				
				foreach($distribution_data as $outstock_date => $as){
					//FBA上架周期获取
					$month_num = in_array(date('n',strtotime($outstock_date)),[7,11,12])?date('n',strtotime($outstock_date)):0;
					
					foreach($as as $as_asin=>$as_miss){
						foreach($transfer_order as $sap_factory_code_outbound=>$tf_time){
						
							if(!$as_miss) break;
							
							if(!isset($sku_mfn_stock[$sku][$sap_factory_code_outbound])) $sku_mfn_stock[$sku][$sap_factory_code_outbound]=0;

							if($sku_mfn_stock[$sku][$sap_factory_code_outbound] > 0){
								
								if(($sku_mfn_stock[$sku][$sap_factory_code_outbound]-$as_miss)>=0){
									$sku_mfn_stock[$sku][$sap_factory_code_outbound] = $sku_mfn_stock[$sku][$sap_factory_code_outbound]-$as_miss;
									$p_quantity=$sku_mfn_stock[$sku][$sap_factory_code_outbound];
									$as_miss=0;	
								}else{
									$p_quantity=$sku_mfn_stock[$sku][$sap_factory_code_outbound];
									$sku_mfn_stock[$sku][$sap_factory_code_outbound]=0;
									$as_miss=abs($sku_mfn_stock[$sku][$sap_factory_code_outbound]-$as_miss);
								}
								//创建配货单
								//计算配货周期得出配货日

								$total_transfer_time = $tf_time+array_get($fba_fc_time,$marketplace_id.'.'.$month_num,7);
								
								$p_date = date('Y-m-d',strtotime(date('Y-m-d',strtotime($outstock_date)-$total_transfer_time*86400)." last thursday"));
								
								if($p_date<$min_p_date) $p_date = $min_p_date;
//								ShipmentRequest::Insert(
//									[
//										'asin' => $as_asin,
//										'marketplace_id' => $marketplace_id,
//										'sap_factory_code'=>array_get($factory_warehouse,$marketplace_id.'.afn_factory_code'),
//										'request_date'=>$p_date,
//										'received_date'=>$outstock_date,
//										'out_warehouse'=>$sap_factory_code_outbound,
//										'type'=>'Mrp',
//										'quantity'=>$p_quantity,
//										'remark'=>'Mrp '.$outstock_date.'缺货调拨',
//										'updated_at'=>date('Y-m-d H:i:s')
//									]
//								);
								
							}
						}
						if($as_miss>0 && $sku_status!=0){
							//创建采购单
							//计算配货周期得出采购日
							
							$total_transfer_time = 14+$sku_purchase_info['estimated_cycle']+array_get($transfer_order,$sku_purchase_info['sap_factory_code'],0)+array_get($fba_fc_time,$marketplace_id.'.'.$month_num,7);
							$p_date = date('Y-m-d',strtotime(date('Y-m-d',strtotime($outstock_date)-$total_transfer_time*86400)." last thursday"));
								
							if($p_date<$min_p_date) $p_date = $min_p_date;
//							PurchaseRequest::updateOrCreate(
//								[
//									'asin' => $as_asin,
//									'sku' =>$sku,
//									'marketplace_id' => $marketplace_id,
//									'sap_factory_code'=>array_get($factory_warehouse,$marketplace_id.'.afn_factory_code'),
//									'request_date'=>$p_date,
//									'type'=>'Mrp'],[
//									'estimated_delivery_date'=>$outstock_date,
//									'quantity'=>$as_miss,
//									'remark'=>'Mrp '.$outstock_date.' 缺货采购',
//									'updated_at'=>date('Y-m-d H:i:s')
//								]
//							);
						}
					}		
				}
			}
		}
	}
	
	
	
}
