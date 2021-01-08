<?php

namespace App\Console\Commands;
use App\Jobs\PaypalPayment;
use Illuminate\Console\Command;
use App\PaypalAccounts;
use App\PaymentsHistory;
use App\Services\Traits\MultipleQueue;
use PayPal\PayPalAPI\TransactionSearchReq;
use PayPal\PayPalAPI\TransactionSearchRequestType;
use PayPal\Service\PayPalAPIInterfaceServiceService;

class ScanPaymentsHistory extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'scan:payments';

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
		
		
		$accounts = PaypalAccounts::where('status',1)->get();
		foreach ($accounts as $account) {
			PaypalPayment::dispatch($account)->onConnection('beanstalkd-shedule-get')->onQueue('beanstalkd-shedule-get');
		}
		
    }
}
