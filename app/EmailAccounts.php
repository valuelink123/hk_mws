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

class EmailAccounts extends Model
{
    use  ExtendedMysqlQueries;
    protected $table = 'email_accounts';
    protected $guarded = [];
    public  $timestamps = true;

}
