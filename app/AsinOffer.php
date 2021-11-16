<?php
namespace App;

use Illuminate\Database\Eloquent\Model;
use App\Services\Traits\ExtendedMysqlQueries;
class AsinOffer extends Model
{
    use  ExtendedMysqlQueries;
    protected $table = 'asin_offers';
    protected $guarded = [];
    public $timestamps = true;
}
