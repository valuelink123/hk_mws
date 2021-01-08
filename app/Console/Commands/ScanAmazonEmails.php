<?php

namespace App\Console\Commands;

use App\Commands\GetEmailsForAccount;
use Illuminate\Console\Command;
use App\EmailAccounts;

class ScanAmazonEmails extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'scan:emails {--accountId=}';

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

        $emailAccounts = EmailAccounts::whereNull('deleted_at');

        if($this->option('accountId')) $emailAccounts = $emailAccounts->where('id',$this->option('accountId'));
        $emailAccounts->chunk(50,function($accounts){

            foreach ($accounts as $account) {
                //MultipleQueue::pushOn(MultipleQueue::SCHEDULE_GET,
                new GetEmailsForAccount($account);
                //);
            }
        });
    }
}
