<?php
namespace App;

use Illuminate\Database\Eloquent\Model;
use App\Services\Traits\ExtendedMysqlQueries;
class FbaStrandedInventoryReport extends Model
{
    use  ExtendedMysqlQueries;
    protected $table = 'fba_stranded_inventory_report';
}
