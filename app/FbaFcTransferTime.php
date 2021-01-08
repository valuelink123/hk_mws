<?php namespace App;

use App\Services\Traits\ExtendedMysqlQueries;
use Illuminate\Database\Eloquent\Model;

class FbaFcTransferTime extends Model {

    use  ExtendedMysqlQueries;
    protected $guarded = [];
	protected $table = 'fba_fc_transfer_time';
    public $timestamps = false;

}
