<?php namespace App;

use App\Services\Traits\ExtendedMysqlQueries;
use Illuminate\Database\Eloquent\Model;

class SapOrderItem extends Model {

    use  ExtendedMysqlQueries;
	protected $connection = 'sapMiddleWare';
    protected $table = 'amazon_orders_item';
    protected $guarded = [];
    public $timestamps = false;

}
