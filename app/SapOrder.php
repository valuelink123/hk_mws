<?php namespace App;

use App\Services\Traits\ExtendedMysqlQueries;
use Illuminate\Database\Eloquent\Model;

class SapOrder extends Model {

    use  ExtendedMysqlQueries;
	protected $connection = 'sapMiddleWare';
    protected $table = 'amazon_orders';
    protected $guarded = [];
    public $timestamps = false;

}
