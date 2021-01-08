<?php
namespace App;

use Illuminate\Database\Eloquent\Model;
use App\Services\Traits\ExtendedMysqlQueries;
class FbaMonthlyInventoryHistoryReport extends Model
{
    use  ExtendedMysqlQueries;
    protected $table = 'fba_monthly_inventory_history_report';
}
