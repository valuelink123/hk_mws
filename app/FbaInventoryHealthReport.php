<?php
namespace App;

use Illuminate\Database\Eloquent\Model;
use App\Services\Traits\ExtendedMysqlQueries;
class FbaInventoryHealthReport extends Model
{
    use  ExtendedMysqlQueries;
    protected $table = 'fba_inventory_health_report';
}
