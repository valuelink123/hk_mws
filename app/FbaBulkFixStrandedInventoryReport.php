<?php
namespace App;

use Illuminate\Database\Eloquent\Model;
use App\Services\Traits\ExtendedMysqlQueries;
class FbaBulkFixStrandedInventoryReport extends Model
{
    use  ExtendedMysqlQueries;
    protected $table = 'fba_bulk_fix_stranded_inventory_report';
}
