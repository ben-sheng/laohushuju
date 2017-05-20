<?php
namespace app\ebay;
use app\ebay\EbaySpider;
use app\model\EbayCategoryEsModel;
use app\model\EbayThreadLogEsModel;
use app\model\EbayListingEsModel;
use app\model\EbaySellerEsModel;

class EbaySeller
{

    function __construct()
    {}

    function __destruct()
    {}
    
    /**
     * 抓商店的评论数、开店日期等等
     * @param array $argv
     */
    function fetchSellerInfo(array $argv){
        $tasktype = 1;
        $rundate = date("Y-m-d",time());
        $starttime = time();
        $taskid = $argv["subtaskid"];
        $threadid = $argv["threadsn"];
        $ctime = time()-24*3600;//抓取的店铺要求是在这个时间之前更新过的
    
        $esm  = new EbaySellerEsModel();
        $spider = new EbaySpider();
        $TLog = new EbayThreadLogEsModel();
    
        $country = $argv["country"];
        $start = $argv["start"];
        $total = $argv["total"];
        $size = $argv["size"];
        $crawltime = $argv["crawltime"];
        $loops = 0;
        $counts=0;
        //循环分类
        while($counts<$total){
            $failing = 0;
            $params = $esm->getParams();
            $params["_source"]=array("sellerid","positivefeedbacks");
            $params["from"]=$start;
            $params["size"]=$size;
            $params["body"]=array("query"=>array(
                "bool"=>array("filter"=>array(
//                    array("range"=>array("updatetime"=>array("lte"=>$ctime))),
                    array("term"=>array("crawler"=>1)),
                    array("range"=>array("updatetime"=>array("gte"=>$crawltime)))
                )))
            );
            $result = $esm->client->search($params);
            $itemnum = 0;
            echo "\r\ntotal: ".$result['hits']['total']." hits: ".count($result['hits']['hits']);
            if(!empty($result) && count($result['hits']['hits'])>0){
                $pround = 0;
                $totallistings = 0;
    
                //取出每个分类，然后分页获取商品数据
                $bulks = array();
                foreach($result['hits']['hits'] as $hit){
                    $loops++;
                    $res = $spider->crawlStoreFeedbackInfo($hit["_source"]["sellerid"]);
                    if(empty($res))continue;
                    $bulkhead = $esm->getBulkParams($hit["_source"]["sellerid"]);
                    $bulks['body'][] = array("update"=>$bulkhead);
                    $positivefeedbacks = null;
                    if(isset($hit["_source"]['positivefeedbacks'])){
                        $positivefeedbacks = $hit["_source"]['positivefeedbacks'];
                    }
                    if(!empty($positivefeedbacks)){
                        $positivefeedbacks[]=$res['positivefeedbacks'];
                        $res['positivefeedbacks'] = $positivefeedbacks;
                    }
                    $res["updatetime"]=time();
                    $res["crawler"]=2;
                    $bulks['body'][] = array(
                        "doc"=>$res
                    );
                }
  
                try{
                    $res_items = 0;
                    $res_error = 0;
                    if(count($bulks)>0){
                        //                           echo "\nbulks:".json_encode($bulks);
                        $responses = $esm->client->bulk($bulks);
                        $res_items = count($responses["items"]);
                        $res_error = $responses["errors"];
                        //echo "\r\n".json_encode($responses);
                    }
                }catch(\Exception $e){
                    echo "\r\n".json_encode($bulks);
                    echo "\r\n".$e->getMessage();
                }   
               echo "\n$loops:  taskid $taskid  threadid $threadid country $country   bulk_counts $res_items bulk_errors $res_error";
    
          }
            $counts += $size;
            $log = array(
                "subtaskid"=>$argv["subtaskid"],
                "machineid"=>$argv["machineid"],
                "rundate"=>$rundate,
                "tasktype"=>$tasktype,
                "total"=>$total,
                "complete"=>$counts,
                "failing"=>$failing,
                "threadid"=>$threadid,
                "starttime"=>$starttime,
                "v1"=>$itemnum,
            );
            $TLog->log($log);
            sleep(rand(1,3));
        }
    }
    
    function sellerWrapper($cmd, $country, $tasktotal, $tasksn, $taskid, $threadtotal, $threadsn=0,$crawltime ){
        $workers = array();
        if(empty($country)){
            $country = "US";
        }
        if(empty($tasktotal) || $tasksn>=$tasktotal || empty($taskid) ||empty($threadtotal)) return;
        $ecm = new EbaySellerEsModel();

        $filter = array(
            array("term"=>array("crawler"=>1)),
            array("range"=>array("updatetime"=>array("gte"=>$crawltime)))
        );
        $param = $ecm->getParams();
        $param["body"]=array(
            "query"=>array(
                "bool"=>array("filter"=>$filter)
            )
        );
        $result = $ecm->client->search($param);
        if(empty($result) || empty($result["hits"]["total"])) return;
        $itemtotal = $result["hits"]["total"];
        //每个任务应该完成的数量
        $taskitems = intval( ceil( $itemtotal/$tasktotal ) );
        $taskstart = $tasksn * $taskitems;
        $threaditems =  intval( ceil( $taskitems/$threadtotal ) );
        $threadstart = $taskstart + $threadsn*$threaditems;
        
        $argv = array(
            "country"=>$country,
            "subtaskid"=>$taskid,
            "machineid"=>$tasksn,
            "threadsn"=>$threadsn,
            "start"=>$threadstart,
            "size"=>20,
            "total"=>$threaditems
        );
        if($cmd=="info"){
            $argv["start"]= $tasksn*$threadtotal*1000 + $threadsn*1000;
            $this->fetchSellerInfo($argv);
        }elseif($cmd=="sellerxxx"){
            $argv["start"]= $tasksn*$threadtotal*1000 + $threadsn*1000;
            $this->crawlListingsFromSeller($argv);
        }
        
    }
    
}
?>