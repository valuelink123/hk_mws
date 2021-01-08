<?php

namespace App;
use App\Services\Traits\ExtendedMysqlQueries;
use Illuminate\Database\Eloquent\Model;

class PaymentsHistory extends Model
{
    use  ExtendedMysqlQueries;
    protected $table = 'payments_history';
    protected $guarded = [];
    public  $timestamps = false;

}
