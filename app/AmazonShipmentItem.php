<?php namespace App;

use App\Services\Traits\ExtendedMysqlQueries;
use Illuminate\Database\Eloquent\Model;

class AmazonShipmentItem extends Model {

    use  ExtendedMysqlQueries;
    protected $guarded = [];
    public $timestamps = false;

}
