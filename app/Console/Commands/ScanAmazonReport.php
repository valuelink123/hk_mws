<?php

namespace App\Console\Commands;
use App\Jobs\GetReport;
use Illuminate\Console\Command;
use App\RequestReport;
use App\SellerAccounts;
use App\Services\Traits\MultipleQueue;
class ScanAmazonReport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'get:report {--sellerId=} {--type=}';

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
		$sellers = $sellerAccounts->groupBy(['mws_seller_id'])->selectRAW('GROUP_CONCAT(id) as ids')->get();
		foreach ($sellers as $seller) {
			$ids = explode(",", $seller->ids);
			$RequestReport = RequestReport::whereIn('seller_account_id',$ids)->whereIn('status',['_SUBMITTED_','_IN_PROGRESS_']);
			if($this->option('type')) $RequestReport=$RequestReport->where('report_type',$this->option('type'));
			$requests = $RequestReport->orderBy('updated_at','asc')->take(1)->get();
			foreach ($requests as $request) {
				GetReport::dispatch($request)->onConnection('beanstalkd-shedule-get')->onQueue('beanstalkd-shedule-get');
			}
		}
       
    }
}
