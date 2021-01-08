<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\CurrencyRate;
use App\Classes\SapRfcRequest;
class SyncSapRate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:rates';

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
        $sap = new SapRfcRequest();

        $data['postdata']['EXPORT']=array('O_MSG'=>'','O_FLAG'=>'');
        $data['postdata']['TABLE']=array('O_TAB'=>array(0));
        $data['postdata']['IMPORT']=array('I_DATE_S'=>date('Ymd'),'I_DATE_E'=>date('Ymd'));
        $res = $sap->ZFMBIRFC005($data);
        if(array_get($res,'ack')==1 && !empty(array_get($res,'data.O_TAB'))){
            $requestTime=date('Y-m-d H:i:s');
            $lists = array_get($res,'data.O_TAB');
            foreach($lists as $list){
				CurrencyRate::updateOrCreate([
                    'currency'=>trim(array_get($list,'FCURR',''))],
                    [
                    'rate' => round(trim(array_get($list,'UKURS',0))/trim(array_get($list,'FFACT',1)),6),
                    'updated_at'=> $requestTime
                    ]
                );
            }
        }

        
    }
}
