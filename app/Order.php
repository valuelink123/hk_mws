<?php namespace App;

use App\Services\Traits\ExtendedMysqlQueries;
use Illuminate\Database\Eloquent\Model;

class Order extends Model {

    use  ExtendedMysqlQueries;

    protected $table = 'orders';
    protected $hidden = ['updated_at'];
    protected $guarded = [];

}
