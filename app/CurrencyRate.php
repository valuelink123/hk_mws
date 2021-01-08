<?php namespace App;

use App\Services\Traits\ExtendedMysqlQueries;
use Illuminate\Database\Eloquent\Model;

class CurrencyRate extends Model {

    use  ExtendedMysqlQueries;
    protected $guarded = [];
    public $timestamps = false;

}
