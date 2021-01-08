<?php
namespace App;

use Illuminate\Database\Eloquent\Model;
use App\Services\Traits\ExtendedMysqlQueries;
class FbaInventoryEventDetailReport extends Model
{
    use  ExtendedMysqlQueries;
    protected $table = 'fba_inventory_event_detail_report';
}
