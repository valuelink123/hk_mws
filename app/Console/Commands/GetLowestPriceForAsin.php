<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Jobs\GetLowestPriceOfferForASIN;
use App\SellerAccounts;
class GetLowestPriceForAsin extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'get:lowestPrice {--sellerId=}';

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

        $sellerAccounts = SellerAccounts::where('primary',1)->whereNull('deleted_at');
        if($this->option('sellerId')) $sellerAccounts=$sellerAccounts->where('id',$this->option('sellerId'));
		$sellerAccounts->chunk(5,function($sellers){
            foreach ($sellers as $seller) {
				GetLowestPriceOfferForASIN::dispatch($seller)->onConnection('beanstalkd-shedule-get')->onQueue('beanstalkd-shedule-get');
            }
        });
        
    }
}
