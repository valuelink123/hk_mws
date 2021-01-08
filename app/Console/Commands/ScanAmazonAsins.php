<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Jobs\KeepaRequest;
use App\Asin;
use App\Services\Traits\MultipleQueue;
use Carbon\Carbon;
use Keepa\API\Request;
use Keepa\API\ResponseStatus;
use Keepa\helper\CSVType;
use Keepa\helper\CSVTypeWrapper;
use Keepa\helper\KeepaTime;
use Keepa\helper\ProductAnalyzer;
use Keepa\helper\ProductType;
use Keepa\KeepaAPI;
use Keepa\objects\AmazonLocale;
use DB;
class ScanAmazonAsins extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'scan:asins';

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
        $products = Asin::select(DB::RAW('asin,marketplaceid'))->orderBy('updated_at','asc');
		$asins = $products->take(5)->get();
		foreach ($asins as $asin) {
			KeepaRequest::dispatch($asin)->onConnection('beanstalkd-keepa-get')->onQueue('beanstalkd-keepa-get');
		}
    }
}
