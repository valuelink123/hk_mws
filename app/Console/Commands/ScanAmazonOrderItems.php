<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Jobs\GetOrderItemsForAccount;
use App\SellerAccounts;
class ScanAmazonOrderItems extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'scan:orderItems {--sellerId=}';

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
        $sellerAccounts = SellerAccounts::whereNull('deleted_at')->whereNull('get_items');
        if($this->option('sellerId')) $sellerAccounts=$sellerAccounts->where('mws_seller_id',$this->option('sellerId'));
		$sellers = $sellerAccounts->get();
        foreach ($sellers as $seller) {
            $seller->get_items=date("Y-m-d H:i:s");
            $seller->save();
            GetOrderItemsForAccount::dispatch($seller)->onConnection('beanstalkd-orderitems-get')->onQueue('beanstalkd-orderitems-get');
        }
        
    }
}
