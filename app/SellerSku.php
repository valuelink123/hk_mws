<?php
namespace App;

use Illuminate\Database\Eloquent\Model;
use App\Services\Traits\ExtendedMysqlQueries;
class SellerSku extends Model
{
    use  ExtendedMysqlQueries;
    protected $table = 'seller_skus';
}
