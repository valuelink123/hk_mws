<?php
namespace App;

use Illuminate\Database\Eloquent\Model;
use App\Services\Traits\ExtendedMysqlQueries;
class AsinOfferBuybox extends Model
{
    use  ExtendedMysqlQueries;
    protected $table = 'asin_offer_buybox';
    protected $guarded = [];
    public $timestamps = true;
}
