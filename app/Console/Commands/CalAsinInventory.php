<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Asin;
use App\SellerSku;
use App\SapAsinMatchSku;
use App\AsinMatchRelation;
use App\SapSkuSite;
class CalAsinInventory extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cal:asinInventory';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
		$requestTime=date('Y-m-d H:i:s');
        $asinStocks = SellerSku::selectRaw('asin,marketplaceid,sum(afn_sellable) as afn_sellable,sum(afn_reserved) as afn_reserved')->groupBy(['asin','marketplaceid'])->get()->toArray();
		$updata=[];
		foreach($asinStocks as $as){
			$sku = SapAsinMatchSku::where('asin',array_get($as,'asin',''))->where('marketplace_id',array_get($as,'marketplaceid',''))->where('actived',1)->value('sku');
			if(!$sku) $sku = AsinMatchRelation::where('asin',array_get($as,'asin',''))->where('marketplace_id',array_get($as,'marketplaceid',''))->value('sku');
			$stock_where_array = array_get(getMarketplaceCode(),array_get($as,'marketplaceid','').'.fbm_factory_warehouse',[]);
			$stock_where = [];
			foreach($stock_where_array as $kfw => $fw){
				$stock_where[]="(sap_factory_code='".$fw['sap_factory_code']."' and sap_warehouse_code='".$fw['sap_warehouse_code']."')";
			}
			if($stock_where){
				$mfn_sellable = SapSkuSite::where('sku',$sku)->where('marketplace_id',array_get($as,'marketplaceid',''))->whereRaw('('.implode(' or ',$stock_where).')')->sum('quantity');
			}else{
				$mfn_sellable = 0;
			}
			
			$updata[] = array(
				'asin'=>trim(array_get($as,'asin','')),
				'sku'=>$sku,
				'marketplaceid' =>trim(array_get($as,'marketplaceid','')),
				'afn_sellable' => intval(array_get($as,'afn_sellable',0)),
				'afn_reserved' => intval(array_get($as,'afn_reserved',0)),
				'mfn_sellable' => intval($mfn_sellable),
				'stock_updated_at'=>$requestTime
			);
		}
		Asin::insertOnDuplicateWithDeadlockCatching($updata,['sku','afn_sellable','afn_reserved','mfn_sellable','stock_updated_at']);
		Asin::where('stock_updated_at','<',$requestTime)->update(['afn_sellable'=>0,'afn_reserved'=>0,'mfn_sellable'=>0]);  
    }
}
