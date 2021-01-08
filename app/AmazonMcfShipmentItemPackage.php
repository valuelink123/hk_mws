<?php

namespace App;
use App\Services\Traits\ExtendedMysqlQueries;
use Illuminate\Database\Eloquent\Model;

class AmazonMcfShipmentItemPackage extends Model
{
    use  ExtendedMysqlQueries;
    public $timestamps = false;
    protected $guarded = [];
    protected $table = 'amazon_mcf_shipment_package';
}
