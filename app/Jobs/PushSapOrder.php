<?php

namespace App\Jobs;
use App\SellerAccounts;
use App\OrderItem;
use App\Order;
use App\SapOrderItem;
use App\SapOrder;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;

class PushSapOrder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct()
    { 
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $logFile = storage_path('logs/pushSap-'.date('Y-m-d').'.log');
        $fp = fopen($logFile,"a+");
        if (flock($fp, LOCK_EX | LOCK_NB)) {
            try {
                fwrite($fp,'Start '.date('Y-m-d H:i:s').PHP_EOL);
                $sellerAccounts = SellerAccounts::all()->toArray();
                foreach($sellerAccounts as $sellerAccount){
                    $sellerAccountId[$sellerAccount['id']]=[
                        'seller_id'=>$sellerAccount['mws_seller_id'],
                        'marketplace_id'=>$sellerAccount['mws_marketplaceid']
                    ];
                }
                $matchOrders = Order::where('vop_flag',1)
                ->where('sap_imported',0)
                ->whereRaw("((fulfillment_channel='AFN' and order_status='Shipped') or (fulfillment_channel='MFN' and order_status='Unshipped'))")
                ->orderBy('last_update_date','asc')
                ->take(100)->get()->toArray();
                $matchOrders = array_chunk( $matchOrders , 10);
                foreach($matchOrders as $orders){
                    $sapInsertItemData = $sapInsertData = $orderLists = $orderIdImported = [];
                    foreach ($orders as $order) {
                        $orderIdImported[] = $order['id'];
                        $account = array_get($sellerAccountId,$order['seller_account_id']);
                        if(!$account){
                            $orderLists[] = $order['amazon_order_id'].' Non-Account';
                            continue;
                        }
                        $orderItems = OrderItem::where('seller_account_id',$order['seller_account_id'])->where('amazon_order_id',$order['amazon_order_id'])->get()->toArray();
                        $hasItems = false;
                        foreach($orderItems as $orderItem){
                            if(removeAsin($account['seller_id'],$orderItem['asin'])) continue;        
                            foreach(['id','user_id','seller_account_id','purchase_date','fulfillment_channel'] as $key){
                                unset($orderItem[$key]);
                            }
                            $fields = array_merge($account,$orderItem);
                            $data = [];
                            foreach($fields as $field=>$value){
                                $data[str_replace(' ','',ucwords(str_replace('_', ' ', $field)))] = $value;
                            }
                            $hasItems = true;
                            $sapInsertItemData[] = $data;
                            
                        }
                        if(!$hasItems){
                            $orderLists[] = $order['amazon_order_id'].' Remove-Asin';
                            continue;
                        }
                        $orderLists[] = $order['amazon_order_id'];
						$order['api_download_date'] = $order['created_at'];
                        foreach(['id','user_id','seller_account_id','purchase_local_date','created_at','updated_at','order_type','asins','seller_skus','vop_flag','sap_imported'] as $key){
                            unset($order[$key]);
                        }
                        $fields = array_merge($account,$order);
                        
                        $data=[];

                        foreach($fields as $field=>$value){
                            $data[str_replace(' ','',ucwords(str_replace('_', ' ', $field)))] = $value;
                        }
                        $sapInsertData[] = $data; 
                    }
                    if($sapInsertItemData){
                        SapOrderItem::insertIgnore($sapInsertItemData);
                        SapOrder::insertIgnore($sapInsertData);
                    }
                    if($orderLists) fwrite($fp,implode(PHP_EOL,$orderLists).PHP_EOL.'Success '.date('Y-m-d H:i:s').PHP_EOL);
                    if($orderIdImported) Order::whereIn('id',$orderIdImported)->update(['sap_imported'=>1]);
                } 
            } catch (Exception $ex) {
                throw $ex;
            }finally{
                flock($fp,LOCK_UN); 
                fclose($fp); 
            }
        }else{
            fclose($fp);
        }
    }
}
