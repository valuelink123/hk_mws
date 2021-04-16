<?php
namespace App;

use Illuminate\Database\Eloquent\Model;
use App\Services\Traits\ExtendedMysqlQueries;
class SellerAsin extends Model
{
    use  ExtendedMysqlQueries;
    protected $guarded = [];
}
