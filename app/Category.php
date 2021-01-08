<?php

namespace App;
use App\Services\Traits\ExtendedMysqlQueries;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use  ExtendedMysqlQueries;
    protected $table = 'category';
    protected $guarded = [];

}
