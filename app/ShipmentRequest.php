<?php namespace App;

use App\Services\Traits\ExtendedMysqlQueries;
use Illuminate\Database\Eloquent\Model;
class ShipmentRequest extends Model {
    use  ExtendedMysqlQueries;
    protected $guarded = [];
}
