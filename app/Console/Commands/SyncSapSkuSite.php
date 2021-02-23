<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\SapSkuSite;
use App\SapSku;
use App\SkuForUser;
use App\Classes\SapRfcRequest;
class SyncSapSkuSite extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:skusite {--date=} ';

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
        $date = $this->option('date')?date('Ymd',strtotime($this->option('date'))):date('Ymd',strtotime('+ 8hours'));
		$sap = new SapRfcRequest();
		
		$data['postdata']['EXPORT']=array('O_MSG'=>'','O_FLAG'=>'');
        $data['postdata']['TABLE']=array('O_TAB'=>array(0));
        $data['postdata']['IMPORT']=array('I_MATNR'=>'');
		$requestTime=date('Y-m-d H:i:s');
        $res = $sap->ZFMPHPRFC030($data);
		if(array_get($res,'ack')==1 && array_get($res,'data.O_FLAG')=='X'){
            $lists = array_get($res,'data.O_TAB');
            foreach($lists as $list){
				if(substr(array_get($list,'WERKS',''),0,2)=='HK'){
					SapSkuSite::updateOrCreate([
						'sku'=>trim(array_get($list,'MATNR','')),
						'marketplace_id'=>trim(array_get($list,'WERKS','')).trim(array_get($list,'LGORT','')),
						'sap_factory_code'=>trim(array_get($list,'WERKS','')),
						'sap_warehouse_code'=>trim(array_get($list,'LGORT',''))
						],
						[
						'quantity' => intval(trim(array_get($list,'LABST',''))),
						'updated_at'=> $requestTime
						]
					);
				
				
				}
                
            }
        }
		unset($data);
		
        $data['postdata']['EXPORT']=array('O_MESSAGE'=>'','O_RETURN'=>'');
        $data['postdata']['TABLE']=array('RESULT_TABLE'=>array(0));
        $data['postdata']['IMPORT']=array('IM_PARAM'=>$date);
        $res = $sap->ZMM_GET_MATERIAL_PLANT_STOCK($data);
        $last_date = SkuForUser::selectRaw('max(date) as date')->where('producter','>',0)->value('date');
        if(array_get($res,'ack')==1 && array_get($res,'data.O_RETURN')=='S'){
            $lists = array_get($res,'data.RESULT_TABLE');
            foreach($lists as $list){
                $sku = trim(array_get($list,'MATNR',''));
                $marketplace_id = array_get(siteToMarketplaceid(),trim(array_get($list,'VKBUR','')),trim(array_get($list,'VKBUR','')));
                $status = intval(trim(array_get($list,'MATNRZT','')));
                SapSkuSite::updateOrCreate([
                    'sku'=>$sku,
					'marketplace_id'=>$marketplace_id,
					'sap_factory_code'=>trim(array_get($list,'WERKS','')),
					'sap_warehouse_code'=>trim(array_get($list,'LGORT',''))
					],
                    [
                    'sap_factory_description' => trim(array_get($list,'NAME1','')),
                    'sap_warehouse_description' => trim(array_get($list,'LGOBE','')),
                    'sap_seller_id' => trim(array_get($list,'VKGRP','')),
                    'sap_seller_name' => trim(array_get($list,'BEZEI','')),
                    'sap_seller_bg' => trim(array_get($list,'ZBGROUP','')),
                    'sap_seller_bu' => trim(array_get($list,'ZBUNIT','')),
                    'status' => $status,
                    'level' => trim(array_get($list,'MATNRDJ','')),
                    'planer' => trim(array_get($list,'ZPLANER','')),
					'cost' => round(array_get($list,'VERPR',''),4),
                    'quantity' => intval(trim(array_get($list,'LABST',''))),
                    'updated_at'=> $requestTime
                    ]
                );
                $last_datas = SkuForUser::where('sku',$sku)->where('marketplace_id',$marketplace_id)->where('date',$last_date)
                ->get(['producter','planer','dqe','te'])->first();
				$last_datas = empty($last_datas)?[]:$last_datas->toArray();
                $last_datas['description'] = SapSku::where('sku',$sku)->value('description');
                $last_datas['status'] = $status;
                SkuForUser::updateOrCreate([
                    'sku'=>$sku,
                    'marketplace_id'=>$marketplace_id,
                    'date'=>date('Y-m-d',strtotime($requestTime))
                ],$last_datas);
            }
        }

        
    }
}
