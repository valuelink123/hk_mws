<?php namespace App;

use App\Services\Traits\ExtendedMysqlQueries;
use Illuminate\Database\Eloquent\Model;

class SapAsinMatchSku extends Model {

    use  ExtendedMysqlQueries;
    public $table='sap_asin_match_sku';
    protected $guarded = [];
    public $timestamps = false;

}
