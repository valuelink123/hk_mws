<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Jobs\GetOrdersForAccount;
use App\SellerAccounts;
class ScanAmazonOrders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'scan:orders {--sellerId=} {--afterDate=} {--beforeDate=}';

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
        $this->info('Scan orders started');
        $sellerAccounts = SellerAccounts::whereNull('deleted_at');
        if($this->option('sellerId')) $sellerAccounts=$sellerAccounts->where('mws_seller_id',$this->option('sellerId'));
		$sellerAccounts->chunk(10,function($sellers){
            foreach ($sellers as $seller) {
				GetOrdersForAccount::dispatch($seller,$this->option('afterDate'),$this->option('beforeDate'))->onConnection('beanstalkd-orders-get')->onQueue('beanstalkd-orders-get');
            }
        });
        
    }
}
