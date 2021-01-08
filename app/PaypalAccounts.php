<?php

namespace App;
use App\Services\Traits\ExtendedMysqlQueries;
use Illuminate\Database\Eloquent\Model;

class PaypalAccounts extends Model
{
    use  ExtendedMysqlQueries;
    protected $table = 'paypal_accounts';
    protected $guarded = [];
    public  $timestamps = true;

}
