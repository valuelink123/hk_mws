<?php

namespace App\Jobs;
use App\SellerAccounts;
use App\FinancesShipmentEvent;
use App\FinancesServicefeeEvent;
use App\FinancesSafetreimbursementEvent;
use App\FinancesRetrochargeEvent;
use App\FinancesRefundEvent;
use App\FinancesProductAdsPaymentEvent;
use App\FinancesFbaliquidationEvent;
use App\FinancesDealEvent;
use App\FinancesCouponEvent;
use App\FinancesClaimEvent;
use App\FinancesChargebackEvent;
use App\FinancesAdjustmentEvent;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use MWSFinancesService_Client;
use MWSFinancesService_Model_ListFinancialEventsByNextTokenRequest;
use MWSFinancesService_Model_ListFinancialEventsRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class GetFinancesForAccount implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
	protected $account;
    protected $afterDate;
    protected $beforeDate;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($account,$afterDate='',$beforeDate='')
	{
		$this->account = $account;
        $afterDate_init = ($account->last_update_finance_date)?$account->last_update_finance_date:date('Y-m-d', strtotime('-1 day'));
        $this->afterDate = ($afterDate)?$afterDate:$afterDate_init;
        $this->beforeDate = ($beforeDate)?$beforeDate:date('Y-m-d');
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
            $client = new MWSFinancesService_Client(
                $account->mws_access_keyid,
                $account->mws_secret_key,
                'VLMWS',
                '1.0.0',
                ['ServiceURL' => $siteConfig[$account->mws_marketplaceid]['serviceUrl']."/Finances/2015-05-01",
                    'ProxyHost' => null,
                    'ProxyPort' => -1,
                    'MaxErrorRetry' => 3]

            );
            $after_date=$this->afterDate;
            $before_date=$this->beforeDate;
            while($after_date<$before_date){
                $defaultDate = $after_date.' 00:00:00';
                $nextToken = null;
                $notEnd = false;
                do {
                    if ($nextToken) {
                        $request = new MWSFinancesService_Model_ListFinancialEventsByNextTokenRequest();
                        $request->setNextToken($nextToken);
                        $resultName = 'ListFinancialEventsByNextTokenResult';
                        $page++;
                    } else {
                        $request = new MWSFinancesService_Model_ListFinancialEventsRequest();
                        $request->setPostedAfter(date("c", strtotime($after_date)));
                        $after_date = date('Y-m-d', strtotime($after_date)+86400);
                        if($after_date>$before_date) $after_date = $before_date;
                        $request->setPostedBefore(date("c", strtotime($after_date)));
                        $resultName = 'ListFinancialEventsResult';
                        $page=1;
                    }

                    $request->setSellerId($account->mws_seller_id);
                    $request->setMWSAuthToken($account->mws_auth_token);
                    try {

                        $response = $nextToken ? $client->listFinancialEventsByNextToken($request) : $client->listFinancialEvents($request);



                        $objResponse = processResponse($response);
                        $resultResponse = $objResponse->{$resultName};
                        $nextToken = isset($resultResponse->NextToken) ? $resultResponse->NextToken : null;
                        $notEnd = !empty($nextToken);
                        //ShipmentEventList 开始
                        $shipmentEvents = isset($resultResponse->FinancialEvents->ShipmentEventList->ShipmentEvent) ? $resultResponse->FinancialEvents->ShipmentEventList->ShipmentEvent : [];

                        $this->getShipmentEvent($shipmentEvents,new FinancesShipmentEvent,$defaultDate);

                        //RefundEventList 开始
                        $shipmentEvents = isset($resultResponse->FinancialEvents->RefundEventList->ShipmentEvent) ? $resultResponse->FinancialEvents->RefundEventList->ShipmentEvent : [];
                        $this->getShipmentEvent($shipmentEvents,new FinancesRefundEvent,$defaultDate);

                        //GuaranteeClaimEventList 开始
                        $shipmentEvents = isset($resultResponse->FinancialEvents->GuaranteeClaimEventList->ShipmentEvent) ? $resultResponse->FinancialEvents->GuaranteeClaimEventList->ShipmentEvent : [];
                        $this->getShipmentEvent($shipmentEvents,new FinancesRefundEvent,$defaultDate);

                        //ChargebackEventList 开始
                        $shipmentEvents = isset($resultResponse->FinancialEvents->ChargebackEventList->ShipmentEvent) ? $resultResponse->FinancialEvents->ChargebackEventList->ShipmentEvent : [];
                        $this->getShipmentEvent($shipmentEvents,new FinancesRefundEvent,$defaultDate);


                        //ProductAdsPaymentEventList 开始
                        $adsEvents = isset($resultResponse->FinancialEvents->ProductAdsPaymentEventList->ProductAdsPaymentEvent) ? $resultResponse->FinancialEvents->ProductAdsPaymentEventList->ProductAdsPaymentEvent : [];
                        foreach($adsEvents as $adsEvent){
                            $insert_data=[];
                            $arrayFee = json_decode(json_encode($adsEvent), true);
                            $insert_data['user_id']=$account->user_id;
                            $insert_data['seller_account_id']=$account->id;
                            $insert_data['transaction_type']=array_get($arrayFee,'transactionType','');
                            $insert_data['posted_date']=array_get($arrayFee,'postedDate')?date('Y-m-d H:i:s',strtotime(array_get($arrayFee,'postedDate'))):$defaultDate;
                            $insert_data['invoice_id']=array_get($arrayFee,'invoiceId','');
                            $insert_data['base_value']=array_get($arrayFee,'baseValue.CurrencyAmount',0);
                            $insert_data['tax_value']=array_get($arrayFee,'taxValue.CurrencyAmount',0);
                            $insert_data['transaction_value']=array_get($arrayFee,'transactionValue.CurrencyAmount',0);
                            $insert_data['currency']=array_get($arrayFee,'transactionValue.CurrencyCode','');

                            //MultipleQueue::pushOn(MultipleQueue::DB_WRITE,function ($job) use ($insert_data) {
                            	FinancesProductAdsPaymentEvent::insertIgnore($insert_data);
                            //});
                        }


                        //ServiceFeeEventList 开始

                        $serviceFeeEvents = isset($resultResponse->FinancialEvents->ServiceFeeEventList->ServiceFeeEvent) ? $resultResponse->FinancialEvents->ServiceFeeEventList->ServiceFeeEvent : [];


                        foreach($serviceFeeEvents as $serviceFeeEvent){
                            $insert_data=[];
                            $insert_data['user_id']=$account->user_id;
                            $insert_data['seller_account_id']=$account->id;
                            $insert_data['amazon_order_id']=isset($serviceFeeEvent->AmazonOrderId)?(string)$serviceFeeEvent->AmazonOrderId:'';
                            $insert_data['fee_reason']=html_entity_decode(isset($serviceFeeEvent->FeeReason)?(string)$serviceFeeEvent->FeeReason:'');
                            $insert_data['seller_sku']=isset($serviceFeeEvent->SellerSKU)?(string)$serviceFeeEvent->SellerSKU:'';
                            $insert_data['fn_sku']=isset($serviceFeeEvent->FnSKU)?(string)$serviceFeeEvent->FnSKU:'';
                            $insert_data['asin']=isset($serviceFeeEvent->ASIN)?(string)$serviceFeeEvent->ASIN:'';
                            $insert_data['posted_date']=isset($serviceFeeEvent->PostedDate)?date('Y-m-d H:i:s',strtotime((string)$serviceFeeEvent->PostedDate)):$defaultDate;
                            $insert_data['fee_description']=html_entity_decode(isset($serviceFeeEvent->FeeDescription)?(string)$serviceFeeEvent->FeeDescription:'');

                            if(isset($serviceFeeEvent->FeeList->FeeComponent)){
                                foreach($serviceFeeEvent->FeeList->FeeComponent as $feeComponent){
                                    $arrayFeeComponent = json_decode(json_encode($feeComponent), true);

                                    if(array_get($arrayFeeComponent,'FeeAmount.CurrencyAmount',0)!=0){
                                        $data=[];
                                        $data['type']=array_get($arrayFeeComponent,'FeeType','');
                                        $data['amount']=array_get($arrayFeeComponent,'FeeAmount.CurrencyAmount',0);
                                        $data['currency'] = array_get($arrayFeeComponent,'FeeAmount.CurrencyCode','');

                                        //MultipleQueue::pushOn(MultipleQueue::DB_WRITE,function ($job) use ($insert_data,$data) {
                                        	FinancesServicefeeEvent::insertIgnore(array_merge($insert_data, $data));
                                        //});
                                    }

                                }
                            }
                        }



                        //RetrochargeEventList 开始
                        
                        $retrochargeEvents = isset($resultResponse->FinancialEvents->RetrochargeEventList->RetrochargeEvent) ? $resultResponse->FinancialEvents->RetrochargeEventList->RetrochargeEvent : [];
                        foreach($retrochargeEvents as $retrochargeEvent){
                            $insert_data=[];
                            $arrayFee = json_decode(json_encode($retrochargeEvent), true);
                            $insert_data['user_id']=$account->user_id;
                            $insert_data['seller_account_id']=$account->id;
                            $insert_data['retrocharge_event_type']=array_get($arrayFee,'RetrochargeEventType','');
                            $insert_data['posted_date']=array_get($arrayFee,'PostedDate')?date('Y-m-d H:i:s',strtotime(array_get($arrayFee,'PostedDate'))):$defaultDate;
                            $insert_data['amazon_order_id']=array_get($arrayFee,'AmazonOrderId','');
                            $insert_data['base_tax']=array_get($arrayFee,'BaseTax.CurrencyAmount',0);
                            $insert_data['shipping_tax']=array_get($arrayFee,'ShippingTax.CurrencyAmount',0);
                            $insert_data['marketplace_name']=array_get($arrayFee,'MarketplaceName','');
                            $insert_data['currency']=array_get($arrayFee,'BaseTax.CurrencyCode','');

                            //MultipleQueue::pushOn(MultipleQueue::DB_WRITE,function ($job) use ($insert_data) {
                            	FinancesRetrochargeEvent::insertIgnore($insert_data);
                            //});
                        }


                        //SAFETReimbursementEvent 开始
                        $SAFETReimbursementEvents = isset($resultResponse->FinancialEvents->SAFETReimbursementEventList->SAFETReimbursementEvent) ? $resultResponse->FinancialEvents->SAFETReimbursementEventList->SAFETReimbursementEvent : [];
                        foreach($SAFETReimbursementEvents as $SAFETReimbursementEvent){
                            $insert_data=[];
                            $arrayFee = json_decode(json_encode($SAFETReimbursementEvent), true);
                            $insert_data['user_id']=$account->user_id;
                            $insert_data['seller_account_id']=$account->id;
                            $insert_data['safet_claim_id']=array_get($arrayFee,'SAFETClaimId','');
                            $insert_data['posted_date']=array_get($arrayFee,'PostedDate')?date('Y-m-d H:i:s',strtotime(array_get($arrayFee,'PostedDate'))):$defaultDate;
                            $insert_data['reimbursed_amount']=array_get($arrayFee,'ReimbursedAmount.CurrencyAmount',0);
                            $insert_data['currency']=array_get($arrayFee,'ReimbursedAmount.CurrencyCode','');
                            //MultipleQueue::pushOn(MultipleQueue::DB_WRITE,function ($job) use ($insert_data) {
                            	FinancesSafetreimbursementEvent::insertIgnore($insert_data);
                            //});
                        }

                        //FBALiquidationEvent 开始
                        $FBALiquidationEvents = isset($resultResponse->FinancialEvents->FBALiquidationEventList->FBALiquidationEvent) ? $resultResponse->FinancialEvents->FBALiquidationEventList->FBALiquidationEvent : [];
                        foreach($FBALiquidationEvents as $FBALiquidationEvent){
                            $insert_data=[];
                            $arrayFee = json_decode(json_encode($FBALiquidationEvent), true);
                            $insert_data['user_id']=$account->user_id;
                            $insert_data['seller_account_id']=$account->id;
                            $insert_data['original_removal_order_id']=array_get($arrayFee,'OriginalRemovalOrderId','');
                            $insert_data['posted_date']=array_get($arrayFee,'PostedDate')?date('Y-m-d H:i:s',strtotime(array_get($arrayFee,'PostedDate'))):$defaultDate;
                            $insert_data['liquidation_proceeds_amount']=array_get($arrayFee,'LiquidationProceedsAmount.CurrencyAmount',0);
                            $insert_data['liquidation_fee_amount']=array_get($arrayFee,'LiquidationFeeAmount.CurrencyAmount',0);
                            $insert_data['currency']=array_get($arrayFee,'LiquidationFeeAmount.CurrencyCode','');
                            //MultipleQueue::pushOn(MultipleQueue::DB_WRITE,function ($job) use ($insert_data) {
                            	FinancesFbaliquidationEvent::insertIgnore($insert_data);
                            //});
                        }
                        



                        //AdjustmentEventList 开始
                        $adjustmentEvents = isset($resultResponse->FinancialEvents->AdjustmentEventList->AdjustmentEvent) ? $resultResponse->FinancialEvents->AdjustmentEventList->AdjustmentEvent : [];
                        foreach($adjustmentEvents as $adjustmentEvent){

                            $insert_data=[];
                            $insert_data['user_id']=$account->user_id;
                            $insert_data['seller_account_id']=$account->id;
                            $insert_data['adjustment_type']=isset($adjustmentEvent->AdjustmentType)?$adjustmentEvent->AdjustmentType:'';
                            $insert_data['posted_date']=isset($adjustmentEvent->PostedDate)?date('Y-m-d H:i:s',strtotime((string)$adjustmentEvent->PostedDate)):$defaultDate;
                            $insert_data['adjustment_amount']=isset($adjustmentEvent->AdjustmentAmount->CurrencyAmount)?round((string)$adjustmentEvent->AdjustmentAmount->CurrencyAmount,2):0;
                            $insert_data['currency']=isset($adjustmentEvent->AdjustmentAmount->CurrencyCode)?(string)$adjustmentEvent->AdjustmentAmount->CurrencyCode:'';

                            if(isset($adjustmentEvent->AdjustmentItemList->AdjustmentItem)){
                                $datas=[];
                                foreach($adjustmentEvent->AdjustmentItemList->AdjustmentItem as $feeComponent){
                                    $arrayFeeComponent = json_decode(json_encode($feeComponent), true);
                                    if(array_get($arrayFeeComponent,'TotalAmount.CurrencyAmount',0)!=0){
                                        if(!array_get($datas,array_get($arrayFeeComponent,'SellerSKU','').'.quantity')) $datas[array_get($arrayFeeComponent,'SellerSKU','')]['quantity']=0;
                                        if(!array_get($datas,array_get($arrayFeeComponent,'SellerSKU','').'.total_amount')) $datas[array_get($arrayFeeComponent,'SellerSKU','')]['total_amount']=0;
                                        $datas[array_get($arrayFeeComponent,'SellerSKU','')]['quantity']+=array_get($arrayFeeComponent,'Quantity',0);
                                        $datas[array_get($arrayFeeComponent,'SellerSKU','')]['total_amount']+=array_get($arrayFeeComponent,'TotalAmount.CurrencyAmount',0);
                                        $datas[array_get($arrayFeeComponent,'SellerSKU','')]['seller_sku']=array_get($arrayFeeComponent,'SellerSKU','');
                                        $datas[array_get($arrayFeeComponent,'SellerSKU','')]['fn_sku'] = array_get($arrayFeeComponent,'FnSKU','');
                                        $datas[array_get($arrayFeeComponent,'SellerSKU','')]['product_description'] = html_entity_decode(array_get($arrayFeeComponent,'ProductDescription',''));
                                        $datas[array_get($arrayFeeComponent,'SellerSKU','')]['asin'] = array_get($arrayFeeComponent,'ASIN','');
                                    }
                                }
                                foreach($datas as $key=>$data){
                                    //MultipleQueue::pushOn(MultipleQueue::DB_WRITE,function ($job) use ($insert_data, $data) {
                                    	FinancesAdjustmentEvent::insertIgnore(array_merge($insert_data, $data));
                                    //});

                                }
                                $datas=[];
                            }else{
                                //MultipleQueue::pushOn(MultipleQueue::DB_WRITE,function () use ($insert_data, $data) {
                                //	FinancesAdjustmentEvent::insertIgnore($insert_data);
                                //});
                                //借记 信用?
                            }
                        }



                        //CouponPaymentEventList 开始
                        $couponEvents = isset($resultResponse->FinancialEvents->CouponPaymentEventList->CouponPaymentEvent) ? $resultResponse->FinancialEvents->CouponPaymentEventList->CouponPaymentEvent : [];
                        foreach($couponEvents as $couponEvent){
                            $insert_data=[];
                            $insert_data['user_id']=$account->user_id;
                            $insert_data['seller_account_id']=$account->id;
                            $insert_data['coupon_id']=isset($couponEvent->CouponId)?$couponEvent->CouponId:'';
                            $insert_data['posted_date']=isset($couponEvent->PostedDate)?date('Y-m-d H:i:s',strtotime($couponEvent->PostedDate)):$defaultDate;
                            $insert_data['seller_coupon_description']=(isset($couponEvent->SellerCouponDescription)?html_entity_decode($couponEvent->SellerCouponDescription):'');
                            $insert_data['clip_or_redemption_count']=isset($couponEvent->ClipOrRedemptionCount)?$couponEvent->ClipOrRedemptionCount:0;
                            $insert_data['payment_event_id']=isset($couponEvent->PaymentEventId)?$couponEvent->PaymentEventId:'';
                            $insert_data['total_amount']=isset($couponEvent->TotalAmount->CurrencyAmount)?$couponEvent->TotalAmount->CurrencyAmount:0;
                            $insert_data['currency']=isset($couponEvent->TotalAmount->CurrencyCode)?$couponEvent->TotalAmount->CurrencyCode:'';

                            if($insert_data['total_amount']!=0){
                                //MultipleQueue::pushOn(MultipleQueue::DB_WRITE,function ($job) use ($insert_data) {
                                	FinancesCouponEvent::insertIgnore($insert_data);
                                //});
                            }
                        }



                        //SellerDealPaymentEventList 开始
                        $dealEvents = isset($resultResponse->FinancialEvents->SellerDealPaymentEventList->SellerDealPaymentEvent) ? $resultResponse->FinancialEvents->SellerDealPaymentEventList->SellerDealPaymentEvent : [];
                        foreach($dealEvents as $dealEvent){
                            $insert_data=[];
                            $insert_data['user_id']=$account->user_id;
                            $insert_data['seller_account_id']=$account->id;
                            $insert_data['deal_id']=isset($dealEvent->dealId)?(string)$dealEvent->dealId:'';
                            $insert_data['posted_date']=isset($dealEvent->postedDate)?date('Y-m-d H:i:s',strtotime((string)$dealEvent->postedDate)):$defaultDate;
                            $insert_data['event_type']=isset($dealEvent->eventType)?(string)$dealEvent->eventType:'';
                            $insert_data['fee_type']=isset($dealEvent->feeType)?(string)$dealEvent->feeType:'';
                            $insert_data['deal_description']=html_entity_decode(isset($dealEvent->dealDescription)?(string)$dealEvent->dealDescription:'');
                            $insert_data['total_amount']=isset($dealEvent->totalAmount->CurrencyAmount)?round((string)$dealEvent->totalAmount->CurrencyAmount,2):0;
                            $insert_data['fee_amount']=isset($dealEvent->feeAmount->CurrencyAmount)?round((string)$dealEvent->feeAmount->CurrencyAmount,2):0;

                            $insert_data['tax_amount']=isset($dealEvent->taxAmount->CurrencyAmount)?round((string)$dealEvent->taxAmount->CurrencyAmount,2):0;
                            $insert_data['currency']=isset($dealEvent->totalAmount->CurrencyCode)?(string)$dealEvent->totalAmount->CurrencyCode:'';

                            if($insert_data['total_amount']!=0){
                                //MultipleQueue::pushOn(MultipleQueue::DB_WRITE,function ($job) use ($insert_data) {
                                	FinancesDealEvent::insertIgnore($insert_data);
                                //});
                            }
                        }
                        
                        
                    } catch (\MWSFinancesService_Exception $ex) {
                        if (getExRetry($ex)) {
							$notEnd = true;
							sleep(60);
						}else{
							throw $ex;
						}
                            
                    }
					sleep(2);
                } while ($notEnd);
				$account->last_update_finance_date = $after_date;
                $account->save();
            }


        }
	}



    public function getShipmentEvent($shipmentEvents,Model $model,$defaultDate){
        foreach($shipmentEvents as $shipmentEvent){
            $insert_data=[];
            $insert_data['user_id']=$this->account->user_id;
            $insert_data['seller_account_id']=$this->account->id;
            $insert_data['amazon_order_id']=isset($shipmentEvent->AmazonOrderId)?(string)$shipmentEvent->AmazonOrderId:'';
            $insert_data['seller_order_id']=isset($shipmentEvent->SellerOrderId)?(string)$shipmentEvent->SellerOrderId:'';
            $insert_data['marketplace_name']=isset($shipmentEvent->MarketplaceName)?(string)$shipmentEvent->MarketplaceName:'';
            $insert_data['posted_date']=isset($shipmentEvent->PostedDate)?date('Y-m-d H:i:s',strtotime($shipmentEvent->PostedDate)):$defaultDate;
            if(isset($shipmentEvent->ShipmentItemList->ShipmentItem)){
                $shipmentItems = $shipmentEvent->ShipmentItemList->ShipmentItem;
                $orderLineNum=0;
                foreach($shipmentItems as $shipmentItem){
                    $tempData=$data=[];
                    $orderLineNum++;
                    $data['order_item_id'] = isset($shipmentItem->OrderItemId)?(string)$shipmentItem->OrderItemId:'';
                    $data['order_adjustment_item_id'] = isset($shipmentItem->OrderAdjustmentItemId)?(string)$shipmentItem->OrderAdjustmentItemId:'';
                    $data['dom_type'] ='ShipmentItem';
                    $data['seller_sku'] = isset($shipmentItem->SellerSKU)?(string)$shipmentItem->SellerSKU:'';
                    $data['quantity_shipped'] = isset($shipmentItem->QuantityShipped)?(int)$shipmentItem->QuantityShipped:0;
                    $data['line_num'] = $orderLineNum;    
                    if(isset($shipmentItem->ItemChargeList->ChargeComponent)){
                        foreach($shipmentItem->ItemChargeList->ChargeComponent as $chargeComponent){
                            $arrayChargeComponent = json_decode(json_encode($chargeComponent), true);    
                            $Amount = array_get($arrayChargeComponent,'ChargeAmount.CurrencyAmount',0);
                            if($Amount!=0){
                                $data['item_type']='ItemCharge';
                                $data['type']=$data['type_id']=array_get($arrayChargeComponent,'ChargeType','');
                                $data['currency'] = array_get($arrayChargeComponent,'ChargeAmount.CurrencyCode','');            
                                $tempData[$data['item_type'].'_'.$data['type']]=$data;
                                if(isset($tempData[$data['item_type'].'_'.$data['type']]['amount'])){
                                    $tempData[$data['item_type'].'_'.$data['type']]['amount'] = $tempData[$data['item_type'].'_'.$data['type']]['amount'] + $Amount;
                                }else{
                                    $tempData[$data['item_type'].'_'.$data['type']]['amount'] = $Amount;
                                }
                            }
                        }
                    }


                    if(isset($shipmentItem->ItemChargeAdjustmentList->ChargeComponent)){
                        foreach($shipmentItem->ItemChargeAdjustmentList->ChargeComponent as $chargeComponent){
                            $arrayChargeComponent = json_decode(json_encode($chargeComponent), true);
                            $Amount = array_get($arrayChargeComponent,'ChargeAmount.CurrencyAmount',0);
                            if($Amount!=0){ 
                                $data['item_type']='ItemChargeAdjustment';
                                $data['type']=$data['type_id']=array_get($arrayChargeComponent,'ChargeType','');
                                $data['currency'] = array_get($arrayChargeComponent,'ChargeAmount.CurrencyCode','');
                                $tempData[$data['item_type'].'_'.$data['type']]=$data;
                                if(isset($tempData[$data['item_type'].'_'.$data['type']]['amount'])){
                                    $tempData[$data['item_type'].'_'.$data['type']]['amount'] = $tempData[$data['item_type'].'_'.$data['type']]['amount'] + $Amount;
                                }else{
                                    $tempData[$data['item_type'].'_'.$data['type']]['amount'] = $Amount;
                                }
                            }
                        }
                    }


                    if(isset($shipmentItem->ItemTaxWithheldList->TaxWithheldComponent)){
                        foreach($shipmentItem->ItemTaxWithheldList->TaxWithheldComponent as $TaxWithheldComponent){
                            if(isset($TaxWithheldComponent->TaxesWithheld->ChargeComponent)){
                                foreach($TaxWithheldComponent->TaxesWithheld->ChargeComponent as $chargeComponent){
                                    $arrayChargeComponent = json_decode(json_encode($chargeComponent), true);
                                    $Amount = array_get($arrayChargeComponent,'ChargeAmount.CurrencyAmount',0);
                                    if($Amount!=0){
                                        $data['item_type']='ItemTaxWithheld';
                                        $data['type']=array_get($arrayChargeComponent,'ChargeType','');
                                        $data['type_id']=$TaxWithheldComponent->TaxesWithheld->TaxCollectionModel;
                                        $data['currency'] = array_get($arrayChargeComponent,'ChargeAmount.CurrencyCode','');                
                                        $tempData[$data['item_type'].'_'.$data['type'].'_'.$data['type_id']]=$data;
                                        if(isset($tempData[$data['item_type'].'_'.$data['type'].'_'.$data['type_id']]['amount'])){
                                            $tempData[$data['item_type'].'_'.$data['type'].'_'.$data['type_id']]['amount'] = $tempData[$data['item_type'].'_'.$data['type'].'_'.$data['type_id']]['amount'] + $Amount;
                                        }else{
                                            $tempData[$data['item_type'].'_'.$data['type'].'_'.$data['type_id']]['amount'] = $Amount;
                                        }
                                    }
                                }
                            }
                        }
                    }


                    if(isset($shipmentItem->ItemFeeList->FeeComponent)){
                        foreach($shipmentItem->ItemFeeList->FeeComponent as $feeComponent){
                            $arrayFeeComponent = json_decode(json_encode($feeComponent), true);
                            $Amount = array_get($arrayFeeComponent,'FeeAmount.CurrencyAmount',0);
                            if($Amount!=0){
                                $data['item_type']='ItemFee';
                                $data['type']=$data['type_id']=array_get($arrayFeeComponent,'FeeType','');   
                                $data['currency'] = array_get($arrayFeeComponent,'FeeAmount.CurrencyCode','');
                                $tempData[$data['item_type'].'_'.$data['type']]=$data;
                                if(isset($tempData[$data['item_type'].'_'.$data['type']]['amount'])){
                                    $tempData[$data['item_type'].'_'.$data['type']]['amount'] = $tempData[$data['item_type'].'_'.$data['type']]['amount'] + $Amount;
                                }else{
                                    $tempData[$data['item_type'].'_'.$data['type']]['amount'] = $Amount;
                                }
                            }
                        }
                    }


                    if(isset($shipmentItem->ItemFeeAdjustmentList->FeeComponent)){
                        foreach($shipmentItem->ItemFeeAdjustmentList->FeeComponent as $feeComponent){
                            $arrayFeeComponent = json_decode(json_encode($feeComponent), true);
                            $Amount = array_get($arrayFeeComponent,'FeeAmount.CurrencyAmount',0);
                            if($Amount!=0){
                                $data['item_type']='ItemFeeAdjustment';
                                $data['type']=$data['type_id']=array_get($arrayFeeComponent,'FeeType','');   
                                $data['currency'] = array_get($arrayFeeComponent,'FeeAmount.CurrencyCode','');
                                $tempData[$data['item_type'].'_'.$data['type']]=$data;
                                if(isset($tempData[$data['item_type'].'_'.$data['type']]['amount'])){
                                    $tempData[$data['item_type'].'_'.$data['type']]['amount'] = $tempData[$data['item_type'].'_'.$data['type']]['amount'] + $Amount;
                                }else{
                                    $tempData[$data['item_type'].'_'.$data['type']]['amount'] = $Amount;
                                }   
                            }
                        }
                    }


                    if(isset($shipmentItem->PromotionList->Promotion)){
                        foreach($shipmentItem->PromotionList->Promotion as $feePromotion){
                            $arrayfeePromotion = json_decode(json_encode($feePromotion), true);
                            $Amount = array_get($arrayfeePromotion,'PromotionAmount.CurrencyAmount',0);
                            if($Amount!=0){
                                $data['item_type']='Promotion';
                                $data['type']=array_get($arrayfeePromotion,'PromotionType','');
                                $data['type_id']=array_get($arrayfeePromotion,'PromotionId','');
                                $data['currency'] = array_get($arrayfeePromotion,'PromotionAmount.CurrencyCode','');
                                $tempData[$data['item_type'].'_'.$data['type'].'_'.$data['type_id']]=$data;
                                if(isset($tempData[$data['item_type'].'_'.$data['type'].'_'.$data['type_id']]['amount'])){
                                    $tempData[$data['item_type'].'_'.$data['type'].'_'.$data['type_id']]['amount'] = $tempData[$data['item_type'].'_'.$data['type'].'_'.$data['type_id']]['amount'] + $Amount;
                                }else{
                                    $tempData[$data['item_type'].'_'.$data['type'].'_'.$data['type_id']]['amount'] = $Amount;
                                }
                            }
                        }
                    }


                    if(isset($shipmentItem->PromotionAdjustmentList->Promotion)){
                        foreach($shipmentItem->PromotionAdjustmentList->Promotion as $feePromotion){
                            $arrayfeePromotion = json_decode(json_encode($feePromotion), true);
                            $Amount = array_get($arrayfeePromotion,'PromotionAmount.CurrencyAmount',0);
                            if($Amount!=0){
                                $data['item_type']='PromotionAdjustmentList';
                                $data['type']=array_get($arrayfeePromotion,'PromotionType','');
                                $data['type_id']=array_get($arrayfeePromotion,'PromotionId','');
                                $data['currency'] = array_get($arrayfeePromotion,'PromotionAmount.CurrencyCode','');
                                $tempData[$data['item_type'].'_'.$data['type'].'_'.$data['type_id']]=$data;
                                if(isset($tempData[$data['item_type'].'_'.$data['type'].'_'.$data['type_id']]['amount'])){
                                    $tempData[$data['item_type'].'_'.$data['type'].'_'.$data['type_id']]['amount'] = $tempData[$data['item_type'].'_'.$data['type'].'_'.$data['type_id']]['amount'] + $Amount;
                                }else{
                                    $tempData[$data['item_type'].'_'.$data['type'].'_'.$data['type_id']]['amount'] = $Amount;
                                }
                            }
                        }
                    }

                    if(isset($shipmentItem->CostOfPointsGranted)){
                        $arrayfeeCost = json_decode(json_encode($shipmentItem->CostOfPointsGranted), true);
                        $Amount = array_get($arrayfeeCost,'CurrencyAmount',0);
                        if($Amount!=0){
                            $data['item_type']='CostOfPointsGranted';
                            $data['type']=$data['type_id']='CostOfPointsGranted';
                            $data['currency'] = array_get($arrayfeeCost,'CurrencyCode','');
                            $tempData[$data['item_type'].'_'.$data['type']]=$data;
                            if(isset($tempData[$data['item_type'].'_'.$data['type']]['amount'])){
                                $tempData[$data['item_type'].'_'.$data['type']]['amount'] = $tempData[$data['item_type'].'_'.$data['type']]['amount'] + $Amount;
                            }else{
                                $tempData[$data['item_type'].'_'.$data['type']]['amount'] = $Amount;
                            }
                        }
                    }

                    if(isset($shipmentItem->CostOfPointsReturned)){
                        $arrayfeeCost = json_decode(json_encode($shipmentItem->CostOfPointsReturned), true);
                        $Amount = array_get($arrayfeeCost,'CurrencyAmount',0);
                        if($Amount!=0){
                                $data['item_type']='CostOfPointsReturned';
                                $data['type']=$data['type_id']='CostOfPointsReturned';
                                $data['currency'] = array_get($arrayfeeCost,'CurrencyCode','');
                                $tempData[$data['item_type'].'_'.$data['type']]=$data;
                                if(isset($tempData[$data['item_type'].'_'.$data['type']]['amount'])){
                                    $tempData[$data['item_type'].'_'.$data['type']]['amount'] = $tempData[$data['item_type'].'_'.$data['type']]['amount'] + $Amount;
                                }else{
                                    $tempData[$data['item_type'].'_'.$data['type']]['amount'] = $Amount;
                                }
                        }
                    }
                    foreach($tempData as $temp_k =>$temp_v){
                        $model::insertIgnore(array_merge($insert_data, $temp_v));
                    }    
                }
            }


            if(isset($shipmentEvent->ShipmentItemAdjustmentList->ShipmentItem)){
                $shipmentItems = $shipmentEvent->ShipmentItemAdjustmentList->ShipmentItem;
                $orderLineNum=0;
                foreach($shipmentItems as $shipmentItem){
                    $tempData= $data=[];
                    $orderLineNum++;
                    $data['order_item_id'] = isset($shipmentItem->OrderItemId)?(string)$shipmentItem->OrderItemId:'';
                    $data['order_adjustment_item_id'] = isset($shipmentItem->OrderAdjustmentItemId)?(string)$shipmentItem->OrderAdjustmentItemId:'';
                    $data['dom_type'] ='ShipmentItemAdjustment';
                    $data['seller_sku'] = isset($shipmentItem->SellerSKU)?(string)$shipmentItem->SellerSKU:'';
                    $data['quantity_shipped'] = isset($shipmentItem->QuantityShipped)?(int)$shipmentItem->QuantityShipped:0;
                    $data['line_num'] = $orderLineNum;    
                    if(isset($shipmentItem->ItemChargeList->ChargeComponent)){
                        foreach($shipmentItem->ItemChargeList->ChargeComponent as $chargeComponent){
                            $arrayChargeComponent = json_decode(json_encode($chargeComponent), true);
                            $Amount = array_get($arrayChargeComponent,'ChargeAmount.CurrencyAmount',0);
                            if($Amount!=0){
                                $data['item_type']='ItemCharge';
                                $data['type']=$data['type_id']=array_get($arrayChargeComponent,'ChargeType','');
                                $data['currency'] = array_get($arrayChargeComponent,'ChargeAmount.CurrencyCode','');            
                                $tempData[$data['item_type'].'_'.$data['type']]=$data;
                                if(isset($tempData[$data['item_type'].'_'.$data['type']]['amount'])){
                                    $tempData[$data['item_type'].'_'.$data['type']]['amount'] = $tempData[$data['item_type'].'_'.$data['type']]['amount'] + $Amount;
                                }else{
                                    $tempData[$data['item_type'].'_'.$data['type']]['amount'] = $Amount;
                                }
                                
                                
                            }
                        }
                    }


                    if(isset($shipmentItem->ItemChargeAdjustmentList->ChargeComponent)){
                        foreach($shipmentItem->ItemChargeAdjustmentList->ChargeComponent as $chargeComponent){
                            $arrayChargeComponent = json_decode(json_encode($chargeComponent), true);
                            $Amount = array_get($arrayChargeComponent,'ChargeAmount.CurrencyAmount',0);
                            if($Amount!=0){ 
                                $data['item_type']='ItemChargeAdjustment';
                                $data['type']=$data['type_id']=array_get($arrayChargeComponent,'ChargeType','');
                                $data['currency'] = array_get($arrayChargeComponent,'ChargeAmount.CurrencyCode','');
                                $tempData[$data['item_type'].'_'.$data['type']]=$data;
                                if(isset($tempData[$data['item_type'].'_'.$data['type']]['amount'])){
                                    $tempData[$data['item_type'].'_'.$data['type']]['amount'] = $tempData[$data['item_type'].'_'.$data['type']]['amount'] + $Amount;
                                }else{
                                    $tempData[$data['item_type'].'_'.$data['type']]['amount'] = $Amount;
                                }
                            }
                        }
                    }


                    if(isset($shipmentItem->ItemTaxWithheldList->TaxWithheldComponent)){
                        foreach($shipmentItem->ItemTaxWithheldList->TaxWithheldComponent as $TaxWithheldComponent){
                            if(isset($TaxWithheldComponent->TaxesWithheld->ChargeComponent)){
                                foreach($TaxWithheldComponent->TaxesWithheld->ChargeComponent as $chargeComponent){
                                    $arrayChargeComponent = json_decode(json_encode($chargeComponent), true);
                                    $Amount = array_get($arrayChargeComponent,'ChargeAmount.CurrencyAmount',0);
                                    if($Amount!=0){
                                        $data['item_type']='ItemTaxWithheld';
                                        $data['type']=array_get($arrayChargeComponent,'ChargeType','');
                                        $data['type_id']=$TaxWithheldComponent->TaxesWithheld->TaxCollectionModel;
                                        $data['currency'] = array_get($arrayChargeComponent,'ChargeAmount.CurrencyCode','');                
                                        $tempData[$data['item_type'].'_'.$data['type'].'_'.$data['type_id']]=$data;
                                        if(isset($tempData[$data['item_type'].'_'.$data['type'].'_'.$data['type_id']]['amount'])){
                                            $tempData[$data['item_type'].'_'.$data['type'].'_'.$data['type_id']]['amount'] = $tempData[$data['item_type'].'_'.$data['type'].'_'.$data['type_id']]['amount'] + $Amount;
                                        }else{
                                            $tempData[$data['item_type'].'_'.$data['type'].'_'.$data['type_id']]['amount'] = $Amount;
                                        }
                                    }
                                }
                            }
                        }
                    }


                    if(isset($shipmentItem->ItemFeeList->FeeComponent)){
                        foreach($shipmentItem->ItemFeeList->FeeComponent as $feeComponent){
                            $arrayFeeComponent = json_decode(json_encode($feeComponent), true);
                            $Amount = array_get($arrayFeeComponent,'FeeAmount.CurrencyAmount',0);
                            if($Amount!=0){
                                $data['item_type']='ItemFee';
                                $data['type']=$data['type_id']=array_get($arrayFeeComponent,'FeeType','');   
                                $data['currency'] = array_get($arrayFeeComponent,'FeeAmount.CurrencyCode','');
                                $tempData[$data['item_type'].'_'.$data['type']]=$data;
                                if(isset($tempData[$data['item_type'].'_'.$data['type']]['amount'])){
                                    $tempData[$data['item_type'].'_'.$data['type']]['amount'] = $tempData[$data['item_type'].'_'.$data['type']]['amount'] + $Amount;
                                }else{
                                    $tempData[$data['item_type'].'_'.$data['type']]['amount'] = $Amount;
                                }
                            }
                        }
                    }


                    if(isset($shipmentItem->ItemFeeAdjustmentList->FeeComponent)){
                        foreach($shipmentItem->ItemFeeAdjustmentList->FeeComponent as $feeComponent){
                            $arrayFeeComponent = json_decode(json_encode($feeComponent), true);
                            $Amount = array_get($arrayFeeComponent,'FeeAmount.CurrencyAmount',0);
                            if($Amount!=0){
                                $data['item_type']='ItemFeeAdjustment';
                                $data['type']=$data['type_id']=array_get($arrayFeeComponent,'FeeType','');
                                $data['currency'] = array_get($arrayFeeComponent,'FeeAmount.CurrencyCode','');
                                $tempData[$data['item_type'].'_'.$data['type']]=$data;
                                if(isset($tempData[$data['item_type'].'_'.$data['type']]['amount'])){
                                    $tempData[$data['item_type'].'_'.$data['type']]['amount'] = $tempData[$data['item_type'].'_'.$data['type']]['amount'] + $Amount;
                                }else{
                                    $tempData[$data['item_type'].'_'.$data['type']]['amount'] = $Amount;
                                }
                            }
                        }
                    }


                    if(isset($shipmentItem->PromotionList->Promotion)){
                        foreach($shipmentItem->PromotionList->Promotion as $feePromotion){
                            $arrayfeePromotion = json_decode(json_encode($feePromotion), true);
                            $Amount = array_get($arrayfeePromotion,'PromotionAmount.CurrencyAmount',0);
                            if($Amount!=0){
                                $data['item_type']='Promotion';
                                $data['type']=array_get($arrayfeePromotion,'PromotionType','');
                                $data['type_id']=array_get($arrayfeePromotion,'PromotionId','');
                                $data['currency'] = array_get($arrayfeePromotion,'PromotionAmount.CurrencyCode','');
                                $tempData[$data['item_type'].'_'.$data['type'].'_'.$data['type_id']]=$data;
                                if(isset($tempData[$data['item_type'].'_'.$data['type'].'_'.$data['type_id']]['amount'])){
                                    $tempData[$data['item_type'].'_'.$data['type'].'_'.$data['type_id']]['amount'] = $tempData[$data['item_type'].'_'.$data['type'].'_'.$data['type_id']]['amount'] + $Amount;
                                }else{
                                    $tempData[$data['item_type'].'_'.$data['type'].'_'.$data['type_id']]['amount'] = $Amount;
                                }
                            }
                        }
                    }


                    if(isset($shipmentItem->PromotionAdjustmentList->Promotion)){
                        foreach($shipmentItem->PromotionAdjustmentList->Promotion as $feePromotion){
                            $arrayfeePromotion = json_decode(json_encode($feePromotion), true);
                            $Amount = array_get($arrayfeePromotion,'PromotionAmount.CurrencyAmount',0);
                            if($Amount!=0){
                                $data['item_type']='PromotionAdjustmentList';
                                $data['type']=array_get($arrayfeePromotion,'PromotionType','');
                                $data['type_id']=array_get($arrayfeePromotion,'PromotionId','');
                                $data['currency'] = array_get($arrayfeePromotion,'PromotionAmount.CurrencyCode','');
                                $tempData[$data['item_type'].'_'.$data['type'].'_'.$data['type_id']]=$data;
                                if(isset($tempData[$data['item_type'].'_'.$data['type'].'_'.$data['type_id']]['amount'])){
                                    $tempData[$data['item_type'].'_'.$data['type'].'_'.$data['type_id']]['amount'] = $tempData[$data['item_type'].'_'.$data['type'].'_'.$data['type_id']]['amount'] + $Amount;
                                }else{
                                    $tempData[$data['item_type'].'_'.$data['type'].'_'.$data['type_id']]['amount'] = $Amount;
                                }
                            }
                        }
                    }

                    if(isset($shipmentItem->CostOfPointsGranted)){
                        $arrayfeeCost = json_decode(json_encode($shipmentItem->CostOfPointsGranted), true);
                        $Amount = array_get($arrayfeeCost,'CurrencyAmount',0);
                        if($Amount!=0){
                                $data['item_type']='CostOfPointsGranted';
                                $data['type']=$data['type_id']='CostOfPointsGranted';
                                $data['currency'] = array_get($arrayfeeCost,'CurrencyCode','');
                                $tempData[$data['item_type'].'_'.$data['type']]=$data;
                                if(isset($tempData[$data['item_type'].'_'.$data['type']]['amount'])){
                                    $tempData[$data['item_type'].'_'.$data['type']]['amount'] = $tempData[$data['item_type'].'_'.$data['type']]['amount'] + $Amount;
                                }else{
                                    $tempData[$data['item_type'].'_'.$data['type']]['amount'] = $Amount;
                                }
                        }
                    }

                    if(isset($shipmentItem->CostOfPointsReturned)){
                        $arrayfeeCost = json_decode(json_encode($shipmentItem->CostOfPointsReturned), true);
                        $Amount = array_get($arrayfeeCost,'CurrencyAmount',0);
                        if($Amount!=0){
                                $data['item_type']='CostOfPointsReturned';
                                $data['type']=$data['type_id']='CostOfPointsReturned';
                                $data['currency'] = array_get($arrayfeeCost,'CurrencyCode','');
                                $tempData[$data['item_type'].'_'.$data['type']]=$data;
                                if(isset($tempData[$data['item_type'].'_'.$data['type']]['amount'])){
                                    $tempData[$data['item_type'].'_'.$data['type']]['amount'] = $tempData[$data['item_type'].'_'.$data['type']]['amount'] + $Amount;
                                }else{
                                    $tempData[$data['item_type'].'_'.$data['type']]['amount'] = $Amount;
                                }
                        }
                    }

                    foreach($tempData as $temp_k =>$temp_v){
                        $model::insertIgnore(array_merge($insert_data, $temp_v));    
                    }
                }
            }


            $tempData=$data=[];
            $data['line_num'] = 1;
            if(isset($shipmentEvent->OrderChargeList->ChargeComponent)){
                foreach($shipmentEvent->OrderChargeList->ChargeComponent as $chargeComponent){
                    $arrayChargeComponent = json_decode(json_encode($chargeComponent), true);
                    $Amount = array_get($arrayChargeComponent,'ChargeAmount.CurrencyAmount',0);
                    if($Amount!=0){ 
                        $data['item_type']='OrderCharge';
                        $data['type']=$data['type_id']=array_get($arrayChargeComponent,'ChargeType','');
                        $data['currency'] = array_get($arrayChargeComponent,'ChargeAmount.CurrencyCode','');
                        $tempData[$data['item_type'].'_'.$data['type']]=$data;
                        if(isset($tempData[$data['item_type'].'_'.$data['type']]['amount'])){
                            $tempData[$data['item_type'].'_'.$data['type']]['amount'] = $tempData[$data['item_type'].'_'.$data['type']]['amount'] + $Amount;
                        }else{
                            $tempData[$data['item_type'].'_'.$data['type']]['amount'] = $Amount;
                        }
                    }
                }
            }

            if(isset($shipmentEvent->OrderChargeAdjustmentList->ChargeComponent)){
                foreach($shipmentEvent->OrderChargeAdjustmentList->ChargeComponent as $chargeComponent){
                    $arrayChargeComponent = json_decode(json_encode($chargeComponent), true);
                    $Amount = array_get($arrayChargeComponent,'ChargeAmount.CurrencyAmount',0);
                    if($Amount!=0){ 
                        $data['item_type']='OrderChargeAdjustment';
                        $data['type']=$data['type_id']=array_get($arrayChargeComponent,'ChargeType','');
                        $data['currency'] = array_get($arrayChargeComponent,'ChargeAmount.CurrencyCode','');
                        $tempData[$data['item_type'].'_'.$data['type']]=$data;
                        if(isset($tempData[$data['item_type'].'_'.$data['type']]['amount'])){
                            $tempData[$data['item_type'].'_'.$data['type']]['amount'] = $tempData[$data['item_type'].'_'.$data['type']]['amount'] + $Amount;
                        }else{
                            $tempData[$data['item_type'].'_'.$data['type']]['amount'] = $Amount;
                        }
                    }
                }
            }


            if(isset($shipmentEvent->ShipmentFeeList->FeeComponent)){
                foreach($shipmentEvent->ShipmentFeeList->FeeComponent as $feeComponent){
                    $arrayFeeComponent = json_decode(json_encode($feeComponent), true);
                    $Amount = array_get($arrayFeeComponent,'FeeAmount.CurrencyAmount',0);
                    if($Amount!=0){
                        $data['item_type']='ShipmentFee';
                        $data['type']=$data['type_id']=array_get($arrayFeeComponent,'FeeType','');
                        $data['currency'] = array_get($arrayFeeComponent,'FeeAmount.CurrencyCode','');  
                        $tempData[$data['item_type'].'_'.$data['type']]=$data;
                        if(isset($tempData[$data['item_type'].'_'.$data['type']]['amount'])){
                            $tempData[$data['item_type'].'_'.$data['type']]['amount'] = $tempData[$data['item_type'].'_'.$data['type']]['amount'] + $Amount;
                        }else{
                            $tempData[$data['item_type'].'_'.$data['type']]['amount'] = $Amount;
                        }
                    }
                }
            }

            if(isset($shipmentEvent->ShipmentFeeAdjustmentList->FeeComponent)){
                foreach($shipmentEvent->ShipmentFeeAdjustmentList->FeeComponent as $feeComponent){
                    $arrayFeeComponent = json_decode(json_encode($feeComponent), true);
                    $Amount = array_get($arrayFeeComponent,'FeeAmount.CurrencyAmount',0);
                    if($Amount!=0){
                        $data['item_type']='ShipmentFeeAdjustment';
                        $data['type']=$data['type_id']=array_get($arrayFeeComponent,'FeeType','');
                        $data['amount']=array_get($arrayFeeComponent,'FeeAmount.CurrencyAmount',0);;    
                        $data['currency'] = array_get($arrayFeeComponent,'FeeAmount.CurrencyCode','');  
                        $tempData[$data['item_type'].'_'.$data['type']]=$data;
                        if(isset($tempData[$data['item_type'].'_'.$data['type']]['amount'])){
                            $tempData[$data['item_type'].'_'.$data['type']]['amount'] = $tempData[$data['item_type'].'_'.$data['type']]['amount'] + $Amount;
                        }else{
                            $tempData[$data['item_type'].'_'.$data['type']]['amount'] = $Amount;
                        }
                    }
                }
            }

            if(isset($shipmentEvent->OrderFeeList->FeeComponent)){
                foreach($shipmentEvent->OrderFeeList->FeeComponent as $feeComponent){
                    $arrayFeeComponent = json_decode(json_encode($feeComponent), true);
                    $Amount = array_get($arrayFeeComponent,'FeeAmount.CurrencyAmount',0);
                    if($Amount!=0){
                        $data['item_type']='OrderFee';
                        $data['type']=$data['type_id']=array_get($arrayFeeComponent,'FeeType','');
                        $data['currency'] = array_get($arrayFeeComponent,'FeeAmount.CurrencyCode','');
                        
                        $tempData[$data['item_type'].'_'.$data['type']]=$data;
                        if(isset($tempData[$data['item_type'].'_'.$data['type']]['amount'])){
                            $tempData[$data['item_type'].'_'.$data['type']]['amount'] = $tempData[$data['item_type'].'_'.$data['type']]['amount'] + $Amount;
                        }else{
                            $tempData[$data['item_type'].'_'.$data['type']]['amount'] = $Amount;
                        }
                            
                    }
                }
            }


            if(isset($shipmentEvent->OrderFeeAdjustmentList->FeeComponent)){
                foreach($shipmentEvent->OrderFeeAdjustmentList->FeeComponent as $feeComponent){
                    $arrayFeeComponent = json_decode(json_encode($feeComponent), true);
                    $Amount=array_get($arrayFeeComponent,'FeeAmount.CurrencyAmount',0);;
                    if($Amount!=0){
                        $data['item_type']='OrderFeeAdjustment';
                        $data['type']=$data['type_id']=array_get($arrayFeeComponent,'FeeType','');
                        $data['currency'] = array_get($arrayFeeComponent,'FeeAmount.CurrencyCode','');
                        
                        $tempData[$data['item_type'].'_'.$data['type']]=$data;
                        if(isset($tempData[$data['item_type'].'_'.$data['type']]['amount'])){
                            $tempData[$data['item_type'].'_'.$data['type']]['amount'] = $tempData[$data['item_type'].'_'.$data['type']]['amount'] + $Amount;
                        }else{
                            $tempData[$data['item_type'].'_'.$data['type']]['amount'] = $Amount;
                        }   
                    }
                }
            }


            if(isset($shipmentEvent->DirectPaymentList->DirectPayment)){
                foreach($shipmentEvent->DirectPaymentList->DirectPayment as $feeComponent){
                    $arrayFeeComponent = json_decode(json_encode($feeComponent), true);
                    $Amount = array_get($arrayFeeComponent,'DirectPaymentAmount.CurrencyAmount',0);
                    if($Amount!=0){
                        $data['item_type']='DirectPayment';
                        $data['type']=$data['type_id']=array_get($arrayFeeComponent,'DirectPaymentType','');
                        $data['currency'] = array_get($arrayFeeComponent,'DirectPaymentAmount.CurrencyCode','');

                        $tempData[$data['item_type'].'_'.$data['type']]=$data;
                        if(isset($tempData[$data['item_type'].'_'.$data['type']]['amount'])){
                            $tempData[$data['item_type'].'_'.$data['type']]['amount'] = $tempData[$data['item_type'].'_'.$data['type']]['amount'] + $Amount;
                        }else{
                            $tempData[$data['item_type'].'_'.$data['type']]['amount'] = $Amount;
                        }
                    }
                }
            }
            
            foreach($tempData as $temp_k =>$temp_v){
                $model::insertIgnore(array_merge($insert_data, $temp_v));    
            }
        }

    }

}
