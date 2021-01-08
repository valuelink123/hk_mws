<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\SapPurchase;
use App\SapAccount;
use App\Classes\SapRfcRequest;
class SyncSapPurchase extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:purchases {--purchase_ids=}';

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
		$purchase_ids = explode(',',$this->option('purchase_ids'));
		$ids=[];
		foreach($purchase_ids as $key=>$purchase_id){
			$ids[$key]=array('EBELN'=>$purchase_id);
		}
        $data['postdata']['EXPORT']=array('O_MESSAGE'=>'','O_RETURN'=>'');
        $data['postdata']['TABLE']=array('RESULT_TABLE'=>array(0),'GT_TABLE'=>$ids);
		$sap = new SapRfcRequest();
        $res = $sap->ZMM_GET_PO_DETAILS($data);
        if(array_get($res,'ack')==1 && array_get($res,'data.O_RETURN')=='S'){
            $requestTime=date('Y-m-d H:i:s');
            $lists = array_get($res,'data.RESULT_TABLE');
            foreach($lists as $list){
                SapPurchase::updateOrCreate([
                    'sap_purchase_id'=>trim(array_get($list,'EBELN','')),
                    'line_num' => intval(array_get($list,'EBELP',0))],
                    [
                    'sku' => trim(array_get($list,'MATNR','')),
                    'quantity' => intval(array_get($list,'MENGE',0)),
                    'estimated_delivery_date' => ((intval(array_get($list,'EINDT',0))==0)?NULL:array_get($list,'EINDT',0)),
                    'actual_delivery_date' => ((intval(array_get($list,'BUDAT',0))==0)?NULL:array_get($list,'BUDAT',0)),
                    'sap_factory_code' => trim(array_get($list,'WERKS','')),
                    'sap_warehouse_code' => trim(array_get($list,'LGORT','')),
                    'price' => round(array_get($list,'NETPR',0),4),
                    'amount' => round(array_get($list,'NETWR',0),4),
                    'unit' => trim(array_get($list,'PEINH','')),
                    'completed' => trim(array_get($list,'OBMNG','')),
                    'currency' => trim(array_get($list,'WAERS','')),
                    'supplier' => trim(array_get($list,'LIFNR','')),
					'voucher_date' => ((intval(array_get($list,'BEDAT',0))==0)?NULL:array_get($list,'BEDAT',0)),
                    'purchase_group' => trim(array_get($list,'EKGRP','')),
                    'updated_at'=> $requestTime
                    ]
                );
            }
        }

        
    }
}
