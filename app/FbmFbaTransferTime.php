<?php namespace App;

use App\Services\Traits\ExtendedMysqlQueries;
use Illuminate\Database\Eloquent\Model;

class FbmFbaTransferTime extends Model {

    use  ExtendedMysqlQueries;
    protected $guarded = [];
	protected $table = 'fbm_fba_transfer_time';
    public $timestamps = false;

}
