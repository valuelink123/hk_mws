<?php

namespace App\Console\Commands;

use App\Commands\GetFbForAccount;
use Illuminate\Console\Command;
use App\SellerAccounts;

class ScanAmazonFeedback extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'scan:feedback {--sellerId=} {--marketplaceId=} {--afterDate=} {--beforeDate=}';

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
        if($this->option('marketplaceId')) $sellerAccounts=$sellerAccounts->where('mws_marketplaceid',$this->option('marketplaceId'));
        $sellerAccounts->chunk(50,function($sellers){
            foreach ($sellers as $seller) {
                //MultipleQueue::pushOn(MultipleQueue::SCHEDULE_GET,
                new GetFbForAccount($seller,$this->option('afterDate'),$this->option('beforeDate'));
                //);
            }
        });
    }
}
