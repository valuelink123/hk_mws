<?php
namespace App;

use Illuminate\Database\Eloquent\Model;
use App\Services\Traits\ExtendedMysqlQueries;
class RestockInventoryReport extends Model
{
    use  ExtendedMysqlQueries;
    protected $table = 'restock_inventory_report';
}
