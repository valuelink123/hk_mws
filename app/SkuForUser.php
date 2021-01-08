<?php namespace App;

use App\Services\Traits\ExtendedMysqlQueries;
use Illuminate\Database\Eloquent\Model;

class SkuForUser extends Model {

    use  ExtendedMysqlQueries;
    protected $guarded = [];
    public $timestamps = false;
    protected $table = 'sku_for_user';


}
