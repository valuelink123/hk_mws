<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use App\Services\Traits\ExtendedMysqlQueries;
class AmazonMcfOrdersItem extends Model
{
    use  ExtendedMysqlQueries;
    public $timestamps = false;
    protected $guarded = [];
    protected $table = 'amazon_mcf_orders_item';
}
