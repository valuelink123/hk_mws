<?php namespace App;

use App\Services\Traits\ExtendedMysqlQueries;
use Illuminate\Database\Eloquent\Model;
use App\SapPurchase;
use App\SapShipment;
use App\SapAsinMatchSku;
use App\ShipmentRequest;

class AsinSalesPlan extends Model {

    use  ExtendedMysqlQueries;
    protected $guarded = [];
    public $timestamps = false;
	
	static function calPlans($asin,$marketplace_id,$sku,$date_from,$date_to)
    {	
        $estimated_shipment_datas = ShipmentRequest::selectRaw('sum(quantity) as quantity,received_date as date')->where('asin',$asin)->where('marketplace_id',$marketplace_id)->where('received_date','>=',$date_from)->where('received_date','<=',$date_to)->where('shipment_completed',0)->whereNotNull('shipment_id')->where('status','<>',4)->groupBy(['date'])->pluck('quantity','date');
		
		$estimated_purchase_datas = SapPurchase::selectRaw('sum(quantity) as quantity,estimated_delivery_date as date')->where('sku',$sku)->where('estimated_delivery_date','>=',$date_from)->where('estimated_delivery_date','<=',$date_to)->whereNull('actual_delivery_date')->groupBy(['date'])->pluck('quantity','date');
		
		$plans = AsinSalesPlan::where('asin',$asin)->where('marketplace_id',$marketplace_id)->where('date','>=',$date_from)->where('date','<=',$date_to)->pluck('quantity_last','date');
		
		$data = [];
		$quantity_miss = 0;
		$requestTime = date('Y-m-d H:i:s');
		while($date_from<=$date_to){
			$quantity_miss += (intval(array_get($plans,$date_from))-intval(array_get($estimated_shipment_datas,$date_from)));
			$data[] = array(
				'asin'=>$asin,
				'marketplace_id'=>$marketplace_id,
				'sku'=>$sku,
				'date'=>$date_from,
				'week_date'=>date('Y-m-d',strtotime("$date_from Sunday")),
				'estimated_afn'=>intval(array_get($estimated_shipment_datas,$date_from)),
				'estimated_purchase'=>intval(array_get($estimated_purchase_datas,$date_from)),
				'quantity_last'=>intval(array_get($plans,$date_from)),
				'quantity_miss'=>$quantity_miss,
				'updated_at'=>$requestTime
			);
			$date_from = date('Y-m-d',strtotime($date_from)+86400);
		}
		if($data){
			AsinSalesPlan::insertOnDuplicateWithDeadlockCatching($data, ['week_date','estimated_afn','estimated_purchase','quantity_last','quantity_miss','updated_at']);
		}
    }

}
