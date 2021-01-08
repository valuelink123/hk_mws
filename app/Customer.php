<?php

namespace App;

use App\SellerAccounts;
use App\Services\Traits\ExtendedMysqlQueries;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use  ExtendedMysqlQueries;

    protected $table = 'customers';
    protected $hidden = ['created_at','updated_at'];
    protected $guarded = [];


    public static function addCustomersFromOrders($orders,SellerAccounts $sellerAccount)
    {
        $customerEmails = [];
        foreach ($orders as $order)
        {
            if (isset($order['BuyerEmail'])&&!empty($order['BuyerEmail']))
            {
                $customerEmails[] = $order['BuyerEmail'];
            }
        }
        $models = static::whereIn('email',$customerEmails)->where('seller_account_id',$sellerAccount)->get();
        $insertData = [];
        foreach ($orders as $order)
        {
            if (isset($order['BuyerEmail'])&&!empty($order['BuyerEmail']) &&($order['OrderStatus']=='Shipped'))
            {
                $email = $order['BuyerEmail'];
                if (!isset($insertData[$email])) {
                    $existedModel = $models->first(function ($key, $value) use ($email) {
                        return $value->email == $email;
                    });
                    $insertData[$email] = ['user_id' => $sellerAccount->user_id, 'seller_account_id' => $sellerAccount->id, 'email' => $email, 'name' => isset($order['BuyerName'])?$order['BuyerName']:null, 'orders_count' => $existedModel ? $existedModel->orders_count : 0, 'created_at' => Carbon::createFromTimestamp(strtotime($order['PurchaseDate']))->toDateTimeString(), 'updated_at' => Carbon::now()->toDateTimeString()];
                }
                $insertData[$email]['orders_count']++;
            }
        }

        static::insertOnDuplicateWithDeadlockCatching(array_values($insertData),['updated_at','orders_count']);

    }

}

