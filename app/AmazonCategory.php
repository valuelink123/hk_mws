<?php
namespace App;

use Illuminate\Database\Eloquent\Model;
use App\Services\Traits\ExtendedMysqlQueries;
class AmazonCategory extends Model
{
    use  ExtendedMysqlQueries;
    protected $guarded = [];
}
