<?php
namespace App\Classes;
use App\SellerAccounts;
use App\Asin;
use App\SellerSku;
use App\SellerAsin;
use App\AmazonReturn;
use App\FbaAmazonFulfilledInventoryReport;
use App\FbaManageInventoryArchived;
use App\FbaMultiCountryInventoryReport;
use App\FbaInventoryEventDetailReport;
use App\FbaInventoryAgeReport;
use App\FbaDailyInventoryHistoryReport;
use App\FbaMonthlyInventoryHistoryReport;
use App\FbaManageInventory;
use App\FbaReceivedInventoryReport;
use App\FbaReservedInventoryReport;
use App\FbaInventoryAdjustmentsReport;
use App\FbaInventoryHealthReport;
use App\RestockInventoryReport;
use App\FbaStrandedInventoryReport;
use App\FbaBulkFixStrandedInventoryReport;
use App\AmazonSettlement;
use App\AmazonSettlementDetail;
use Carbon\Carbon;
use Exception;
class SaveMwsReportData {

    private $report_type;
    private $account;
    private $resources;
	private $requestTime;

    public function __construct($account, $report_type, $resources) {
        $this->account = $account;
        $this->report_type = $report_type;
        $this->resources = $resources;
		$this->requestTime = date('Y-m-d H:i:s');
    }

    public function save(){
		switch ($this->report_type){
			case '_GET_AFN_INVENTORY_DATA_BY_COUNTRY_':
				if($this->account->mws_marketplaceid=='A1F83G8C2ARO7P') self::saveEuInventory();
				self::saveEuInventoryNew();
				break;  
			case '_GET_FBA_MYI_ALL_INVENTORY_DATA_':
				if($this->account->mws_marketplaceid!='A1F83G8C2ARO7P') self::saveInventory();
				self::saveInventoryNew();
				break;
			case '_GET_AFN_INVENTORY_DATA_':
				self::saveAfnInventory();
				break;
			case '_GET_FBA_FULFILLMENT_CUSTOMER_RETURNS_DATA_':
				self::saveReturns();
				break;
			case '_GET_FBA_FULFILLMENT_INVENTORY_SUMMARY_DATA_':
				self::saveInventorySummary();
				break;
			case '_GET_FBA_INVENTORY_AGED_DATA_':
				self::saveInventoryAge();
				break;
			case '_GET_FBA_FULFILLMENT_CURRENT_INVENTORY_DATA_':
				self::saveDailyInventory();
				break;
			case '_GET_FBA_FULFILLMENT_MONTHLY_INVENTORY_DATA_':
				self::saveMonthlyInventory();
				break;
			case '_GET_FBA_FULFILLMENT_INVENTORY_RECEIPTS_DATA_':
				self::saveInventoryReceipts();
				break;
			case '_GET_RESERVED_INVENTORY_DATA_':
				self::saveInventoryReserved();
				break;
			case '_GET_FBA_FULFILLMENT_INVENTORY_ADJUSTMENTS_DATA_':
				self::saveInventoryAdjustments();
				break;
			case '_GET_FBA_FULFILLMENT_INVENTORY_HEALTH_DATA_':
				self::saveInventoryHealth();
				break;
			case '_GET_FBA_MYI_UNSUPPRESSED_INVENTORY_DATA_':
				self::saveInventoryUnsuppressed();
				break;
			case '_GET_RESTOCK_INVENTORY_RECOMMENDATIONS_REPORT_':
				self::saveInventoryRestock();
				break;
			case '_GET_FBA_FULFILLMENT_INBOUND_NONCOMPLIANCE_DATA_':
				self::saveInventoryInbound();
				break;
			case '_GET_STRANDED_INVENTORY_UI_DATA_':
				self::saveInventoryStranded();
				break;
			case '_GET_STRANDED_INVENTORY_LOADER_DATA_':
				self::saveInventoryBulkFixStranded();
				break;
			case '_GET_V2_SETTLEMENT_REPORT_DATA_FLAT_FILE_':
				self::saveSettlement();
				break;
			default:
				break;
		}
	}
	public function saveSettlement(){
		$account = $this->account;
		$data = $itemData = [];
		$currency = '';
		foreach ($this->resources as $info)
		{
			$settlement_id = trim(current($info));
			$settlement_start_date = Carbon::parse(next($info))->toDateTimeString();
			$settlement_end_date = Carbon::parse(next($info))->toDateTimeString();
			$deposit_date = Carbon::parse(next($info))->toDateTimeString();
			$total_amount = round(next($info),2);
			$mainCurrency = trim(next($info));
			if($mainCurrency) $currency = $mainCurrency;
			$transaction_type = trim(next($info));
			$order_id = trim(next($info));
			$merchant_order_id = trim(next($info));
			$adjustment_id = trim(next($info));
			$shipment_id = trim(next($info));
			$marketplace_name = trim(next($info));
			$shipment_fee_type = trim(next($info));
			$shipment_fee_amount = round(next($info),2);
			$order_fee_type = trim(next($info));
			$order_fee_amount = round(next($info),2);
			$fulfillment_id = trim(next($info));
			$posted_date = Carbon::parse(next($info))->toDateTimeString();
			$order_item_code = trim(next($info));
			$merchant_order_item_id = trim(next($info));
			$merchant_adjustment_item_id = trim(next($info));
			$sku = trim(next($info));
			$quantity_purchased = intval(next($info));
			$price_type = trim(next($info));
			$price_amount = round(next($info),2);
			$item_related_fee_type = trim(next($info));
			$item_related_fee_amount = round(next($info),2);
			$misc_fee_amount = round(next($info),2);
			$other_fee_amount = round(next($info),2);
			$other_fee_reason_description = trim(next($info));
			$promotion_id = trim(next($info));
			$promotion_type = trim(next($info));
			$promotion_amount = round(next($info),2);
			$direct_payment_type = trim(next($info));
			$direct_payment_amount = round(next($info),2);
			$other_amount = round(next($info),2);
			if(!$data){
				$data[] = array(
					'user_id' => $account->user_id,
					'seller_account_id' =>$account->id,
					'report_id' =>$account->report_id,
					'settlement_id' =>$settlement_id,
					'settlement_start_date' => $settlement_start_date,
					'settlement_end_date' => $settlement_end_date,
					'deposit_date'=>$deposit_date,
					'total_amount'=>$total_amount,
					'currency'=>$currency,
					'updated_at'=>$this->requestTime
				);
			}else{
				$itemData[] = array(
					'user_id' => $account->user_id,
					'seller_account_id' =>$account->id,
					'settlement_id' =>$settlement_id,
					'transaction_type'=>$transaction_type,
					'order_id'=>$order_id,
					'merchant_order_id'=>$merchant_order_id,
					'adjustment_id'=>$adjustment_id,
					'shipment_id'=>$shipment_id,
					'marketplace_name'=>$marketplace_name,
					'shipment_fee_type'=>$shipment_fee_type,
					'shipment_fee_amount'=>$shipment_fee_amount,
					'order_fee_type'=>$order_fee_type,
					'order_fee_amount'=>$order_fee_amount,
					'fulfillment_id'=>$fulfillment_id,
					'posted_date'=>$posted_date,
					'order_item_code'=>$order_item_code,
					'merchant_order_item_id'=>$merchant_order_item_id,
					'merchant_adjustment_item_id'=>$merchant_adjustment_item_id,
					'sku'=>$sku,
					'quantity_purchased'=>$quantity_purchased,
					'price_type'=>$price_type,
					'price_amount'=>$price_amount,
					'item_related_fee_type'=>$item_related_fee_type,
					'item_related_fee_amount'=>$item_related_fee_amount,
					'misc_fee_amount'=>$misc_fee_amount,
					'other_fee_amount'=>$other_fee_amount,
					'other_fee_reason_description'=>$other_fee_reason_description,
					'promotion_id'=>$promotion_id,
					'promotion_type'=>$promotion_type,
					'promotion_amount'=>$promotion_amount,
					'direct_payment_type'=>$direct_payment_type,
					'direct_payment_amount'=>$direct_payment_amount,
					'other_amount'=>$other_amount,
					'currency'=>$currency,
					'updated_at'=>$this->requestTime
				);
			}
		}
		if($itemData){
			AmazonSettlement::insert($data);
			$chunk_result = array_chunk($itemData, 50);
			foreach($chunk_result as $k=>$v){
				AmazonSettlementDetail::insert($v);
			}
			//AmazonSettlementDetail::insert($itemData);
		}
	}
	public function saveDailyInventory(){
		$account = $this->account;
		$skus_updata = $date_rand = [];
		foreach ($this->resources as $info)
		{
			
			$snapshot_date = date('Y-m-d',strtotime(trim(current($info))));
			$fnsku = next($info);
			$sku = next($info);
			$product_name = next($info);
			$quantity = intval(next($info));
			$fulfillment_center_id = next($info);
			$disposition = next($info);
			$country = next($info);

			$skus_updata[] = array(
				'user_id' => $account->user_id,
				'seller_account_id' =>$account->id,
				'snapshot_date' => $snapshot_date,
				'seller_sku' => $sku,
				'fnsku'=>$fnsku,
				'country'=>$country,
				'fulfillment_center_id'=>$fulfillment_center_id,
				'disposition'=>$disposition,
				'quantity'=>$quantity,
				'updated_at'=>$this->requestTime
			);
			if(!in_array($snapshot_date,$date_rand)) $date_rand[]=$snapshot_date;
		}
		if($skus_updata){
			FbaDailyInventoryHistoryReport::where('seller_account_id',$account->id)->whereIn('snapshot_date',$date_rand)->delete();
			$chunk_result = array_chunk($skus_updata, 50);
			foreach($chunk_result as $k=>$v){
				FbaDailyInventoryHistoryReport::insert($v);
			}
		}
	}
	public function saveMonthlyInventory(){
		$account = $this->account;
		$skus_updata = $date_rand = [];
		foreach ($this->resources as $info)
		{
			
			$month = trim(current($info));
			$month = substr($month,3,4).'-'.substr($month,0,2);
			$fnsku = next($info);
			$sku = next($info);
			$product_name = next($info);
			$average_quantity = round(next($info),2);
			$end_quantity = intval(next($info));
			$fulfillment_center_id = next($info);
			$disposition = next($info);
			$country = next($info);

			$skus_updata[] = array(
				'user_id' => $account->user_id,
				'seller_account_id' =>$account->id,
				'month' => $month,
				'seller_sku' => $sku,
				'fnsku'=>$fnsku,
				'country'=>$country,
				'fulfillment_center_id'=>$fulfillment_center_id,
				'disposition'=>$disposition,
				'average_quantity'=>$average_quantity,
				'end_quantity'=>$end_quantity,
				'updated_at'=>$this->requestTime
			);
			if(!in_array($month,$date_rand)) $date_rand[]=$month;
		}
		if($skus_updata){
			FbaMonthlyInventoryHistoryReport::where('seller_account_id',$account->id)->whereIn('month',$date_rand)->delete();
			$chunk_result = array_chunk($skus_updata, 50);
			foreach($chunk_result as $k=>$v){
				FbaMonthlyInventoryHistoryReport::insert($v);
			}
			
		}

	}
	public function saveInventoryReceipts(){
		$account = $this->account;
		$skus_updata = $date_rand = [];
		foreach ($this->resources as $info)
		{
			$received_date = date('Y-m-d',strtotime(trim(current($info))));
			$fnsku = next($info);
			$sku = next($info);
			$product_name = next($info);
			$quantity = intval(next($info));
			$fba_shipment_id = next($info);
			$fulfillment_center_id = next($info);
			$skus_updata[] = array(
				'user_id' => $account->user_id,
				'seller_account_id' =>$account->id,
				'received_date' => $received_date,
				'seller_sku' => $sku,
				'fnsku'=>$fnsku,
				'fba_shipment_id'=>$fba_shipment_id,
				'fulfillment_center_id'=>$fulfillment_center_id,
				'quantity'=>$quantity,
				'updated_at'=>$this->requestTime
			);
			if(!in_array($received_date,$date_rand)) $date_rand[]=$received_date;
		}
		if($skus_updata){
			FbaReceivedInventoryReport::where('seller_account_id',$account->id)->whereIn('received_date',$date_rand)->delete();
			$chunk_result = array_chunk($skus_updata, 50);
			foreach($chunk_result as $k=>$v){
				FbaReceivedInventoryReport::insert($v);
			}
		}
	}
	public function saveInventoryReserved(){
		$account = $this->account;
		$skus_updata=[];
		$clearData = array(
			'updated_at'=>$this->requestTime,
			'reserved_qty'=>0,
			'reserved_customerorders'=>0,
			'reserved_fc_transfers'=>0,
			'reserved_fc_processing'=>0
		);
		foreach ($this->resources as $info)
		{
			$sku = current($info);
			$fnsku = next($info);
			$asin = next($info);
			$product_name = next($info);
			$reserved_qty = intval(next($info));
			$reserved_customerorders = intval(next($info));
			$reserved_fc_transfers = intval(next($info));
			$reserved_fc_processing = intval(next($info));
			$skus_updata[] = array(
				'user_id' => $account->user_id,
				'seller_account_id' =>$account->id,
				'asin' => $asin,
				'seller_sku' => $sku,
				'fnsku'=>$fnsku,
				'reserved_qty'=>$reserved_qty,
				'reserved_customerorders'=>$reserved_customerorders,
				'reserved_fc_transfers'=>$reserved_fc_transfers,
				'reserved_fc_processing'=>$reserved_fc_processing,
				'updated_at'=>$this->requestTime
			);
		}
		FbaReservedInventoryReport::insertOnDuplicateWithDeadlockCatching($skus_updata,['reserved_qty','reserved_customerorders','reserved_fc_transfers','reserved_fc_processing']);
		FbaReservedInventoryReport::where('seller_account_id',$account->id)->where('updated_at','<',$this->requestTime)->update($clearData);
	}
	public function saveInventoryAdjustments(){
		$account = $this->account;
		$skus_updata = $date_rand = [];
		foreach ($this->resources as $info)
		{	
			$adjusted_date = date('Y-m-d',strtotime(trim(current($info))));
			$transaction_item_id = next($info);
			$fnsku = next($info);
			$sku = next($info);
			$product_name = next($info);
			$fulfillment_center_id = next($info);
			$quantity = intval(next($info));
			$reason = next($info);
			$disposition = next($info);
			$reconciled = intval(next($info));
			$unreconciled = intval(next($info));

			$skus_updata[] = array(
				'user_id' => $account->user_id,
				'seller_account_id' =>$account->id,
				'adjusted_date' => $adjusted_date,
				'transaction_item_id' => $transaction_item_id,
				'seller_sku' => $sku,
				'fnsku'=>$fnsku,
				'reason'=>$reason,
				'fulfillment_center_id'=>$fulfillment_center_id,
				'quantity'=>$quantity,
				'disposition'=>$disposition,
				'reconciled'=>$reconciled,
				'unreconciled'=>$unreconciled,
				'updated_at'=>$this->requestTime
			);
			if(!in_array($adjusted_date,$date_rand)) $date_rand[]=$adjusted_date;
		}
		if($skus_updata){
			FbaInventoryAdjustmentsReport::where('seller_account_id',$account->id)->whereIn('adjusted_date',$date_rand)->delete();
			$chunk_result = array_chunk($skus_updata, 50);
			foreach($chunk_result as $k=>$v){
				FbaInventoryAdjustmentsReport::insert($v);
			}
		}
	}
	public function saveInventoryHealth(){
		$account = $this->account;
		$skus_updata = $date_rand = [];
		foreach ($this->resources as $info)
		{	
			$snapshot_date = date('Y-m-d',strtotime(trim(current($info))));
			$sku = next($info);
			$fnsku = next($info);
			$asin = next($info);
			$product_name = next($info);
			$condition = next($info);
			$sales_rank = intval(next($info));
			$product_group = next($info);
			$total_quantity = intval(next($info));
			$sellable_quantity = intval(next($info));
			$unsellable_quantity = intval(next($info));
			$inv_age_0_to_90_days = intval(next($info));
			$inv_age_91_to_180_days = intval(next($info));
			$inv_age_181_to_270_days = intval(next($info));
			$inv_age_271_to_365_days = intval(next($info));
			$inv_age_365_plus_days = intval(next($info));
			$units_shipped_last_24_hrs = intval(next($info));
			$units_shipped_last_7_days = intval(next($info));
			$units_shipped_last_30_days = intval(next($info));
			$units_shipped_last_90_days = intval(next($info));
			$units_shipped_last_180_days = intval(next($info));
			$units_shipped_last_365_days = intval(next($info));
			$weeks_of_cover_t7 = round(next($info),2);
			$weeks_of_cover_t30 = round(next($info),2);
			$weeks_of_cover_t90 = round(next($info),2);
			$weeks_of_cover_t180 = round(next($info),2);
			$weeks_of_cover_t365 = round(next($info),2);
			$num_afn_new_sellers = intval(next($info));
			$num_afn_used_sellers = intval(next($info));
			$currency = next($info);
			$your_price = round(next($info),2);
			$sales_price = round(next($info),2);
			$lowest_afn_new_price = round(next($info),2);
			$lowest_afn_used_price = round(next($info),2);
			$lowest_mfn_new_price = round(next($info),2);
			$lowest_mfn_used_price = round(next($info),2);
			$qty_to_be_charged_ltsf_12_mo = intval(next($info));
			$qty_in_long_term_storage_program = intval(next($info));
			$qty_with_removals_in_progress = intval(next($info));
			$projected_ltsf_12_mo = round(next($info),2);
			$per_unit_volume = round(next($info),6);
			$is_hazmat = next($info);
			$in_bound_quantity = intval(next($info));
			$asin_limit = intval(next($info));
			$inbound_recommend_quantity = intval(next($info));
			$qty_to_be_charged_ltsf_6_mo = intval(next($info));
			$projected_ltsf_6_mo = round(next($info),2);

			$skus_updata[] = array(
				'user_id' => $account->user_id,
				'seller_account_id' =>$account->id,
				'snapshot_date'=>$snapshot_date,
				'seller_sku'=>$sku,
				'fnsku'=>$fnsku,
				'asin'=>$asin,
				'condition'=>$condition,
				'sales_rank'=>$sales_rank,
				'product_group'=>$product_group,
				'total_quantity'=>$total_quantity,
				'sellable_quantity'=>$sellable_quantity,
				'unsellable_quantity'=>$unsellable_quantity,
				'inv_age_0_to_90_days'=>$inv_age_0_to_90_days,
				'inv_age_91_to_180_days'=>$inv_age_91_to_180_days,
				'inv_age_181_to_270_days'=>$inv_age_181_to_270_days,
				'inv_age_271_to_365_days'=>$inv_age_271_to_365_days,
				'inv_age_365_plus_days'=>$inv_age_365_plus_days,
				'units_shipped_last_24_hrs'=>$units_shipped_last_24_hrs,
				'units_shipped_last_7_days'=>$units_shipped_last_7_days,
				'units_shipped_last_30_days'=>$units_shipped_last_30_days,
				'units_shipped_last_90_days'=>$units_shipped_last_90_days,
				'units_shipped_last_180_days'=>$units_shipped_last_180_days,
				'units_shipped_last_365_days'=>$units_shipped_last_365_days,
				'weeks_of_cover_t7'=>$weeks_of_cover_t7,
				'weeks_of_cover_t30'=>$weeks_of_cover_t30,
				'weeks_of_cover_t90'=>$weeks_of_cover_t90,
				'weeks_of_cover_t180'=>$weeks_of_cover_t180,
				'weeks_of_cover_t365'=>$weeks_of_cover_t365,
				'num_afn_new_sellers'=>$num_afn_new_sellers,
				'num_afn_used_sellers'=>$num_afn_used_sellers,
				'currency'=>$currency,
				'your_price'=>$your_price,
				'sales_price'=>$sales_price,
				'lowest_afn_new_price'=>$lowest_afn_new_price,
				'lowest_afn_used_price'=>$lowest_afn_used_price,
				'lowest_mfn_new_price'=>$lowest_mfn_new_price,
				'lowest_mfn_used_price'=>$lowest_mfn_used_price,
				'qty_to_be_charged_ltsf_12_mo'=>$qty_to_be_charged_ltsf_12_mo,
				'qty_in_long_term_storage_program'=>$qty_in_long_term_storage_program,
				'qty_with_removals_in_progress'=>$qty_with_removals_in_progress,
				'projected_ltsf_12_mo'=>$projected_ltsf_12_mo,
				'per_unit_volume'=>$per_unit_volume,
				'is_hazmat'=>$is_hazmat,
				'in_bound_quantity'=>$in_bound_quantity,
				'asin_limit'=>$asin_limit,
				'inbound_recommend_quantity'=>$inbound_recommend_quantity,
				'qty_to_be_charged_ltsf_6_mo'=>$qty_to_be_charged_ltsf_6_mo,
				'projected_ltsf_6_mo'=>$projected_ltsf_6_mo,
				'updated_at'=>$this->requestTime
			);
			if(!in_array($snapshot_date,$date_rand)) $date_rand[]=$snapshot_date;
		}
		if($skus_updata){
			FbaInventoryHealthReport::where('seller_account_id',$account->id)->whereIn('snapshot_date',$date_rand)->delete();
			$chunk_result = array_chunk($skus_updata, 50);
			foreach($chunk_result as $k=>$v){
				FbaInventoryHealthReport::insert($v);
			}
		}
	}
	
	public function saveInventoryRestock(){
		$account = $this->account;
		$skus_updata=[];
		$clearData = array(
			'updated_at'=>$this->requestTime,
			'supplier'=>NULL,
			'supplier_part_no'=>NULL,
			'currency_code'=>NULL,
			'fulfilled_by'=>NULL,
			'alert'=>NULL,
			'recommended_ship_date'=>NULL,
			'inventory_level_threshold_published_current_month'=>NULL,
			'current_month_very_low_inventory_threshold'=>NULL,
			'current_month_minimum_inventory_threshold'=>NULL,
			'current_month_maximum_inventory_threshold'=>NULL,
			'current_month_very_high_inventory_threshold'=>NULL,
			'inventory_level_threshold_published_next_month'=>NULL,
			'next_month_very_low_inventory_threshold'=>NULL,
			'next_month_minimum_inventory_threshold'=>NULL,
			'next_month_maximum_inventory_threshold'=>NULL,
			'next_month_very_high_inventory_threshold'=>NULL,
			'price'=>0,
			'sales_last_30_days'=>0,
			'units_sold_last_30_days'=>0,
			'total_units'=>0,
			'inbound'=>0,
			'available'=>0,
			'fc_transfer'=>0,
			'fc_processing'=>0,
			'customer_order'=>0,
			'unfulfillable'=>0,
			'days_of_supply'=>0,
			'recommended_replenishment_qty'=>0,
			'utilization'=>0,
			'maximum_shipment_quantity'=>0,
		);
		
		foreach ($this->resources as $info)
		{
			$country = current($info);
			$product_name = next($info);
			$fnsku = next($info);
			$sku = next($info);
			$asin = next($info);
			$condition = next($info);
			$supplier = next($info);
			$supplier_part_no = next($info);
			$currency_code = next($info);
			$price = round(next($info),2);
			$sales_last_30_days = intval(next($info));
			$units_sold_last_30_days = intval(next($info));
			$total_units = intval(next($info));
			$inbound = intval(next($info));
			$available = intval(next($info));
			$fc_transfer = intval(next($info));
			$fc_processing = intval(next($info));
			$customer_order = intval(next($info));
			$unfulfillable = intval(next($info));
			$fulfilled_by = next($info);
			$days_of_supply = intval(next($info));
			$alert = next($info);
			$recommended_replenishment_qty = intval(next($info));
			$recommended_ship_date = next($info);
			$inventory_level_threshold_published_current_month = next($info);
			$current_month_very_low_inventory_threshold = next($info);
			$current_month_minimum_inventory_threshold = next($info);
			$current_month_maximum_inventory_threshold = next($info);
			$current_month_very_high_inventory_threshold = next($info);
			$inventory_level_threshold_published_next_month = next($info);
			$next_month_very_low_inventory_threshold = next($info);
			$next_month_minimum_inventory_threshold = next($info);
			$next_month_maximum_inventory_threshold = next($info);
			$next_month_very_high_inventory_threshold = next($info);
			$utilization = intval(next($info));
			$maximum_shipment_quantity = intval(next($info));

			$skus_updata[] = array(
				'user_id' => $account->user_id,
				'seller_account_id' =>$account->id,
				'country' => $country,
				'asin' => $asin,
				'seller_sku' => $sku,
				'fnsku'=>$fnsku,
				'condition' => $condition,
				'supplier' => $supplier,
				'supplier_part_no'=>$supplier_part_no,
				'currency_code'=>$currency_code,
				'price'=>$price,
				'sales_last_30_days'=>$sales_last_30_days,
				'units_sold_last_30_days'=>$units_sold_last_30_days,
				'total_units'=>$total_units,
				'inbound'=>$inbound,
				'available'=>$available,
				'fc_transfer'=>$fc_transfer,
				'fc_processing'=>$fc_processing,
				'customer_order'=>$customer_order,
				'unfulfillable'=>$unfulfillable,
				'fulfilled_by'=>$fulfilled_by,
				'days_of_supply'=>$days_of_supply,
				'alert'=>$alert,
				'recommended_replenishment_qty'=>$recommended_replenishment_qty,
				'recommended_ship_date'=>$recommended_ship_date,
				'inventory_level_threshold_published_current_month'=>$inventory_level_threshold_published_current_month,
				'current_month_very_low_inventory_threshold'=>$current_month_very_low_inventory_threshold,
				'current_month_minimum_inventory_threshold'=>$current_month_minimum_inventory_threshold,
				'current_month_maximum_inventory_threshold'=>$current_month_maximum_inventory_threshold,
				'current_month_very_high_inventory_threshold'=>$current_month_very_high_inventory_threshold,
				'inventory_level_threshold_published_next_month'=>$inventory_level_threshold_published_next_month,
				'next_month_very_low_inventory_threshold'=>$next_month_very_low_inventory_threshold,
				'next_month_minimum_inventory_threshold'=>$next_month_minimum_inventory_threshold,
				'next_month_maximum_inventory_threshold'=>$next_month_maximum_inventory_threshold,
				'next_month_very_high_inventory_threshold'=>$next_month_very_high_inventory_threshold,
				'utilization'=>$utilization,
				'maximum_shipment_quantity'=>$maximum_shipment_quantity,
				'updated_at'=>$this->requestTime
			);
		}
		RestockInventoryReport::insertOnDuplicateWithDeadlockCatching($skus_updata,
			[
				'updated_at',
				'supplier',
				'supplier_part_no',
				'currency_code',
				'fulfilled_by',
				'alert',
				'recommended_ship_date',
				'inventory_level_threshold_published_current_month',
				'current_month_very_low_inventory_threshold',
				'current_month_minimum_inventory_threshold',
				'current_month_maximum_inventory_threshold',
				'current_month_very_high_inventory_threshold',
				'inventory_level_threshold_published_next_month',
				'next_month_very_low_inventory_threshold',
				'next_month_minimum_inventory_threshold',
				'next_month_maximum_inventory_threshold',
				'next_month_very_high_inventory_threshold',
				'price',
				'sales_last_30_days',
				'units_sold_last_30_days',
				'total_units',
				'inbound',
				'available',
				'fc_transfer',
				'fc_processing',
				'customer_order',
				'unfulfillable',
				'days_of_supply',
				'recommended_replenishment_qty',
				'utilization',
				'maximum_shipment_quantity',
			]
		);
		RestockInventoryReport::where('seller_account_id',$account->id)->where('updated_at','<',$this->requestTime)->update($clearData);
	}
	public function saveInventoryInbound(){
	}
	public function saveInventoryStranded(){
		$account = $this->account;
		$skus_updata = $date_rand = [];
		foreach ($this->resources as $info)
		{	
			$primary_action = current($info);
			$date_stranded = date('Y-m-d',strtotime(next($info)));
			$date_to_take_auto_removal = date('Y-m-d',strtotime(next($info)));
			$status_primary = next($info);
			$status_secondary = next($info);
			$error_message = next($info);
			$stranded_reason = next($info);
			$asin = next($info);
			$sku = next($info);
			$fnsku = next($info);
			$product_name = next($info);
			$condition = next($info);
			$fulfilled_by = next($info);
			$fulfillable_qty = intval(next($info));
			$your_price = round(next($info),2);
			$unfulfillable_qty = intval(next($info));
			$reserved_quantity = intval(next($info));
			$inbound_shipped_qty = intval(next($info));

			$skus_updata[] = array(
				'user_id' => $account->user_id,
				'seller_account_id' =>$account->id,
				'primary_action' => $primary_action,
				'date_stranded' => $date_stranded,
				'date_to_take_auto_removal' => $date_to_take_auto_removal,
				'status_primary' => $status_primary,
				'status_secondary' => $status_secondary,
				'error_message' => $error_message,
				'stranded_reason' => $stranded_reason,
				'asin'=>$asin,
				'seller_sku'=>$sku,
				'fnsku'=>$fnsku,
				'condition'=>$condition,
				'fulfilled_by'=>$fulfilled_by,
				'fulfillable_qty'=>$fulfillable_qty,
				'your_price'=>$your_price,
				'unfulfillable_qty'=>$unfulfillable_qty,
				'reserved_quantity'=>$reserved_quantity,
				'inbound_shipped_qty'=>$inbound_shipped_qty,
				'updated_at'=>$this->requestTime
			);
		}
		if($skus_updata){
			FbaStrandedInventoryReport::where('seller_account_id',$account->id)->delete();
			$chunk_result = array_chunk($skus_updata, 50);
			foreach($chunk_result as $k=>$v){
				FbaStrandedInventoryReport::insert($v);
			}
		}
	}
	public function saveInventoryBulkFixStranded(){
		$account = $this->account;
		$skus_updata = $date_rand = [];
		foreach ($this->resources as $info)
		{	
			$sku = current($info);
			$product_id = next($info);
			$product_id_type = next($info);
			$price = round(next($info),2);
			$minimum_seller_allowed_price = round(next($info),2);
			$maximum_seller_allowed_price = round(next($info),2);
			$item_condition = next($info);
			$quantity = intval(next($info));
			$add_delete = next($info);
			$will_ship_internationally = next($info);
			$expedited_shipping = next($info);
			$standard_plus = next($info);
			$item_note = next($info);
			$fulfillment_center_id = next($info);
			$product_tax_code = next($info);
			$leadtime_to_ship = next($info);
			$merchant_shipping_group_name = next($info);

			$skus_updata[] = array(
				'user_id' => $account->user_id,
				'seller_account_id' =>$account->id,
				'seller_sku'=>$sku,
				'product_id' => $product_id,
				'product_id_type' => $product_id_type,
				'price' => $price,
				'minimum_seller_allowed_price' => $minimum_seller_allowed_price,
				'maximum_seller_allowed_price' => $maximum_seller_allowed_price,
				'item_condition' => $item_condition,
				'quantity'=>$quantity,
				'add_delete'=>$add_delete,
				'will_ship_internationally'=>$will_ship_internationally,
				'expedited_shipping'=>$expedited_shipping,
				'standard_plus'=>$standard_plus,
				'item_note'=>$item_note,
				'fulfillment_center_id'=>$fulfillment_center_id,
				'product_tax_code'=>$product_tax_code,
				'leadtime_to_ship'=>$leadtime_to_ship,
				'merchant_shipping_group_name'=>$merchant_shipping_group_name,
				'updated_at'=>$this->requestTime
			);
		}
		if($skus_updata){
			FbaBulkFixStrandedInventoryReport::where('seller_account_id',$account->id)->delete();
			$chunk_result = array_chunk($skus_updata, 50);
			foreach($chunk_result as $k=>$v){
				FbaBulkFixStrandedInventoryReport::insert($v);
			}
		}
	}
	public function saveEuInventory(){
		$account = $this->account;
		$match_seller_account_id = SellerAccounts::where('mws_seller_id',$account->mws_seller_id)->pluck('id','mws_marketplaceid')->toArray();
		$skus_updata=$asins_data = $sellerAsins = [];
		$clearData = array(
			'updated_at'=>Carbon::now()->toDateTimeString(),
			'mfn'=>0,
			'mfn_sellable'=>0,
			'afn_reserved'=>0,
			'afn'=>0,
			'afn_sellable'=>0,
			'afn_unsellable'=>0,
			'afn_total'=>0,
			'afn_transfer'=>0
		);
		foreach ($this->resources as $info)
		{
			$sku = current($info);
			$fnsku = next($info);
			$asin = next($info);
			$title = '';
			$cond = next($info);
			$price = 0;
			$mfn = 0;
			$mfn_sellable = 0;
			$afn = 1;
			$afn_warehouse_qty = 0;
			$country_code = trim(next($info));
			$marketplace_id = array_get(siteToMarketplaceid(),$country_code,$account->mws_marketplaceid);
			$afn_sellable = intval(next($info));
			$afn_unsellable = 0;
			$afn_reserved = 0;
			$afn_total = $afn_sellable;
			$afn_transfer = 0;
			$skus_updata[] = array(
				'user_id' => $account->user_id,
				'seller_account_id' =>array_get($match_seller_account_id,$marketplace_id,$account->id),
				'asin' => $asin,
				'seller_sku' => $sku,
				'marketplaceid' => $marketplace_id,
				'fnsku'=>$fnsku,
				'mfn'=>$mfn,
				'mfn_sellable'=>$mfn_sellable,
				'afn'=>$afn,
				'afn_sellable'=>$afn_sellable,
				'afn_unsellable'=>$afn_unsellable,
				'afn_total'=>$afn_total,
				'afn_reserved'=>$afn_reserved,
				'afn_transfer'=>$afn_transfer,
				'updated_at'=>$this->requestTime
			);
			
			$asins_data[] = array(
				'asin' => $asin,
				'marketplaceid' => $marketplace_id
			);
			$sellerAsins[] = array(
				'asin' => $asin,
				'seller_account_id' => array_get($match_seller_account_id,$marketplace_id,$account->id)
			);
		}
		SellerSku::insertOnDuplicateWithDeadlockCatching($skus_updata,['mfn','mfn_sellable','afn','afn_sellable','afn_unsellable','afn_total','afn_transfer','afn_reserved','updated_at']);
		Asin::insertIgnore($asins_data);
		SellerSku::whereIn('seller_account_id',array_values($match_seller_account_id))->where('updated_at','<',$this->requestTime)->update($clearData);
		SellerAsin::insertIgnore($sellerAsins);
	}
	public function saveInventory(){
		$account = $this->account;
		$skus_updata=$asins_data = $sellerAsins = [];
		$clearData = array(
			'updated_at'=>Carbon::now()->toDateTimeString(),
			'mfn'=>0,
			'mfn_sellable'=>0,
			'afn'=>0,
			'afn_reserved'=>0,
			'afn_sellable'=>0,
			'afn_unsellable'=>0,
			'afn_total'=>0,
			'afn_transfer'=>0
		);
		$siteDatas = SellerAccounts::where('mws_seller_id',$account->mws_seller_id)->WhereNull('deleted_at')->get();
		foreach ($this->resources as $info)
		{
			$sku = current($info);
			$fnsku = next($info);
			$asin = next($info);
			$title = next($info);
			$cond = next($info);
			$price = round(next($info),2);
			$mfn = (next($info)=='Yes')?1:0;
			$mfn_sellable = intval(next($info));
			$afn = (next($info)=='Yes')?1:0;
			$afn_warehouse_qty = intval(next($info));
			$afn_sellable = intval(next($info));
			$afn_unsellable = intval(next($info));
			$afn_reserved = intval(next($info));
			$afn_total = intval(next($info));
			$afn_transfer = intval($afn_total-$afn_sellable-$afn_unsellable);
			$skus_updata[] = array(
				'user_id' => $account->user_id,
				'seller_account_id' =>$account->id,
				'asin' => $asin,
				'seller_sku' => $sku,
				'marketplaceid' => $account->mws_marketplaceid,
				'fnsku'=>$fnsku,
				'mfn'=>$mfn,
				'mfn_sellable'=>$mfn_sellable,
				'afn'=>$afn,
				'afn_sellable'=>$afn_sellable,
				'afn_unsellable'=>$afn_unsellable,
				'afn_total'=>$afn_total,
				'afn_reserved'=>$afn_reserved,
				'afn_transfer'=>$afn_transfer,
				'updated_at'=>$this->requestTime
			);
			foreach(getAreaMarketplaceids() as $k=>$v){
				if(in_array($account->mws_marketplaceid ,$v)){
					foreach($v as $vk){
						$asins_data[] = array(
							'asin' => $asin,
							'marketplaceid' => $vk
						);
					}	
				}
			}
			foreach($siteDatas as $siteData){
				$sellerAsins[] = array(
					'asin' => $asin,
					'seller_account_id' => $siteData->id,
				);
			}
		}
		SellerSku::insertOnDuplicateWithDeadlockCatching($skus_updata,['mfn','mfn_sellable','afn','afn_sellable','afn_unsellable','afn_total','afn_transfer','afn_reserved','updated_at']);
		Asin::insertIgnore($asins_data);
		SellerSku::where('seller_account_id',$account->id)->where('updated_at','<',$this->requestTime)->update($clearData);
		SellerAsin::insertIgnore($sellerAsins);
	}


	public function saveInventoryNew(){
		$account = $this->account;
		$skus_updata=[];
		$clearData = array(
			'updated_at'=>$this->requestTime,
			'mfn_listing_exists'=>0,
			'mfn_fulfillable_quantity'=>0,
			'afn_listing_exists'=>0,
			'afn_warehouse_quantity'=>0,
			'afn_fulfillable_quantity'=>0,
			'afn_unsellable_quantity'=>0,
			'afn_reserved_quantity'=>0,
			'afn_total_quantity'=>0,
			'afn_inbound_working_quantity'=>0,
			'afn_inbound_shipped_quantity'=>0,
			'afn_inbound_receiving_quantity'=>0,
			'afn_researching_quantity'=>0,
			'afn_reserved_future_supply'=>0,
			'afn_future_supply_buyable'=>0,
		);
		
		foreach ($this->resources as $info)
		{
			$sku = current($info);
			$fnsku = next($info);
			$asin = next($info);
			$title = next($info);
			$condition = next($info);
			$price = round(next($info),2);
			$mfn_listing_exists = (next($info)=='Yes')?1:0;
			$mfn_fulfillable_quantity = intval(next($info));
			$afn_listing_exists = (next($info)=='Yes')?1:0;
			$afn_warehouse_quantity = intval(next($info));
			$afn_fulfillable_quantity = intval(next($info));
			$afn_unsellable_quantity = intval(next($info));
			$afn_reserved_quantity = intval(next($info));
			$afn_total_quantity = intval(next($info));
			$per_unit_volume = round(next($info),2);
			$afn_inbound_working_quantity = intval(next($info));
			$afn_inbound_shipped_quantity = intval(next($info));
			$afn_inbound_receiving_quantity = intval(next($info));
			$afn_researching_quantity = intval(next($info));
			$afn_reserved_future_supply = intval(next($info));
			$afn_future_supply_buyable = intval(next($info));

			$skus_updata[] = array(
				'user_id' => $account->user_id,
				'seller_account_id' =>$account->id,
				'asin' => $asin,
				'seller_sku' => $sku,
				'fnsku'=>$fnsku,
				'condition' => $condition,
				'mfn_listing_exists'=>$mfn_listing_exists,
				'mfn_fulfillable_quantity'=>$mfn_fulfillable_quantity,
				'afn_listing_exists'=>$afn_listing_exists,
				'afn_warehouse_quantity'=>$afn_warehouse_quantity,
				'afn_fulfillable_quantity'=>$afn_fulfillable_quantity,
				'afn_unsellable_quantity'=>$afn_unsellable_quantity,
				'afn_reserved_quantity'=>$afn_reserved_quantity,
				'afn_total_quantity'=>$afn_total_quantity,
				'per_unit_volume'=>$per_unit_volume,
				'afn_inbound_working_quantity'=>$afn_inbound_working_quantity,
				'afn_inbound_shipped_quantity'=>$afn_inbound_shipped_quantity,
				'afn_inbound_receiving_quantity'=>$afn_inbound_receiving_quantity,
				'afn_researching_quantity'=>$afn_researching_quantity,
				'afn_reserved_future_supply'=>$afn_reserved_future_supply,
				'afn_future_supply_buyable'=>$afn_future_supply_buyable,
				'updated_at'=>$this->requestTime
			);
		}
		FbaManageInventoryArchived::insertOnDuplicateWithDeadlockCatching($skus_updata,
			[
				'mfn_listing_exists',
				'mfn_fulfillable_quantity',
				'afn_listing_exists',
				'afn_warehouse_quantity',
				'afn_fulfillable_quantity',
				'afn_unsellable_quantity',
				'afn_reserved_quantity',
				'afn_total_quantity',
				'afn_inbound_working_quantity',
				'afn_inbound_shipped_quantity',
				'afn_inbound_receiving_quantity',
				'afn_researching_quantity',
				'afn_reserved_future_supply',
				'afn_future_supply_buyable',
				'updated_at'
			]
		);
		FbaManageInventoryArchived::where('seller_account_id',$account->id)->where('updated_at','<',$this->requestTime)->update($clearData);
	}

	public function saveEuInventoryNew(){
		$account = $this->account;
		$match_seller_account_id = SellerAccounts::where('mws_seller_id',$account->mws_seller_id)->pluck('id','mws_marketplaceid')->toArray();
		$skus_updata=[];
		$clearData = array(
			'updated_at'=>$this->requestTime,
			'quantity_for_local_fulfillment'=>0
		);
		foreach ($this->resources as $info)
		{
			$sku = current($info);
			$fnsku = next($info);
			$asin = next($info);
			$condition_type = next($info);
			$country_code = trim(next($info));
			$marketplace_id = array_get(siteToMarketplaceid(),$country_code,$account->mws_marketplaceid);
			$quantity_for_local_fulfillment = intval(next($info));
			$skus_updata[] = array(
				'user_id' => $account->user_id,
				'seller_account_id' =>array_get($match_seller_account_id,$marketplace_id,$account->id),
				'asin' => $asin,
				'seller_sku' => $sku,
				'marketplace_id' => $marketplace_id,
				'fnsku'=>$fnsku,
				'country_code'=>$country_code,
				'condition_type'=>$condition_type,
				'quantity_for_local_fulfillment'=>$quantity_for_local_fulfillment,
				'updated_at'=>$this->requestTime
			);
		}
		FbaMultiCountryInventoryReport::insertOnDuplicateWithDeadlockCatching($skus_updata,['quantity_for_local_fulfillment','updated_at']);
		FbaMultiCountryInventoryReport::whereIn('seller_account_id',array_values($match_seller_account_id))->where('updated_at','<',$this->requestTime)->update($clearData);
	}

	public function saveAfnInventory(){
		$account = $this->account;
		$skus_updata= [];
		$clearData = array(
			'updated_at'=>$this->requestTime,
			'quantity_available'=>0,
		);
		
		foreach ($this->resources as $info)
		{
			$sku = current($info);
			$fnsku = next($info);
			$asin = next($info);
			$condition_type = next($info);
			$warehouse_condition_code = next($info);
			$quantity_available = intval(next($info));
			$skus_updata[] = array(
				'user_id' => $account->user_id,
				'seller_account_id' =>$account->id,
				'asin' => $asin,
				'seller_sku' => $sku,
				'fnsku'=>$fnsku,
				'condition_type'=>$condition_type,
				'warehouse_condition_code'=>$warehouse_condition_code,
				'quantity_available'=>$quantity_available,
				'updated_at'=>$this->requestTime
			);
		}
		FbaAmazonFulfilledInventoryReport::insertOnDuplicateWithDeadlockCatching($skus_updata,['quantity_available','updated_at']);
		FbaAmazonFulfilledInventoryReport::where('seller_account_id',$account->id)->where('updated_at','<',$this->requestTime)->update($clearData);
	}

	public function saveInventorySummary(){
		$account = $this->account;
		$skus_updata = $date_rand = [];
		foreach ($this->resources as $info)
		{
			
			$snapshot_date = date('Y-m-d',strtotime(trim(current($info))));
			$transaction_type = next($info);
			$fnsku = next($info);
			$sku = next($info);
			$product_name = next($info);
			$fulfillment_center_id = next($info);
			$quantity = intval(next($info));
			$disposition = next($info);
			$skus_updata[] = array(
				'user_id' => $account->user_id,
				'seller_account_id' =>$account->id,
				'snapshot_date' => $snapshot_date,
				'seller_sku' => $sku,
				'fnsku'=>$fnsku,
				'transaction_type'=>$transaction_type,
				'fulfillment_center_id'=>$fulfillment_center_id,
				'disposition'=>$disposition,
				'quantity'=>$quantity,
				'updated_at'=>$this->requestTime
			);
			if(!in_array($snapshot_date,$date_rand)) $date_rand[]=$snapshot_date;
		}
		if($skus_updata){
			FbaInventoryEventDetailReport::where('seller_account_id',$account->id)->whereIn('snapshot_date',$date_rand)->delete();
			$chunk_result = array_chunk($skus_updata, 50);
			foreach($chunk_result as $k=>$v){
				FbaInventoryEventDetailReport::insert($v);
			}
		}
	}

	public function saveReturns(){
		$account = $this->account;
		$count_arr = $updata = array();
		foreach ($this->resources as $info)
		{
			$return_date = date('Y-m-d H:i:s',strtotime(trim(current($info))));
			$amazon_order_id = trim(next($info));
			$seller_sku = trim(next($info));
			$asin = trim(next($info));
			$fnsku = trim(next($info));
			$title = trim(next($info));
			$quantity = intval(trim(next($info)));
			$fulfillment_center_id = trim(next($info));
			$detailed_disposition = trim(next($info));
			$reason = trim(next($info));
			if($account->mws_marketplaceid=='A1VC38T7YXB528'){ 
				$status = NULL;
			}else{
				$status = trim(next($info));
			}
			$license_plate_number = trim(next($info));
			$customer_comments = trim(next($info));
			if(array_get($count_arr,$amazon_order_id.$return_date)){
				$count_arr[$amazon_order_id.$return_date]++;
			}else{
				$count_arr[$amazon_order_id.$return_date]=1;
			}
			$line_num = $count_arr[$amazon_order_id.$return_date];
			$updata[] = array(
				'user_id' => $account->user_id,
				'seller_account_id' =>$account->id,
				'return_date' => $return_date,
				'amazon_order_id' => $amazon_order_id,
				'line_num'=>$line_num,
				'seller_sku'=>$seller_sku,
				'asin'=>$asin,
				'fnsku'=>$fnsku,
				'title'=>$title,
				'quantity'=>$quantity,
				'fulfillment_center_id'=>$fulfillment_center_id,
				'detailed_disposition'=>$detailed_disposition,
				'reason'=>$reason,
				'status'=>$status,
				'license_plate_number'=>$license_plate_number,
				'customer_comments'=>$customer_comments,
				'updated_at'=>$this->requestTime
			);
		}
		AmazonReturn::insertIgnore($updata);
	}

	public function saveInventoryAge(){
		$account = $this->account;
		$skus_updata = array();
		foreach ($this->resources as $info)
		{
			$snapshot_date = date('Y-m-d',strtotime(trim(current($info))));
			$seller_sku = trim(next($info));
			$fnsku = trim(next($info));
			$asin = trim(next($info));
			$product_name = trim(next($info));
			$condition = trim(next($info));
			$avaliable_quantity = intval(trim(next($info)));
			$qty_with_removals_in_progress = intval(trim(next($info)));
			$inv_age_0_to_90_days = intval(trim(next($info)));
			$inv_age_91_to_180_days = intval(trim(next($info)));
			$inv_age_181_to_270_days = intval(trim(next($info)));
			$inv_age_271_to_365_days = intval(trim(next($info)));
			$inv_age_365_plus_days = intval(trim(next($info)));
			$currency = trim(next($info));
			$qty_to_be_charged_ltsf_6_mo = intval(trim(next($info)));
			$projected_ltsf_6_mo = round(trim(next($info)),2);
			$qty_to_be_charged_ltsf_12_mo = intval(trim(next($info)));
			$projected_ltsf_12_mo = round(trim(next($info)),2);
			$units_shipped_last_7_days = intval(trim(next($info)));
			$units_shipped_last_30_days = intval(trim(next($info)));
			$units_shipped_last_60_days = intval(trim(next($info)));
			$units_shipped_last_90_days = intval(trim(next($info)));
			$alert  = trim(next($info));
			$your_price = round(trim(next($info)),2);
			$sales_price = round(trim(next($info)),2);
			$lowest_price_new = round(trim(next($info)),2);
			$lowest_price_used = round(trim(next($info)),2);
			$recommended_action  = trim(next($info));
			$healthy_inventory_level = trim(next($info));
			$recommended_sales_price = round(trim(next($info)),2);
			$recommended_sale_duration_days = intval(trim(next($info)));
			$recommended_removal_quantity = intval(trim(next($info)));
			$estimated_cost_savings_of_recommended_actions = trim(next($info));
			$sell_through = round(trim(next($info)),17);
			$item_volume = round(trim(next($info)),6);
			$volume_units = trim(next($info));
			$storage_type = trim(next($info));
			
			$skus_updata[] = array(
				'user_id' => $account->user_id,
				'seller_account_id' =>$account->id,
				'snapshot_date'=>$snapshot_date,
				'seller_sku'=>$seller_sku,
				'fnsku'=>$fnsku,
				'asin'=>$asin,
				'condition'=>$condition,
				'avaliable_quantity'=>$avaliable_quantity,
				'qty_with_removals_in_progress'=>$qty_with_removals_in_progress,
				'inv_age_0_to_90_days'=>$inv_age_0_to_90_days,
				'inv_age_91_to_180_days'=>$inv_age_91_to_180_days,
				'inv_age_181_to_270_days'=>$inv_age_181_to_270_days,
				'inv_age_271_to_365_days'=>$inv_age_271_to_365_days,
				'inv_age_365_plus_days'=>$inv_age_365_plus_days,
				'currency'=>$currency,
				'qty_to_be_charged_ltsf_6_mo'=>$qty_to_be_charged_ltsf_6_mo,
				'projected_ltsf_6_mo'=>$projected_ltsf_6_mo,
				'qty_to_be_charged_ltsf_12_mo'=>$qty_to_be_charged_ltsf_12_mo,
				'projected_ltsf_12_mo'=>$projected_ltsf_12_mo,
				'units_shipped_last_7_days'=>$units_shipped_last_7_days,
				'units_shipped_last_30_days'=>$units_shipped_last_30_days,
				'units_shipped_last_60_days'=>$units_shipped_last_60_days,
				'units_shipped_last_90_days'=>$units_shipped_last_90_days,
				'alert'=>$alert,
				'your_price'=>$your_price,
				'sales_price'=>$sales_price,
				'lowest_price_new'=>$lowest_price_new,
				'lowest_price_used'=>$lowest_price_used,
				'recommended_action'=>$recommended_action,
				'healthy_inventory_level'=>$healthy_inventory_level,
				'recommended_sales_price'=>$recommended_sales_price,
				'recommended_sale_duration_days'=>$recommended_sale_duration_days,
				'recommended_removal_quantity'=>$recommended_removal_quantity,
				'estimated_cost_savings_of_recommended_actions'=>$estimated_cost_savings_of_recommended_actions,
				'sell_through'=>$sell_through,
				'item_volume'=>$item_volume,
				'volume_units'=>$volume_units,
				'storage_type'=>$storage_type,
				'updated_at'=>$this->requestTime
			);
		}
		FbaInventoryAgeReport::insertIgnore($skus_updata);
	}

	public function saveInventoryUnsuppressed(){
		$account = $this->account;
		$skus_updata=[];
		$clearData = array(
			'updated_at'=>$this->requestTime,
			'mfn_listing_exists'=>0,
			'mfn_fulfillable_quantity'=>0,
			'afn_listing_exists'=>0,
			'afn_warehouse_quantity'=>0,
			'afn_fulfillable_quantity'=>0,
			'afn_unsellable_quantity'=>0,
			'afn_reserved_quantity'=>0,
			'afn_total_quantity'=>0,
			'afn_inbound_working_quantity'=>0,
			'afn_inbound_shipped_quantity'=>0,
			'afn_inbound_receiving_quantity'=>0,
			'afn_researching_quantity'=>0,
			'afn_reserved_future_supply'=>0,
			'afn_future_supply_buyable'=>0,
		);
		
		foreach ($this->resources as $info)
		{
			$sku = current($info);
			$fnsku = next($info);
			$asin = next($info);
			$title = next($info);
			$condition = next($info);
			$price = round(next($info),2);
			$mfn_listing_exists = (next($info)=='Yes')?1:0;
			$mfn_fulfillable_quantity = intval(next($info));
			$afn_listing_exists = (next($info)=='Yes')?1:0;
			$afn_warehouse_quantity = intval(next($info));
			$afn_fulfillable_quantity = intval(next($info));
			$afn_unsellable_quantity = intval(next($info));
			$afn_reserved_quantity = intval(next($info));
			$afn_total_quantity = intval(next($info));
			$per_unit_volume = round(next($info),2);
			$afn_inbound_working_quantity = intval(next($info));
			$afn_inbound_shipped_quantity = intval(next($info));
			$afn_inbound_receiving_quantity = intval(next($info));
			$afn_researching_quantity = intval(next($info));
			$afn_reserved_future_supply = intval(next($info));
			$afn_future_supply_buyable = intval(next($info));

			$skus_updata[] = array(
				'user_id' => $account->user_id,
				'seller_account_id' =>$account->id,
				'asin' => $asin,
				'seller_sku' => $sku,
				'fnsku'=>$fnsku,
				'condition' => $condition,
				'mfn_listing_exists'=>$mfn_listing_exists,
				'mfn_fulfillable_quantity'=>$mfn_fulfillable_quantity,
				'afn_listing_exists'=>$afn_listing_exists,
				'afn_warehouse_quantity'=>$afn_warehouse_quantity,
				'afn_fulfillable_quantity'=>$afn_fulfillable_quantity,
				'afn_unsellable_quantity'=>$afn_unsellable_quantity,
				'afn_reserved_quantity'=>$afn_reserved_quantity,
				'afn_total_quantity'=>$afn_total_quantity,
				'per_unit_volume'=>$per_unit_volume,
				'afn_inbound_working_quantity'=>$afn_inbound_working_quantity,
				'afn_inbound_shipped_quantity'=>$afn_inbound_shipped_quantity,
				'afn_inbound_receiving_quantity'=>$afn_inbound_receiving_quantity,
				'afn_researching_quantity'=>$afn_researching_quantity,
				'afn_reserved_future_supply'=>$afn_reserved_future_supply,
				'afn_future_supply_buyable'=>$afn_future_supply_buyable,
				'updated_at'=>$this->requestTime
			);
		}
		FbaManageInventory::insertOnDuplicateWithDeadlockCatching($skus_updata,
			[
				'mfn_listing_exists',
				'mfn_fulfillable_quantity',
				'afn_listing_exists',
				'afn_warehouse_quantity',
				'afn_fulfillable_quantity',
				'afn_unsellable_quantity',
				'afn_reserved_quantity',
				'afn_total_quantity',
				'afn_inbound_working_quantity',
				'afn_inbound_shipped_quantity',
				'afn_inbound_receiving_quantity',
				'afn_researching_quantity',
				'afn_reserved_future_supply',
				'afn_future_supply_buyable',
				'updated_at'
			]
		);
		FbaManageInventory::where('seller_account_id',$account->id)->where('updated_at','<',$this->requestTime)->update($clearData);
	}
}
