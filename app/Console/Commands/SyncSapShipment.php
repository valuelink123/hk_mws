<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\SapShipment;
use App\SapAccount;
use App\Classes\SapRfcRequest;
class SyncSapShipment extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:shipments {--shipment_ids=}';

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
        $shipment_ids = explode(',',$this->option('shipment_ids'));
		$ids=[];
		foreach($shipment_ids as $key=>$shipment_id){
			$ids[$key]=array('SHIPID'=>$shipment_id);
		}
        $data['postdata']['EXPORT']=array('O_MESSAGE'=>'','O_RETURN'=>'');
        $data['postdata']['TABLE']=array('RESULT_TABLE'=>array(0),'GT_TABLE'=>$ids);
		$sap = new SapRfcRequest();
        $res = $sap->ZTM_GET_TM_DETAILS($data);

        if(array_get($res,'ack')==1 && array_get($res,'data.O_RETURN')=='S'){
            $requestTime=date('Y-m-d H:i:s');
            $lists = array_get($res,'data.RESULT_TABLE');
            foreach($lists as $list){
                SapShipment::updateOrCreate(
					[
                    'sap_shipment_id'=>trim(array_get($list,'SHIPID',''))],
                    [
                    'tm' => trim(array_get($list,'TKNUM','')),
                    'op' => trim(array_get($list,'ERNAM','')),
                    'sto' => trim(array_get($list,'EBELN','')),
                    'dn' => trim(array_get($list,'VBELN','')),
                    'delivery_posting' => trim(array_get($list,'BWART_641','')),
                    'receipt_posting' => trim(array_get($list,'BWART_101','')),
                    'sap_factory_code' => trim(array_get($list,'WERKS','')),
                    'seller_id' => trim(array_get($list,'AMZZH','')),
                    'post' => trim(array_get($list,'AULWE','')),
                    'post_description' => trim(array_get($list,'BEZEI1','')),
                    'tax_refund' => trim(array_get($list,'WRKST','')),
                    'order_id' => trim(array_get($list,'MDH','')),
					'sub_order_id' => trim(array_get($list,'ZDH')),
                    'logistics' => trim(array_get($list,'TDLNR','')),
					
					'logistics_description' => trim(array_get($list,'SORTL','')),
                    'channel' => trim(array_get($list,'VSART','')),
                    'channel_description' => trim(array_get($list,'BEZEI','')),
                    'logistics_status' => trim(array_get($list,'WLZT','')),
					
					'demand_date' => ((intval(array_get($list,'REDAT',0))==0)?NULL:array_get($list,'REDAT',0)),
                    'delivery_date' => ((intval(array_get($list,'DATBG',0))==0)?NULL:array_get($list,'DATBG',0)),
                    'etd_estimated' => ((intval(array_get($list,'ETD_P',0))==0)?NULL:array_get($list,'ETD_P',0)),
                    'etd_actual' => ((intval(array_get($list,'ETD',0))==0)?NULL:array_get($list,'ETD',0)),
					
					'eta_estimated' => ((intval(array_get($list,'ETA_P',0))==0)?NULL:array_get($list,'ETA_P',0)),
                    'eta_actual' => ((intval(array_get($list,'ETA',0))==0)?NULL:array_get($list,'ETA',0)),
                    'clear_date_estimated' => ((intval(array_get($list,'QGDATE_P',0))==0)?NULL:array_get($list,'QGDATE_P',0)),
                    'clear_date_actual' => ((intval(array_get($list,'QGDATE',0))==0)?NULL:array_get($list,'QGDATE',0)),
					
					'delivery_date_estimated' => ((intval(array_get($list,'PSDATE_P',0))==0)?NULL:array_get($list,'PSDATE_P',0)),
                    'delivery_date_actual' => ((intval(array_get($list,'PSDATE',0))==0)?NULL:array_get($list,'PSDATE',0)),
                    'sign_date_estimated' => ((intval(array_get($list,'FBAQS_P',0))==0)?NULL:array_get($list,'FBAQS_P',0)),
                    'sign_date_actual' => ((intval(array_get($list,'FBAQS',0))==0)?NULL:array_get($list,'FBAQS',0)),
					
					'add_date_estimated' => ((intval(array_get($list,'FBASJ_P',0))==0)?NULL:array_get($list,'FBASJ_P',0)),
                    'add_date_actual' => ((intval(array_get($list,'FBASJ',0))==0)?NULL:array_get($list,'FBASJ',0)),
                    'estimated_aging' => trim(array_get($list,'ZSX','')),
                    'actual_aging' => trim(array_get($list,'SJZSX','')),
					
					'dn_amount' => round(trim(array_get($list,'KWERT','')),4),
                    'logistics_amount' => round(trim(array_get($list,'NETWR','')),4),
					
                    'updated_at'=> $requestTime
                    ]
                );
            }
        }

        
    }
}
