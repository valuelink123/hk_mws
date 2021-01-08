<?php
/**
 * App\Models\SellerProductsIds
 *
 * @property integer $user_id
 * @property integer $seller_account_id
 * @property array $products ([[sku:'', asin:'', quantity: ''],[sku:'', asin:'', quantity: '']])
 **/


namespace App;
use App\Services\Traits\ExtendedMysqlQueries;
use Illuminate\Database\Eloquent\Model;

class FinancesRefundEvent extends Model
{
    use  ExtendedMysqlQueries;
    protected $guarded = [];

}
