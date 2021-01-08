<?php
namespace App;

use Illuminate\Database\Eloquent\Model;
use App\Services\Traits\ExtendedMysqlQueries;
class FbaManageInventoryArchived extends Model
{
    use  ExtendedMysqlQueries;
    protected $table = 'fba_manage_inventory_archived';
}
