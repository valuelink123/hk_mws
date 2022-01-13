<?php namespace App;

use App\Services\Traits\ExtendedMysqlQueries;
use Illuminate\Database\Eloquent\Model;

class AsinMatchRelation extends Model {

    use  ExtendedMysqlQueries;
    public $table='asin_match_relation';
    protected $guarded = [];
    public $timestamps = false;

}
