<?php
namespace App;

use Illuminate\Database\Eloquent\Model;
use App\Services\Traits\ExtendedMysqlQueries;
class FbaDailyInventoryHistoryReport extends Model
{
    use  ExtendedMysqlQueries;
    protected $table = 'fba_daily_inventory_history_report';
}
