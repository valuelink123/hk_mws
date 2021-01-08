<?php

namespace App\Console\Commands;
use App\Jobs\GetInventoryForAccount;
use Illuminate\Console\Command;
use App\SellerAccounts;
class ScanAmazonStock extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'scan:stock {--sellerId=}';

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
				GetInventoryForAccount::dispatch($seller)->onConnection('beanstalkd-shedule-get')->onQueue('beanstalkd-shedule-get');
            }
        });
    }
}
