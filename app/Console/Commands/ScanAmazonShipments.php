<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Jobs\GetShipmentsForAccount;
use App\SellerAccounts;
class ScanAmazonShipments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'scan:shipments {--sellerId=} {--afterDate=} {--beforeDate=}';

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
        $this->info('Scan Shipment started');
        $sellerAccounts = SellerAccounts::where('primary',1)->whereNull('deleted_at');
        if($this->option('sellerId')) $sellerAccounts=$sellerAccounts->where('mws_seller_id',$this->option('sellerId'));
		$sellerAccounts->chunk(10,function($sellers){
            foreach ($sellers as $seller) {
				GetShipmentsForAccount::dispatch($seller,$this->option('afterDate'),$this->option('beforeDate'))->onConnection('beanstalkd-shedule-get')->onQueue('beanstalkd-shedule-get');
            }
        });
        
    }
}
