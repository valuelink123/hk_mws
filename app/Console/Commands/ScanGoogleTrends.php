<?php

namespace App\Console\Commands;
use App\Jobs\GoogleTrends;
use Illuminate\Console\Command;
use App\KeywordsTrend;
use App\Services\Traits\MultipleQueue;
use Google\GTrends;
class ScanGoogleTrends extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'scan:ggtrends';

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
        $date = date('Y-m-d',strtotime('this week')-86400);
        $i=0;
        $keywords = KeywordsTrend::where('actived',1)->where('sku_group','AMAZON')->where('updated_at','<',$date)->get();
        foreach ($keywords as $keyword) {
            GoogleTrends::dispatch($keyword)->onConnection('beanstalkd-shedule-get')->onQueue('beanstalkd-shedule-get')->delay($i*60);
            $i++;
        }
		$keywords = KeywordsTrend::where('actived',1)->where('sku_group','<>','AMAZON')->where('updated_at','<',$date)->get();
		foreach ($keywords as $keyword) {
			GoogleTrends::dispatch($keyword)->onConnection('beanstalkd-shedule-get')->onQueue('beanstalkd-shedule-get')->delay($i*90);
            $i++;
		}
    }
}
