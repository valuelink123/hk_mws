<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\SapPurchaseRecord;
use App\SapAccount;
use App\Classes\SapRfcRequest;
class SyncSapPurchaseRecord extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:purchaserecords {--afterDate=} {--beforeDate=}';

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
		$afterDate = $this->option('afterDate')?date('Ymd',strtotime($this->option('afterDate'))):date('Ymd',strtotime('- 2days'));
		$beforeDate = $this->option('beforeDate')?date('Ymd',strtotime($this->option('beforeDate'))):date('Ymd',strtotime('+ 1days'));
		$sap = new SapRfcRequest();

        $data['postdata']['EXPORT']=array('O_MESSAGE'=>'','O_RETURN'=>'');
        $data['postdata']['TABLE']=array('RESULT_TABLE'=>array(0));
        $data['postdata']['IMPORT']=array('ZSDATE'=>$afterDate,'ZEDATE'=>$beforeDate);
        $res = $sap->ZMM_GET_INFO_DATA($data);
        if(array_get($res,'ack')==1 && array_get($res,'data.O_RETURN')=='S'){
            $requestTime=date('Y-m-d H:i:s');
            $lists = array_get($res,'data.RESULT_TABLE');
            foreach($lists as $list){
                SapPurchaseRecord::updateOrCreate([
                    'sap_purchase_id'=>trim(array_get($list,'INFNR','')),
                    'sap_factory_code' => trim(array_get($list,'WERKS','')),
					'purchase_team' => trim(array_get($list,'EKORG',''))],
                    [
                    'sku' => trim(array_get($list,'MATNR','')),
                    'supplier' => trim(array_get($list,'LIFNR','')),
					'created_date' => trim(array_get($list,'ERDAT','')),
					'supplier_name' => trim(array_get($list,'NAME1','')),
					'purchase_group' => trim(array_get($list,'EKGRP','')),
					'purchaser' => trim(array_get($list,'EKNAM','')),
					'price_unit' => intval(array_get($list,'PEINH','')),
                    'price' => round(array_get($list,'NETPR',''),2),
					'currency' => trim(array_get($list,'WAERS','')),
					'sku_unit' => trim(array_get($list,'LMEIN','')),
					'min_purchase_quantity' => intval(array_get($list,'NORBM','')),
					'order_unit' => trim(array_get($list,'MEINS','')),
					'estimated_cycle' => intval(array_get($list,'APLFZ','')),
                    'updated_at'=> $requestTime
                    ]
                );
            }
        }

        
    }
}
