<?php
namespace App;

use Illuminate\Database\Eloquent\Model;
use App\Services\Traits\ExtendedMysqlQueries;
class FbaAmazonFulfilledInventoryReport extends Model
{
    use  ExtendedMysqlQueries;
    protected $table = 'fba_amazon_fulfilled_inventory_report';
}
