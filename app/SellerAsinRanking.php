<?php
namespace App;

use Illuminate\Database\Eloquent\Model;
use App\Services\Traits\ExtendedMysqlQueries;
class SellerAsinRanking extends Model
{
    use  ExtendedMysqlQueries;
    protected $guarded = [];
}
