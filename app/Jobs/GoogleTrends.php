<?php

namespace App\Jobs;
use Illuminate\Bus\Queueable;
//use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Google\GTrends;
use App\KeywordsTrend;
use Carbon\Carbon;

class GoogleTrends implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /**
     * Create a new job instance.
     *
     * @return void
     */
	protected $keyword;
	
    public function __construct($keyword)
    {
		$this->keyword = $keyword;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $currentBlackFri = date('Y-m-d',strtotime(date('Y')."-10-31 next thursday")+22*86400);
        $dateTo = date('Y-m-d',strtotime("last saturday"));
        if($dateTo>=$currentBlackFri){
            $blackFriArr=[date('Y-m-d',strtotime((date('Y')-2)."-10-31 next thursday")+22*86400),date('Y-m-d',strtotime((date('Y')-1)."-10-31 next thursday")+22*86400),$currentBlackFri];
        }else{
            $blackFriArr=[date('Y-m-d',strtotime((date('Y')-3)."-10-31 next thursday")+22*86400),date('Y-m-d',strtotime((date('Y')-2)."-10-31 next thursday")+22*86400),date('Y-m-d',strtotime((date('Y')-1)."-10-31 next thursday")+22*86400)];
        }

        $dateFrom = date('Y-m-d',strtotime(date('Y-m-d',strtotime($dateTo ."-155 weeks"))." last sunday"));
        if($dateFrom>$blackFriArr[0]){
            $dateFrom = date('Y-m-d',strtotime($blackFriArr[0]." last sunday"));
            $dateTo = date('Y-m-d',strtotime(date('Y-m-d',strtotime($dateFrom ."+155 weeks"))." next saturday"));
        }

        $options = [
            'hl'  => ($this->keyword->language??'en-US'),
            'tz'  => -480,
            'geo' => ($this->keyword->country_code??'US'),
        ];
        $gt = new GTrends($options);
        $data = [];
        try{
            $result = $gt->interestOverTime($this->keyword->keyword,intval($this->keyword->category),$dateFrom.' '.$dateTo);
            foreach($result as $k=>$v){
                $data[$k] = [
                                'date_from'=>date('Y-m-d',intval($v['time'])),
                                'date_to'=>date('Y-m-d',intval($v['time'])+86400*6),
                                'value'=>intval(array_get($v,'value.0')),
                                'isBF'=>(in_array(date('Y-m-d',$v['time']+86400*5),$blackFriArr)?1:0)
                            ];     
            } 

            if($this->keyword->sku_group !='AMAZON'){
                $base_keywords_amazon = unserialize(KeywordsTrend::where('sku_group','AMAZON')->where('country_code',$this->keyword->country_code)->value('data'));
                $new_data = [];
                $bfk = [];
                foreach($base_keywords_amazon as $k=>$v){
                    $new_data[$k]=(array_get($data,$k.'.value')>0)?(array_get($data,$k.'.value')*$v['value']):$v['value'];
                    if($v['isBF']==1) $bfk[] = $k;
                }

                $new_data_chunk = array_chunk($new_data,52);
                if(($bfk[0]+52)!=$bfk[1]){
                    $diff_w = $bfk[0]+52-$bfk[1];
                    if($diff_w>0){
                        for($tmpi=1;$tmpi<=abs($diff_w);$tmpi++){
                            array_push($new_data_chunk[0],'0');
                        }
                        $new_data_chunk[0] = array_slice($new_data_chunk[0],-52,52);   
                    }else{
                        for($tmpi=1;$tmpi<=abs($diff_w);$tmpi++){
                            array_unshift($new_data_chunk[0],'0');
                        }
                        $new_data_chunk[0] = array_slice($new_data_chunk[0],0,52);
                    }

                }
                if(($bfk[2]-52)!=$bfk[1]){
                    $diff_w = $bfk[2]-52-$bfk[1];
                    if($diff_w>0){
                        for($tmpi=1;$tmpi<=abs($diff_w);$tmpi++){
                            array_push($new_data_chunk[2],'0');
                        }
                        $new_data_chunk[2] = array_slice($new_data_chunk[2],-52,52);   
                    }else{
                        for($tmpi=1;$tmpi<=abs($diff_w);$tmpi++){
                            array_unshift($new_data_chunk[2],'0');
                        }
                        $new_data_chunk[2] = array_slice($new_data_chunk[2],0,52);
                    }
                }
                $avg_m = []; 
                foreach($new_data_chunk[1] as $k=>$v){
                    $i=$avg_m[$k]=0; $empty_v=[];
                    for($tmpi=0;$tmpi<=2;$tmpi++){
                        if($new_data_chunk[$tmpi][$k]>0){
                            $i++;
                        }else{
                            $empty_v[]=$tmpi;
                        }
                        $avg_m[$k]+=$new_data_chunk[$tmpi][$k];
                    }
                    $avg_m[$k]=round($avg_m[$k]/$i,2);
                    foreach($empty_v as $k1=>$v1){
						$lr_value = (array_get($new_data_chunk[$v1],($k+1))>0)?array_get($new_data_chunk[$v1],($k+1)):array_get($new_data_chunk[$v1],($k-1));
						$llrr_value = (array_get($new_data_chunk[$v1],($k+2))>0)?array_get($new_data_chunk[$v1],($k+2)):array_get($new_data_chunk[$v1],($k-2));
                        $new_data_chunk[$v1][$k] = round((($lr_value>0)?$lr_value:$llrr_value),4);
                    }
                }

                $symmetry = array_merge_recursive($new_data_chunk[0],$new_data_chunk[1],$new_data_chunk[2]);
                
                $avg_m_avg = round(array_sum($avg_m)/count($avg_m),2);

                $season_p = [];
                foreach($avg_m as $k=>$v){
                    $season_p[$k]=round($v/$avg_m_avg,6);
                }

                for($tmpi=0;$tmpi<=2;$tmpi++){
                    foreach($new_data_chunk[$tmpi] as $k=>$v){
                        $new_data_chunk[$tmpi][$k]=round($v/$season_p[$k],2);
                    }
                }
                $xy = array_merge_recursive($new_data_chunk[0],$new_data_chunk[1],$new_data_chunk[2]);
                $x = array_keys($xy);
                $y = array_values($xy);
                $a = slope($y,$x);
                $b = intercept($y,$x);
                
                for($tmpi = 156;$tmpi<=207;$tmpi++){
                    $u[$tmpi] = ($b + $a*$tmpi)*$season_p[$tmpi-156];    
                }
                $this->keyword->symmetry = serialize($symmetry);
                $this->keyword->season = serialize($season_p);
                $this->keyword->xy = serialize($xy);
                $this->keyword->a = $a;
                $this->keyword->b = $b;
                $this->keyword->u = serialize($u);
            }
            $this->keyword->date_from = $dateFrom;
            $this->keyword->date_to = $dateTo;
            $this->keyword->data = serialize($data);
    		$this->keyword->updated_at = Carbon::now()->toDateTimeString();
    		$this->keyword->save();
        } catch (Exception $ex) {
            throw $ex;
        }
    }
}
