<?php
namespace App;

use Illuminate\Database\Eloquent\Model;
use App\Services\Traits\ExtendedMysqlQueries;
class FbaMultiCountryInventoryReport extends Model
{
    use  ExtendedMysqlQueries;
    protected $table = 'fba_multi_country_inventory_report';
}
