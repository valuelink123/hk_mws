<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\SapSku;
use App\Classes\SapRfcRequest;
class SyncSapSku extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:skus {--afterDate=} {--beforeDate=}';

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
        $res = $sap->ZMM_GET_MATERIAL_BASE_DATA($data);
        if(array_get($res,'ack')==1 && array_get($res,'data.O_RETURN')=='S'){
            $requestTime=date('Y-m-d H:i:s');
            $lists = array_get($res,'data.RESULT_TABLE');
            foreach($lists as $list){
                SapSku::updateOrCreate([
                    'sku'=>trim(array_get($list,'MATNR',''))
					],
                    [
                    'description' => trim(array_get($list,'MAKTX','')),
                    'description_en' => trim(array_get($list,'MAKTX_EN','')),
                    'sku_group' => trim(array_get($list,'MATKL','')),
                    'sku_group_description' => trim(array_get($list,'WGBEZ','')),
                    'sku_group_description_en' => trim(array_get($list,'WGBEZ60','')),
                    'brand' => trim(array_get($list,'Z_MAT_CPPP','')),
                    'model' => trim(array_get($list,'Z_MAT_CPXH','')),
                    'is_accessories' => ((trim(array_get($list,'Z_MAT_PJ',''))=='是')?1:0),
                    'power_model' => trim(array_get($list,'Z_MAT_DYGG','')),
					'color' => trim(array_get($list,'Z_MAT_YS','')),
                    'gross_weight' => trim(array_get($list,'BRGEW','')),
                    'net_weight' => trim(array_get($list,'NTGEW','')),
                    'unit_of_weight' => trim(array_get($list,'GEWEI','')),
					'length' => trim(array_get($list,'LAENG','')),
                    'width' => trim(array_get($list,'BREIT','')),
                    'height' => trim(array_get($list,'HOEHE','')),
                    'unit_of_length' => trim(array_get($list,'MEABM','')),
					'product_manager_id' => trim(array_get($list,'LABOR','')),
                    'product_manager' => trim(array_get($list,'LBTXT','')),
                    'updated_at'=> $requestTime
                    ]
                );
            }
        }

        
    }
}
