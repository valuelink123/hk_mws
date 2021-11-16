<?php
namespace App;

use Illuminate\Database\Eloquent\Model;
use App\Services\Traits\ExtendedMysqlQueries;
class AsinOfferSummary extends Model
{
    use  ExtendedMysqlQueries;
    protected $table = 'asin_offer_summary';
    protected $guarded = [];
    public $timestamps = true;
}
