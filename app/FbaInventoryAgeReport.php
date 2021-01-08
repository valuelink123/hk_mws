<?php
namespace App;

use Illuminate\Database\Eloquent\Model;
use App\Services\Traits\ExtendedMysqlQueries;
class FbaInventoryAgeReport extends Model
{
    use  ExtendedMysqlQueries;
    protected $table = 'fba_inventory_age_report';
}
