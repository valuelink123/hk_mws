<?php

namespace App\Console\Commands;

use App\Jobs\GetFinancesForAccount;
use Illuminate\Console\Command;
use App\SellerAccounts;

class ScanAmazonFinances extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'scan:finances {--sellerId=} {--afterDate=} {--beforeDate=}';

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
        $sellerAccounts = SellerAccounts::whereNull('deleted_at');
        if($this->option('sellerId')) $sellerAccounts=$sellerAccounts->where('mws_seller_id',$this->option('sellerId'));
        $sellerAccounts->where('primary',1)->chunk(10,function($sellers){
            foreach ($sellers as $seller) {
				GetFinancesForAccount::dispatch($seller,$this->option('afterDate'),$this->option('beforeDate'))->onConnection('beanstalkd-shedule-get')->onQueue('beanstalkd-shedule-get');
            }
        });
    }
}
