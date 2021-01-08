<?php
namespace App;

use Illuminate\Database\Eloquent\Model;
use App\Services\Traits\ExtendedMysqlQueries;
class FbaReceivedInventoryReport extends Model
{
    use  ExtendedMysqlQueries;
    protected $table = 'fba_received_inventory_report';
}
