<?php namespace App;

use App\Services\Traits\ExtendedMysqlQueries;
use Illuminate\Database\Eloquent\Model;

class AmazonSettlement extends Model {

    use  ExtendedMysqlQueries;

    protected $table = 'amazon_settlements';
    protected $guarded = [];

}
