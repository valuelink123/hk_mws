<?php
namespace App;

use Illuminate\Database\Eloquent\Model;
use App\Services\Traits\ExtendedMysqlQueries;
use Illuminate\Support\Facades\Auth;
class RequestReport extends Model
{
    use  ExtendedMysqlQueries;
    protected $table = 'request_report';
}
