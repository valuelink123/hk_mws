<?php
/**
 * App\Models\SellerProductsIds
 *
 * @property integer $user_id
 * @property integer $seller_account_id
 * @property array $products ([[sku:'', asin:'', quantity: ''],[sku:'', asin:'', quantity: '']])
 **/


namespace App;
use Illuminate\Database\Eloquent\Model;
use App\Services\Traits\MultipleQueue;

class SellerMwsRequest extends Model
{


    



}
