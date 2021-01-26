<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\SellerAccounts;
use App\OrderItem;
use App\Order;
use App\SapOrderItem;
use App\SapOrder;
class InsertOrderToSap extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'push:order {--sellerAccountId=} {--afterDate=} {--beforeDate=}';

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
		$afterDate = $this->option('afterDate')?date('Y-m-d H:i:s',strtotime($this->option('afterDate'))):date('Y-m-d H:i:s',strtotime('- 4hours'));
        $beforeDate = $this->option('beforeDate')?date('Y-m-d H:i:s',strtotime($this->option('beforeDate'))):date('Y-m-d H:i:s');
        $sellerAccounts = SellerAccounts::all()->toArray();
        foreach($sellerAccounts as $sellerAccount){
            $sellerAccountId[$sellerAccount['id']]=[
                'seller_id'=>$sellerAccount['mws_seller_id'],
                'marketplace_id'=>$sellerAccount['mws_marketplaceid']
            ];
        }
        $pushRules = [
            ['fulfillment_channel'=>'AFN','order_status'=>'Shipped'],
            ['fulfillment_channel'=>'MFN','order_status'=>'Unshipped'],
        ];
        foreach($pushRules as $rules){
            $matchOrders = Order::where('last_update_date','>=',$afterDate)->where('last_update_date','<=',$beforeDate);
            if($this->option('sellerAccountId')) $matchOrders=$matchOrders->where('seller_account_id',$this->option('sellerAccountId'));
            foreach($rules as $key=>$val){
                $matchOrders = $matchOrders->where($key,$val);
            }
            $matchOrders->chunk(10,function($orders){
                $sapInsertItemData = $sapInsertData = [];
                foreach ($orders as $order) {
                    $order=json_decode(json_encode($order),true);
                    $account = array_get($sellerAccountId,$order['seller_account_id']);
                    if(!$account) continue;

                    $orderItems = OrderItem::where('seller_account_id',$order['seller_account_id'])->where('amazon_order_id',$order['amazon_order_id'])->get()->toArray();
                    foreach($orderItems as $orderItem){
                        foreach(['id','user_id','seller_account_id','purchase_date','fulfillment_channel'] as $key){
                            unset($orderItem[$key]);
                        }
                        $fields = array_merge($account,$orderItem);
                        $data = [];
                        foreach($fields as $field=>$value){
                            $data[str_replace(' ','',ucwords(str_replace('_', ' ', $field)))] = $value;
                        }
                        $sapInsertItemData[] = $data;
                    }

                    foreach(['id','user_id','seller_account_id','purchase_local_date','created_at','updated_at','order_type','asins','seller_skus'] as $key){
                        unset($order[$key]);
                    }
                    $fields = array_merge($account,$order);
                    $data = [];
                    foreach($fields as $field=>$value){
                        $data[str_replace(' ','',ucwords(str_replace('_', ' ', $field)))] = $value;
                    }
                    $sapInsertData[] = $data;
                    
                }
                if($sapInsertItemData){
                    SapOrderItem::insertIgnore($sapInsertItemData);
                    SapOrder::insertIgnore($sapInsertData);
                }
            });
        }
        

        
    }
}
