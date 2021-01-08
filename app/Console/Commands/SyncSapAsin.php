<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\SapAsinMatchSku;
use App\SapAccount;
use App\Classes\SapRfcRequest;
class SyncSapAsin extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:asins {--afterDate=} {--beforeDate=}';

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
		$afterDate = $this->option('afterDate')?date('Ymd',strtotime($this->option('afterDate'))):date('Ymd',strtotime('- 10years'));
		$beforeDate = $this->option('beforeDate')?date('Ymd',strtotime($this->option('beforeDate'))):date('Ymd');
        $sap = new SapRfcRequest();
	
        $data['postdata']['EXPORT']=array('O_MSG'=>'','O_FLAG'=>'');
        $data['postdata']['TABLE']=array('O_TAB'=>array(0));
        $data['postdata']['IMPORT']=array('I_DATE_S'=>$afterDate,'I_DATE_E'=>$beforeDate);
        $res = $sap->ZFMPHPRFC019($data);
        $m_arr = siteToMarketplaceid();
		$account_to_sellerid = SapAccount::pluck('seller_id','sap_account_code');
        if(array_get($res,'ack')==1 && array_get($res,'data.O_FLAG')=='X'){
            $requestTime=date('Y-m-d H:i:s');
            $asinList = array_get($res,'data.O_TAB');
            foreach($asinList as $asin){
                SapAsinMatchSku::updateOrCreate([
                    'asin'=>trim(array_get($asin,'ASIN','')),
                    'seller_id' => array_get($account_to_sellerid,trim(array_get($asin,'KUNNR','')),trim(array_get($asin,'KUNNR',''))),
                    'marketplace_id' => (isset($m_arr['www.'.strtolower(trim(array_get($asin,'SITE','')))])?$m_arr['www.'.strtolower(trim(array_get($asin,'SITE','')))]:'www.'.strtolower(trim(array_get($asin,'SITE','')))),
                    'seller_sku'=> trim(array_get($asin,'SELLER_SKU',''))],
                    [
                    'sku' => trim(array_get($asin,'MATNR','')),
                    'status' => trim(array_get($asin,'ZSTATUS','')),
                    'sku_status' => intval(array_get($asin,'MATNRZT',0)),
                    'sku_group' => trim(array_get($asin,'MATKL','')),
                    'actived' => 1,
                    'sap_seller_bg' => trim(array_get($asin,'ZBGROUP','')),
                    'sap_seller_bu' => trim(array_get($asin,'ZBUNIT','')),
                    'sap_seller_id' => trim(array_get($asin,'VKGRP','')),
                    
                    'sap_warehouse_code' => trim(array_get($asin,'LGORT','')),
                    'sap_factory_code' => trim(array_get($asin,'WERKS','')),
                    'sap_shipment_code' => trim(array_get($asin,'SDABW','')),
                    'updated_at'=> $requestTime
                    ]
                );
            }
            SapAsinMatchSku::where('updated_at','<',$requestTime)->delete();//->update(['actived'=>0]);
        }

        
    }
}
