<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use App\Services\Traits\ExtendedMysqlQueries;
class AmazonMcfShipmentItem extends Model
{
    use  ExtendedMysqlQueries;
    public $timestamps = false;
    protected $guarded = [];
    protected $table = 'amazon_mcf_shipment_item';
}
