<?php

namespace App\Jobs;
use App\SellerAccounts;
use App\RequestReport;
use App\Asin;
use App\SellerSku;
use Carbon\Carbon;
use App\Classes\SaveMwsReportData;
use MarketplaceWebService_Client;
use MarketplaceWebService_Model_GetReportRequestListRequest;
use MarketplaceWebService_Model_IdList;
use MarketplaceWebService_Model_RequestReportRequest;
use MarketplaceWebService_Exception;
use MarketplaceWebService_Model_GetReportRequest;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class GetReport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
	protected $requestReport;
	
    public function __construct($requestReport)
    {
        $this->requestReport = $requestReport;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $account = SellerAccounts::whereNull('deleted_at')->find($this->requestReport->seller_account_id);
        if (!empty($account)) {
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
			
			try {
				$request = new MarketplaceWebService_Model_GetReportRequestListRequest();
				$reportRequestIdListArray = new MarketplaceWebService_Model_IdList();
				$reportRequestIdListArray->setId($this->requestReport->request_id);
				$request->setMerchant($account->mws_seller_id);
				$request->setMWSAuthToken($account->mws_auth_token);
				$request->setReportRequestIdList($reportRequestIdListArray);
				$response = $client->getReportRequestList($request);
				$getReportRequestListResult = $response->getGetReportRequestListResult();
				$reportRequestInfoList = $getReportRequestListResult->getReportRequestInfoList();
				foreach ($reportRequestInfoList as $reportRequestInfo) {
					$reportStatus = $reportRequestInfo->getReportProcessingStatus();
					$reportId = $reportRequestInfo->getGeneratedReportId();
					break;
				}
				$this->requestReport->status=$reportStatus;
				if($reportId){
					ob_start();
					$fileHandle = @fopen('php://memory', 'rw+');
					$parameters = array (
						'Merchant' => $account->mws_seller_id,
						'Report' => $fileHandle,
						'ReportId' => $reportId,
						'MWSAuthToken' => $account->mws_auth_token,
					);
					$request = new MarketplaceWebService_Model_GetReportRequest($parameters);
					$response = $client->getReport($request);
					$getReportResult = $response->getGetReportResult();
					$responseMetadata = $response->getResponseMetadata();
					rewind($fileHandle);
					$responseStr = stream_get_contents($fileHandle);
					@fclose($fileHandle);
					ob_end_clean();
					/*
					if($account->mws_seller_id=='A1VC38T7YXB528'){
						$responseStr = iconv("Shift_JIS","UTF-8//IGNORE",$responseStr) ;
					}else{
						$responseStr = iconv("latin1","UTF-8//IGNORE",$responseStr) ;
					}
					*/
					$res = csv_to_array($responseStr, PHP_EOL, "\t");
					$this->requestReport->report_id=$reportId;
					$this->requestReport->response=json_encode($res);
					$obj = new SaveMwsReportData($account, $this->requestReport->report_type, $res);
					$obj->save();
					
				}
			} catch(MarketplaceWebService_Exception $ex){
				throw $ex;
			}
		}
		$this->requestReport->updated_at=Carbon::now()->toDateTimeString();
		$this->requestReport->save();
    }
}
