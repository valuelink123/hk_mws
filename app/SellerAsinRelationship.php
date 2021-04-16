<?php
namespace App;

use Illuminate\Database\Eloquent\Model;
use App\Services\Traits\ExtendedMysqlQueries;
class SellerAsinRelationship extends Model
{
    use  ExtendedMysqlQueries;
    protected $guarded = [];
}
