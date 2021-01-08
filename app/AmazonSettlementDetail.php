<?php namespace App;

use App\Services\Traits\ExtendedMysqlQueries;
use Illuminate\Database\Eloquent\Model;

class AmazonSettlementDetail extends Model {

    use  ExtendedMysqlQueries;

    protected $table = 'amazon_settlement_details';
    protected $guarded = [];

}
