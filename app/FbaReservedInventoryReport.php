<?php
namespace App;

use Illuminate\Database\Eloquent\Model;
use App\Services\Traits\ExtendedMysqlQueries;
class FbaReservedInventoryReport extends Model
{
    use  ExtendedMysqlQueries;
    protected $table = 'fba_reserved_inventory_report';
}
