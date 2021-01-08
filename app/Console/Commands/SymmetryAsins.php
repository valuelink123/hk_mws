<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\DailyStatistic;
use App\SymmetryAsin;
use App\SapAsinMatchSku;
use App\Classes\AsinSym;
class SymmetryAsins extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sym:asins';

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

        $date_to = date('Y-m-d',strtotime("last saturday"));
        $asins=[];
        for($i=4;$i>=0;$i--){ 
            $date_from = date('Y-m-d',strtotime($date_to.' -6 days'));
            $week_asins = DailyStatistic::selectRaw('asin, marketplace_id, sum(quantity_shipped) as quantity_shipped')->where('date','>=',$date_from)->where('date','<=',$date_to)->where('quantity_shipped','>',0)->groupBy(['asin','marketplace_id'])->get()->toArray();
            foreach($week_asins as $k=>$v){
                $asins[$v['asin'].'_'.$v['marketplace_id']][$i] = $v['quantity_shipped'];
            }
            $date_to = date('Y-m-d',strtotime($date_to.' -7 days'));
        }

        foreach($asins as $k=>$v){
            for($i=0;$i<=4;$i++){
                if(!(array_get($v,$i)>0)) $asins[$k][$i]=0;
            }
            ksort($asins[$k]);
			try{
				$asin_marketplaceid = explode('_',$k);
				$obj = new AsinSym($asin_marketplaceid[0],$asin_marketplaceid[1],$asins[$k]);
				$asinSym = $obj->symmetry(52);
				if($asinSym){
					foreach($asinSym as $ak=>$av){
						SymmetryAsin::updateOrCreate(
							[
								'asin'=>array_get($av,'asin',''),
								'marketplace_id' => array_get($av,'marketplace_id',''),
								'date' => array_get($av,'date','')
							],
							[
								'sku' => array_get($av,'sku',''),
								'sku_group' => array_get($av,'sku_group',''),
								'quantity' => intval(array_get($av,'quantity',0)),
								'updated_at'=>date('Y-m-d H:i:s')
							]
						);
					}
				}
				unset($obj);
			} catch (\Exception $ex) {
				print_r($k.' '.$ex->getMessage());
			}
        }

        
    }
}
