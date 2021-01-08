<?php

namespace App\Jobs;
use App\SellerAccounts;
use App\RequestReport;
use Carbon\Carbon;
use MarketplaceWebService_Client;
use MarketplaceWebService_Model_GetReportRequestListRequest;
use MarketplaceWebService_Model_IdList;
use MarketplaceWebService_Model_RequestReportRequest;
use MarketplaceWebService_Exception;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class MwsRequestReport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
	protected $account;
	protected $reportType;
	protected $afterDate;
    protected $beforeDate;
	
	
    public function __construct($account,$reportType,$afterDate='',$beforeDate='')
    {
        $this->account = $account;
		$this->reportType = $reportType;
        $this->afterDate = $afterDate;
        $this->beforeDate = $beforeDate;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $account = $this->account;
        if ($account) {
			$siteConfig = getSiteConfig();
			$client = new MarketplaceWebService_Client(
				$account->mws_access_keyid,
                $account->mws_secret_key,
				['ServiceURL' => $siteConfig[$account->mws_marketplaceid]['serviceUrl'].'/',
					'ProxyHost' => null,
					'ProxyPort' => -1,
					'MaxErrorRetry' => 3],
				'VLMWS',
				'1.0.0'
			);
			$notEnd = false;
            do {
				try {
					$request = new MarketplaceWebService_Model_RequestReportRequest();
					$request->setReportType($this->reportType);
					if($this->afterDate) $request->setStartDate($this->afterDate);
					if($this->beforeDate) $request->setEndDate($this->beforeDate);
					$request->setMerchant($account->mws_seller_id);
					$request->setMWSAuthToken($account->mws_auth_token);
					$request->setMarketplace($account->mws_marketplaceid);
		
					$response = $client->requestReport($request);
					$requestReportResult = $response->getRequestReportResult();
					$reportRequestInfo = $requestReportResult->getReportRequestInfo();
					$requestId = $reportRequestInfo->getReportRequestId();
					$status = $reportRequestInfo->getReportProcessingStatus();
					
					$insertData['user_id']=$account->user_id;
					$insertData['seller_account_id']=$account->id;
					$insertData['report_type']=$this->reportType;
					$insertData['status']=$status;
					$insertData['request_id']=$requestId;
					$insertData['after_date']=$this->afterDate;
					$insertData['before_date']=$this->beforeDate;
					$insertData['created_at']=$insertData['updated_at']=Carbon::now()->toDateTimeString();
					RequestReport::insert($insertData);
				} catch(MarketplaceWebService_Exception $ex){
					if (getExRetry($ex)) {
						$notEnd = true;
						sleep(60);
					}else{
						throw $ex;
					}
				}
			} while ($notEnd);
		}
    }
}
