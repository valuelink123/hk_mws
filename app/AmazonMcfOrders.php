<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use App\Services\Traits\ExtendedMysqlQueries;
class AmazonMcfOrders extends Model
{
    use  ExtendedMysqlQueries;
    public $timestamps = false;
    protected $guarded = [];
    protected $table = 'amazon_mcf_orders';
}
