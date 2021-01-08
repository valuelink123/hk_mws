<?php

namespace App;
use App\Services\Traits\ExtendedMysqlQueries;
use Illuminate\Database\Eloquent\Model;
use App\Commands\GetProductsForAccount;
use App\Services\Traits\MultipleQueue;
use Carbon\Carbon;
use App\SellerProductsIds;
class SellerAccounts extends Model
{
    use  ExtendedMysqlQueries;
    
    protected $table = 'seller_accounts';

    public function lock(){

    }
    public function unlock(){

    }

    public function assignProducts(array $orders,SellerAccounts $account,$scanImmediately = true)
    {
        $product_updata = $orderAsins = [];
        foreach ($orders as $order)
        {
            if (!isset($order['Items'])) continue;
            foreach($order['Items'] as $ordersItem){
                if(!array_get($ordersItem,'SellerSKU','')) continue;
                $orderAsins[array_get($ordersItem,'SellerSKU','')]['title'] = array_get($ordersItem,'Title','');
                $orderAsins[array_get($ordersItem,'SellerSKU','')]['asin'] = array_get($ordersItem,'Asin','');
                $orderAsins[array_get($ordersItem,'SellerSKU','')]['price'] = round(array_get($ordersItem,'ItemPrice.Amount',0),2);
            }
        }


        foreach($orderAsins as $k=>$v){
            $product_updata[]=array(
                'user_id'=>$account->user_id,
                'seller_account_id'=>$account->id,
                'marketplaceid'=>$account->mws_marketplaceid,
                'seller_sku'=>$k,
                'asin'=>array_get($v,'asin',''),
                'title'=>array_get($v,'title',''),
                'price'=>round(array_get($v,'price',0),2),
                'not_found'=>0
            );
        }

        if($product_updata){
            //MultipleQueue::pushOn(MultipleQueue::DB_WRITE, function () use ($product_updata) {
            Product::insertOnDuplicateWithDeadlockCatching($product_updata, ['not_found','price','title']);
            //});
        }
    }
}
