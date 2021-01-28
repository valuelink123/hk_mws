<?php

namespace App\Jobs;
use App\SellerAccounts;
use App\AmazonSettlement;
use App\AmazonSettlementDetail;
use Carbon\Carbon;
use App\Classes\SaveMwsReportData;
use MarketplaceWebService_Client;
use MarketplaceWebService_Model_GetReportListRequest;
use MarketplaceWebService_Model_TypeList;
use MarketplaceWebService_Exception;
use MarketplaceWebService_Model_GetReportRequest;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class GetSettlement implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    protected $account;
    protected $report_type;
	
    public function __construct($account,$report_type)
    {
        $this->account = $account;
        $this->report_type = $report_type;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $account = $this->account;
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
				$request = new MarketplaceWebService_Model_GetReportListRequest();
				$reportTypeListArray = new MarketplaceWebService_Model_TypeList();
				$reportTypeListArray->setType($this->report_type);
				$request->setMerchant($account->mws_seller_id);
				$request->setMWSAuthToken($account->mws_auth_token);
                $request->setReportTypeList($reportTypeListArray);
                //$request->setMaxCount(10);
				$response = $client->getReportList($request);
				$getReportListResult = $response->getGetReportListResult();
				$reportInfoList = $getReportListResult->getReportInfoList();
				foreach ($reportInfoList as $reportInfo) {
                    $reportId = $reportInfo->getReportId();
                    $account->report_id = $reportId;
                    $reportDone = AmazonSettlement::where('seller_account_id',$account->id)->where('report_Id',$reportId)->value('settlement_id');
					if(!$reportDone){
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
                        if($account->mws_marketplaceid=='A1VC38T7YXB528'){
                            $responseStr = iconv("ISO-8859-1","UTF-8//IGNORE",$responseStr);
                        }
                        $res = csv_to_array($responseStr, chr(10), chr(9));
                        $obj = new SaveMwsReportData($account, $this->report_type, $res);
                        $obj->save();
                        sleep(60); 
                    }
				}
				
			} catch(MarketplaceWebService_Exception $ex){
				throw $ex;
			}
		}
    }
}
