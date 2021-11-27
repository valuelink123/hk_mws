<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\SapAccount;
use App\Classes\SapRfcRequest;
class SyncSapAccount extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:sapAccount {--afterDate=} {--beforeDate=}';

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
		$afterDate = $this->option('afterDate')?date('Ymd',strtotime($this->option('afterDate'))):date('Ymd',strtotime('- 3days'));
		$beforeDate = $this->option('beforeDate')?date('Ymd',strtotime($this->option('beforeDate'))):date('Ymd');
        $sap = new SapRfcRequest();
        $data['postdata']['EXPORT']=array('O_MSG'=>'','O_FLAG'=>'');
        $data['postdata']['TABLE']=array('O_TAB'=>array(0));
        $data['postdata']['IMPORT']=array('I_DATE_S'=>$afterDate,'I_DATE_E'=>$beforeDate);
        $res = $sap->ZFMPHPRFC026($data);
        if(array_get($res,'ack')==1 && array_get($res,'data.O_FLAG')=='X'){
            $requestTime=date('Y-m-d H:i:s');
            $accounts = array_get($res,'data.O_TAB');
            foreach($accounts as $account){
                SapAccount::updateOrCreate(
					[
						'sap_account_code' => trim(array_get($account,'KUNNR','')),
					],
					[
						'sap_account_name' => trim(array_get($account,'SELLER','')),
						'sap_site_code' => trim(array_get($account,'VKBUR','')),
						'sap_deleted' => trim(array_get($account,'ZDELETE','')),
						'sap_updated_date' => trim(array_get($account,'ZDATE','')),
						'seller_id' => trim(array_get($account,'SELLERID','')),
						'site' => trim(array_get($account,'ZFVKBUR','')),
					]
				);
            }
        }
    }
}
