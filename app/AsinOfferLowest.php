<?php
namespace App;

use Illuminate\Database\Eloquent\Model;
use App\Services\Traits\ExtendedMysqlQueries;
class AsinOfferLowest extends Model
{
    use  ExtendedMysqlQueries;
    protected $table = 'asin_offer_lowest';
    protected $guarded = [];
    public $timestamps = true;
}
