<?php
namespace App\Classes;
use App\SapAsinMatchSku;
use App\AsinMatchRelation;
use App\KeywordsTrend;
class AsinSym {

    private $asin;
    private $marketplace_id;
    private $data;

    public function __construct($asin, $marketplace_id, $data=[0,0,0,0,0]) {
        $this->asin = $asin;
        $this->marketplace_id = $marketplace_id;
        $this->data = $data;
    }

    public function symmetry($weeks=52) {
		$split_days = array(
			'0'=>1,
			'1'=>1.15,
			'2'=>1.25,
			'3'=>1.16,
			'4'=>1.08,
			'5'=>1.07,
			'6'=>0.92
		);
        $asin_info = SapAsinMatchSku::where('asin',$this->asin)->where('marketplace_id',$this->marketplace_id)->where('actived',1)->whereNotNull('sku')->whereNotNull('sku_group')->first();
		if(empty($asin_info)){
			$asin_info = AsinMatchRelation::where('asin',$this->asin)->where('marketplace_id',$this->marketplace_id)->whereNotNull('sku')->whereNotNull('sku_group')->first();
		}
        if(empty($asin_info)) return [];
        $sku = $asin_info->sku;
        $sku_group = $asin_info->sku_group;

        $country_code = array_get(getSiteCountryCode(),$this->marketplace_id);
        if(!$country_code) return [];

        $sku_group_symmetry = KeywordsTrend::where('sku_group',$sku_group)->where('country_code',$country_code)->first();
        if(empty($sku_group_symmetry)) return [];
        $u=unserialize($sku_group_symmetry->u);
        $season=unserialize($sku_group_symmetry->season);
        $a = $sku_group_symmetry->a;
        $b = $sku_group_symmetry->b;
        $symmetry=unserialize($sku_group_symmetry->symmetry);
        $week_s = $week_symmetry = [];
        $i=151;
        foreach($this->data as $k=>$v){
            $week_s[$i]=round($v/$symmetry[$i],4);
            $i++;
        }
        $last_week_diff = round($this->data[4] - (array_avg(array_slice($week_s,0,4,true))*($b+$a*155)*array_get($season,'51')),2);
        $end = $weeks+$i;
        $date_from = date('Y-m-d',strtotime('this week')-86400);
		if($last_week_diff<0) $last_week_diff = 0; 
        while($i<=$end){
            $value = round(array_avg(array_slice($week_s,-4,4,true))*($b+$a*$i)*array_get($season,$i%52)+ $last_week_diff,2);

            $week_s[$i]=round($value/(($b+$a*$i)*array_get($season,$i%52)),4);

            $last_week_diff = 0; 
			
			for($x=0;$x<=6;$x++){
				$day_value = round($value/array_sum($split_days)*array_get($split_days,$x));
				$week_symmetry[] = array(
					'asin'=>$this->asin,
					'marketplace_id'=>$this->marketplace_id,
					'date'=>$date_from,
					'sku'=>$sku,
					'sku_group'=>$sku_group,
					'quantity'=>$day_value
				);
				$date_from = date('Y-m-d',strtotime($date_from)+86400);
			}
            $i++;
        }
        return $week_symmetry;
    }
}
