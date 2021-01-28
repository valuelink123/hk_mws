<?php

namespace App\Console\Commands;
use Illuminate\Console\Command;
use App\Jobs\PushSapOrder;
class InsertOrderToSap extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'push:order';

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
        PushSapOrder::dispatch()->onConnection('beanstalkd-sap-put')->onQueue('beanstalkd-sap-put');
    }
}
