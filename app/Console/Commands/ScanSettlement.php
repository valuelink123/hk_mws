<?php

namespace App\Console\Commands;
use App\Jobs\GetSettlement;
use Illuminate\Console\Command;
use App\SellerAccounts;
use App\RequestReport;
use Carbon\Carbon;
use MarketplaceWebService_Client;
use MarketplaceWebService_Model_GetReportRequestListRequest;
use MarketplaceWebService_Model_IdList;
use MarketplaceWebService_Model_RequestReportRequest;
use MarketplaceWebService_Exception;
use Exception;
use App\Services\Traits\MultipleQueue;
class ScanSettlement extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'request:settlement {--sellerId=} {--type=}';

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
        if($this->option('sellerId')) $sellerAccounts=$sellerAccounts->where('mws_seller_id',$this->option('sellerId'));
        $sellers = $sellerAccounts->get();
        $report_type = $this->option('type')??'_GET_V2_SETTLEMENT_REPORT_DATA_FLAT_FILE_';
		foreach ($sellers as $seller) {
			GetSettlement::dispatch($seller,$report_type)->onConnection('beanstalkd-shedule-get')->onQueue('beanstalkd-shedule-get');
		}
    }
}
