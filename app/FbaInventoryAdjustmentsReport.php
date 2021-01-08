<?php
namespace App;

use Illuminate\Database\Eloquent\Model;
use App\Services\Traits\ExtendedMysqlQueries;
class FbaInventoryAdjustmentsReport extends Model
{
    use  ExtendedMysqlQueries;
    protected $table = 'fba_inventory_adjustments_report';
}
