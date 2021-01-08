<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\PaypalAccounts;
use App\PaymentsHistory;
use PayPal\PayPalAPI\TransactionSearchReq;
use PayPal\PayPalAPI\TransactionSearchRequestType;
use PayPal\Service\PayPalAPIInterfaceServiceService;
class PaypalPayment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
	protected $paypal_account;
	
    public function __construct($paypal_account)
    {
		$this->paypal_account = $paypal_account;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
		$notEnd = false;
		$updateAt = date('Y-m-d H:i:s');
		$endDate = date('Y-m-d\TH:i:s\Z',strtotime( $updateAt ));
		do{
			try{
				
				$transactionSearchRequest = new TransactionSearchRequestType();
				$transactionSearchRequest->StartDate=date('Y-m-d\TH:i:s\Z',strtotime($this->paypal_account->updated_at)+1);
				$transactionSearchRequest->EndDate=$endDate;
				$transactionSearchRequest->Status='Success '; 
				$transactionSearchRequest->TransactionClass='Sent';
				$tranSearchReq = new TransactionSearchReq();
				$tranSearchReq->TransactionSearchRequest = $transactionSearchRequest;
				$config = array(
					"acct1.UserName" => $this->paypal_account->api_username,
					"acct1.Password" => $this->paypal_account->api_password,
					"acct1.Signature" => $this->paypal_account->api_signature,
					"mode" => "live",
					'log.LogEnabled' => false,
					'log.FileName' => '../PayPal.log',
					'log.LogLevel' => 'FINE'
				);
				$paypalService = new PayPalAPIInterfaceServiceService($config);
				$transactionSearchResponse = $paypalService->TransactionSearch($tranSearchReq);
				$transactionSearchResponse = json_decode(json_encode($transactionSearchResponse), true);
				$paymentTransactions = array_get($transactionSearchResponse,'PaymentTransactions');
				if(!is_array($paymentTransactions)) $paymentTransactions=[];
				foreach($paymentTransactions as $payment){
					PaymentsHistory::insertIgnore(array(
						'paypal_account_id'=> $this->paypal_account->id,
						'timestamp'=> str_replace(array('T','Z'),array(' ',''),array_get($payment,'Timestamp')),
						'timezone'=> array_get($payment,'Timezone'),
						'type'=> array_get($payment,'Type'),
						'payer'=> array_get($payment,'Payer'),
						'payer_display_name'=> array_get($payment,'PayerDisplayName'),
						'transaction_id'=> array_get($payment,'TransactionID'),
						'status'=> array_get($payment,'Status'),
						'gross_amount'=> array_get($payment,'GrossAmount.value'),
						'gross_amount_currency'=> array_get($payment,'GrossAmount.currencyID'),
						'fee_amount'=> array_get($payment,'FeeAmount.value'),
						'fee_amount_currency'=> array_get($payment,'FeeAmount.currencyID'),
						'net_amount'=> array_get($payment,'NetAmount.value'),
						'net_amount_currency'=> array_get($payment,'NetAmount.currencyID'),
						'updated_at'=> date('Y-m-d H:i:s'),
					));
					$endDate = array_get($payment,'Timestamp');		
				}
				
				if(array_get($transactionSearchResponse,'Ack')=='Success'){
					$notEnd = false;
				}else{
					if(array_get($transactionSearchResponse,'Ack')=='SuccessWithWarning' && count(array_get($transactionSearchResponse,'Errors',[]))==1 && array_get($transactionSearchResponse,'Errors.0.ErrorCode')=='11002'){
						$notEnd = true;
					}else{
						$notEnd = false;
						throw new Exception(json_encode(array_get($transactionSearchResponse,'Errors')));
					}
				}
			}catch(Exception $ex){
				throw $ex;
			}
		} while ($notEnd);
		$this->paypal_account->updated_at = $updateAt;
		$this->paypal_account->save();	
    }
}
