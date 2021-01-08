<?php namespace App;

use App\Services\Traits\ExtendedMysqlQueries;
use Illuminate\Database\Eloquent\Model;

class ProductBsr extends Model {

    use  ExtendedMysqlQueries;
    protected $guarded = [];
    public $timestamps = false;

}
