<?php
namespace app\ebay;
use app\ebay\EbaySpider;
use app\model\EbayCategoryEsModel;
use app\model\EbayThreadLogEsModel;
use app\model\EbayListingEsModel;
use app\model\EbaySellerEsModel;

class EbayListing
{
    const LISTING_MAXID = 401400000000;
    const LISTING_MINID = 2230000000;
    function __construct()
    {}

    function __destruct()
    {}
    
    /**
     * 抓取所有分类信息，存入到es库
     * @param $argv[start,size,total,subtaskid,machineid,threadid]
     */
    function crawlListingsFromCategory(array $argv){       
        $tasktype = 1;
        $rundate = date("Y-m-d",time());
        $starttime = time();    
        $taskid = $argv["subtaskid"];
        $threadid = $argv["threadsn"];
                
        $ecmodel = new EbayCategoryEsModel();
        $spider = new EbaySpider();
        $elmodel = new EbayListingEsModel();        
        $TLog = new EbayThreadLogEsModel();
        
        $country = $argv["country"];
        $start = $argv["start"];
        $total = $argv["total"];
        $size = $argv["size"];
        $loops = 0;
        //循环分类
        for($counts=0;$counts<$total;){
            $failing = 0;
            $params = $ecmodel->getParams();
            $params["_source"]=array("categoryid","ebaycategoryid","categoryurl","listings");
            $params["from"]=$start+$counts;
            $params["size"]=$size;
            $params["body"]=array("query"=>array(
               "bool"=>array("filter"=>array(
                   array("term"=>array("leaf"=>1)),
                   array("term"=>array("crawler"=>1)),
//                   array("range"=>array("updatetime"=>array("lte"=>time()-24*3600)))
//                    array("term"=>array("productid"=>"32728437130"))
               )))
            );
            $result = $ecmodel->client->search($params);
            $itemnum = 0;
            echo "\r\ntotal: ".$result['hits']['total']." hits: ".count($result['hits']['hits']);
            if(!empty($result) && count($result['hits']['hits'])>0){
                $pround = 0;                
                $totallistings = 0;
                
                //取出每个分类，然后分页获取商品数据
                foreach($result['hits']['hits'] as $hit){
                    $loops++;
                    $page = 1;
                    $listings = 0;
                    $nullops = 0;//没有获得数据，连续3次就退出
                    $oldcounts = 0;
                    $categoryid = $hit["_source"]["categoryid"];
                    $cgurl = $hit["_source"]["categoryurl"];
                    if(empty($categoryid) || empty($cgurl))continue;
                    
                    //分页获取产品数据,如果没有数据可抓了，置$page=0
                    while($page){
                        $res = $spider->crawlProductsByCategory($country, $categoryid, $cgurl, $page,12);
 //                       echo "\nres:::".json_encode($res);
                        if(empty($res) || empty($res["items"])){
                            $nullops++;
                            if($nullops>=4){
                                $failing++;
                                break;
                            }else{
                                continue;
                            }
                        }
                        $listings = $res["listings"];                        
                        $items = $res["items"];
                        $bulks = array();
                        $uptime = time();
                        foreach($items as $item){
                            if(empty($item) || !isset($item["lid"]))continue;
                            $item["updatetime"]=$uptime;
                            $item["watchinglog"]=array();
                            $item["soldlog"]=array();
                            $item["lidsn"]=$item["lid"];
                            
                            $bulkhead = $elmodel->getBulkParams($item["lid"]);
                            $bulks['body'][] = array("update"=>$bulkhead);
                            $script = array(
//                                "inline"=>"def list=[params.country];if (list.contains(ctx._source.country)){}else{list.add(ctx._source.country)};ctx._source.country=list;",
//                                "inline"=>"ctx._source.country+=params.country",
//                                "inline"=>"if(ctx._source.country.contains(params.country)==false){ctx._source.country.add(params.country)}ctx._source.updatetime=params.utime;",
                                "inline"=>'def clist=[params.country];if(ctx._source.country  instanceof String){if(ctx._source.country!=params.country){clist.add(ctx._source.country);}ctx._source.country=clist;}else{if(!ctx._source.country.contains(params.country)){ctx._source.country.add(params.country);}}ctx._source.updatetime=params.utime;',
                                "lang"=>"painless",
                                "params"=>array(
                                    "country" =>$item['country'][0],
                                    "utime"=>$uptime
                                )
                            );
                            $bulks['body'][] = array(
                               "script"=>$script,
//                                 "doc"=>array(
//                                      "lurl"=>$item['lurl'],
//                                     "lformat"=>$item['lformat'],
//                                     "ltitle"=>$item['ltitle'],
//                                     "country"=>$item['country'],
//                                     "updatetime"=>$item['updatetime'],
//                                     "soldlog"=>$item['soldlog'],
//                                     "watchinglog"=>$item['watchinglog'],
//                                 ),
                                "upsert"=>$item
                            );
                            $itemnum++;
                        }
                        
                        
                        try{
                            $res_items = 0;
                            $res_error = 0;
                            if(count($bulks)>0){
     //                           echo "\nbulks:".json_encode($bulks);
                                $responses = $elmodel->client->bulk($bulks);
                                $res_items = count($responses["items"]);
                                $res_error = $responses["errors"];
                                //第一次抓取时，此处无用。只在后续追加抓取时才需要
    //                             if(!empty($responses) && !empty($responses['items'])){
    //                                 foreach($responses['items'] as $item){
    //                                     if($item["update"]["result"]!="created" && $item["update"]["_type"]==$ape->type){
    //                                         $oldcounts++;
    //                                     }
    //                                 }
    //                             }
   //                             echo "\r\n".json_encode($responses);
                                //{"took":421,"errors":false,"items":[{"index":{"_index":"matrix_ali_1","_type":"aliproducts_1","_id":"32788003011","_version":1,"result":"created","_shards":{"total":2,"successful":1,"failed":0},"created":true,"status":201}},{"index":{"_index":"matrix_ali_1","_type":"aliproducts_1","_id":"32784685534","_version":1,"result":"created","_shards":{"total":2,"successful":1,"failed":0},"created":true,"status":201}},{"index":{"_index":"matrix_ali_1","_type":"aliproducts_1","_id":"32785432534","_version":1,"result":"created","_shards":{"total":2,"successful":1,"failed":0},"created":true,"status":201}},{"index":{"_index":"matrix_ali_1","_type":"aliproducts_1","_id":"32785450575","_version":1,"result":"created","_shards":{"total":2,"successful":1,"failed":0},"created":true,"status":201}},{"index":{"_index":"matrix_ali_1","_type":"aliproducts_1","_id":"32787491809","_version":1,"result":"created","_shards":{"total":2,"successful":1,"failed":0},"created":true,"status":201}},{"index":{"_index":"matrix_ali_1","_type":"aliproducts_1","_id":"32785352779","_version":1,"result":"created","_shards":{"total":2,"successful":1,"failed":0},"created":true,"status":201}},{"index":{"_index":"matrix_ali_1","_type":"aliproducts_1","_id":"32784685490","_version":1,"result":"created","_shards":{"total":2,"successful":1,"failed":0},"created":true,"status":201}},{"index":{"_index":"matrix_ali_1","_type":"aliproducts_1","_id":"32785396632","_version":1,"result":"created","_shards":{"total":2,"successful":1,"failed":0},"created":true,"status":201}},{"index":{"_index":"matrix_ali_1","_type":"aliproducts_1","_id":"32787571442","_version":1,"result":"created","_shards":{"total":2,"successful":1,"failed":0},"created":true,"status":201}},{"index":{"_index":"matrix_ali_1","_type":"aliproducts_1","_id":"32783324277","_version":1,"result":"created","_shards":{"total":2,"successful":1,"failed":0},"created":true,"status":201}},{"index":{"_index":"matrix_ali_1","_type":"aliproducts_1","_id":"32782774344","_version":1,"result":"created","_shards":{"total":2,"successful":1,"failed":0},"created":true,"status":201}},{"index":{"_index":"matrix_ali_1","_type":"aliproducts_1","_id":"32782037325","_version":1,"result":"created","_shards":{"total":2,"successful":1,"failed":0},"created":true,"status":201}},{"index":{"_index":"matrix_ali_1","_type":"aliproducts_1","_id":"32784843302","_version":1,"result":"created","_shards":{"total":2,"successful":1,"failed":0},"created":true,"status":201}},{"index":{"_index":"matrix_ali_1","_type":"aliproducts_1","_id":"32782021298","_version":1,"result":"created","_shards":{"total":2,"successful":1,"failed":0},"created":true,"status":201}},{"index":{"_index":"matrix_ali_1","_type":"aliproducts_1","_id":"32779434563","_version":1,"result":"created","_shards":{"total":2,"successful":1,"failed":0},"created":true,"status":201}},{"index":{"_index":"matrix_ali_1","_type":"aliproducts_1","_id":"32779007627","_version":1,"result":"created","_shards":{"total":2,"successful":1,"failed":0},"created":true,"status":201}},{"index":{"_index":"matrix_ali_1","_type":"aliproducts_1","_id":"32773564685","_version":1,"result":"created","_shards":{"total":2,"successful":1,"failed":0},"created":true,"status":201}},{"index":{"_index":"matrix_ali_1","_type":"aliproducts_1","_id":"32773634640","_version":1,"result":"created","_shards":{"total":2,"successful":1,"failed":0},"created":true,"status":201}},{"index":{"_index":"matrix_ali_1","_type":"aliproducts_1","_id":"32767417560","_version":5,"result":"updated","_shards":{"total":2,"successful":1,"failed":0},"created":false,"status":200}},{"index":{"_index":"matrix_ali_1","_type":"aliproducts_1","_id":"32765092251","_version":1,"result":"created","_shards":{"total":2,"successful":1,"failed":0},"created":true,"status":201}},{"index":{"_index":"matrix_ali_1","_type":"aliproducts_1","_id":"32764708095","_version":1,"result":"created","_shards":{"total":2,"successful":1,"failed":0},"created":true,"status":201}},{"index":{"_index":"matrix_ali_1","_type":"aliproducts_1","_id":"32764073936","_version":1,"result":"created","_shards":{"total":2,"successful":1,"failed":0},"created":true,"status":201}},{"index":{"_index":"matrix_ali_1","_type":"aliproducts_1","_id":"32767143484","_version":1,"result":"created","_shards":{"total":2,"successful":1,"failed":0},"created":true,"status":201}},{"index":{"_index":"matrix_ali_1","_type":"aliproducts_1","_id":"32764576984","_version":1,"result":"created","_shards":{"total":2,"successful":1,"failed":0},"created":true,"status":201}},{"index":{"_index":"matrix_ali_1","_type":"aliproducts_1","_id":"32764866803","_version":1,"result":"created","_shards":{"total":2,"successful":1,"failed":0},"created":true,"status":201}},{"index":{"_index":"matrix_ali_1","_type":"aliproducts_1","_id":"32767139411","_version":1,"result":"created","_shards":{"total":2,"successful":1,"failed":0},"created":true,"status":201}},{"index":{"_index":"matrix_ali_1","_type":"aliproducts_1","_id":"32764850826","_version":1,"result":"created","_shards":{"total":2,"successful":1,"failed":0},"created":true,"status":201}},{"index":{"_index":"matrix_ali_1","_type":"aliproducts_1","_id":"32764049856","_version":1,"result":"created","_shards":{"total":2,"successful":1,"failed":0},"created":true,"status":201}},{"index":{"_index":"matrix_ali_1","_type":"aliproducts_1","_id":"32764934262","_version":1,"result":"created","_shards":{"total":2,"successful":1,"failed":0},"created":true,"status":201}},{"index":{"_index":"matrix_ali_1","_type":"aliproducts_1","_id":"32764652420","_version":1,"result":"created","_shards":{"total":2,"successful":1,"failed":0},"created":true,"status":201}},{"index":{"_index":"matrix_ali_1","_type":"aliproducts_1","_id":"32764548859","_version":1,"result":"created","_shards":{"total":2,"successful":1,"failed":0},"created":true,"status":201}},{"index":{"_index":"matrix_ali_1","_type":"aliproducts_1","_id":"32766115394","_version":1,"result":"created","_shards":{"total":2,"successful":1,"failed":0},"created":true,"status":201}},{"index":{"_index":"matrix_ali_1","_type":"aliproducts_1","_id":"32766123318","_version":1,"result":"created","_shards":{"total":2,"successful":1,"failed":0},"created":true,"status":201}},{"index":{"_index":"matrix_ali_1","_type":"aliproducts_1","_id":"32766103279","_version":1,"result":"created","_shards":{"total":2,"successful":1,"failed":0},"created":true,"status":201}},{"index":{"_index":"matrix_ali_1","_type":"aliproducts_1","_id":"32760907290","_version":1,"result":"created","_shards":{"total":2,"successful":1,"failed":0},"created":true,"status":201}},{"index":{"_index":"matrix_ali_1","_type":"aliproducts_1","_id":"32751568714","_version":1,"result":"created","_shards":{"total":2,"successful":1,"failed":0},"created":true,"status":201}},{"index":{"_index":"matrix_ali_1","_type":"aliproducts_1","_id":"32747818187","_version":1,"result":"created","_shards":{"total":2,"successful":1,"failed":0},"created":true,"status":201}},{"index":{"_index":"matrix_ali_1","_type":"aliproducts_1","_id":"32747778076","_version":1,"result":"created","_shards":{"total":2,"successful":1,"failed":0},"created":true,"status":201}},{"index":{"_index":"matrix_ali_1","_type":"aliproducts_1","_id":"32742683348","_version":1,"result":"created","_shards":{"total":2,"successful":1,"failed":0},"created":true,"status":201}},{"index":{"_index":"matrix_ali_1","_type":"aliproducts_1","_id":"32740248316","_version":1,"result":"created","_shards":{"total":2,"successful":1,"failed":0},"created":true,"status":201}},{"index":{"_index":"matrix_ali_1","_type":"aliproducts_1","_id":"32737538364","_version":1,"result":"created","_shards":{"total":2,"successful":1,"failed":0},"created":true,"status":201}},{"index":{"_index":"matrix_ali_1","_type":"aliproducts_1","_id":"32739579494","_version":1,"result":"created","_shards":{"total":2,"successful":1,"failed":0},"created":true,"status":201}},{"index":{"_index":"matrix_ali_1","_type":"aliproducts_1","_id":"32737466946","_version":1,"result":"created","_shards":{"total":2,"successful":1,"failed":0},"created":true,"status":201}},{"index":{"_index":"matrix_ali_1","_type":"aliproducts_1","_id":"32739555633","_version":1,"result":"created","_shards":{"total":2,"successful":1,"failed":0},"created":true,"status":201}},{"index":{"_index":"matrix_ali_1","_type":"aliproducts_1","_id":"32739211486","_version":1,"result":"created","_shards":{"total":2,"successful":1,"failed":0},"created":true,"status":201}},{"index":{"_index":"matrix_ali_1","_type":"aliproducts_1","_id":"32735805316","_version":1,"result":"created","_shards":{"total":2,"successful":1,"failed":0},"created":true,"status":201}},{"index":{"_index":"matrix_ali_1","_type":"aliproducts_1","_id":"32732750587","_version":4,"result":"updated","_shards":{"total":2,"successful":1,"failed":0},"created":false,"status":200}},{"index":{"_index":"matrix_ali_1","_type":"aliproducts_1","_id":"32727497959","_version":1,"result":"created","_shards":{"total":2,"successful":1,"failed":0},"created":true,"status":201}}]}
                            }
                       }catch(\Exception $e){
                            echo "\r\n".json_encode($bulks);
                            echo "\r\n".$e->getMessage();
                        }

                        echo "\n$loops:  taskid $taskid  threadid $threadid country $country category $categoryid   page $page listings $listings  bulk_counts $res_items bulk_errors $res_error mem[".memory_get_usage()."]";                        
                        
                        $nullops = 0;                        
                        if($listings <= $res["count"]*$page || $oldcounts>$res["count"]*2){
                            $page = 0;
                        }else{
                            $page++;
                        }
                     }
                     if($listings > $hit["_source"]["listings"]){
                         $ecparams = $ecmodel->getParams($hit["_source"]["categoryid"]);
                         $ecparams["body"]["doc"]=array(
                             "listings"=>$listings
                         );
                         $ecmodel->client->update($ecparams);
                     }
                } 
                //每个分类
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
    
    /**
     * 锁定，准备获取maxid，然后再设置
     * 参考http://blog.csdn.net/ugg/article/details/41894947
     * @param \Redis $redis
     * @return unknown
     */
    function lock(\Redis $redis, $lockkey){
        if(empty($lockkey)){
            $lockkey = "linstinginfomark.lock";
        }
        $lockvalue = 0;//0失败，1成功        
        while(true){
            $now = time();
            $res = $redis->setnx($lockkey,time());
            if($res){//锁定成功,返回
                $lockvalue = 1;
                break;
            }else{
                $tt = $redis->get($lockkey);
                echo "\n now-tt:::".($now-$tt);
                if($now-$tt>600){//10分钟没有解锁，意味着被锁堵塞了
                    $nt = $redis->getSet($lockkey,time());
                    if($nt==$tt){//说明锁定成功
                        $lockvalue=1;
                        break;
                    }else{
                        echo "\nsleep111";
                        sleep(2);//继续等待
                    }
                }else{//未堵塞，等待2s再去取锁
                    echo "\nsleep222";
                    sleep(2);
                }
            }
        }
        return $lockvalue;        
    }
    
    function unlock(\Redis $redis, $lockkey){
        if(empty($lockkey)){
            $lockkey = "linstinginfomark.lock";
        }
        return $redis->delete($lockkey);
    }
    
    function fetchListingInfo($country, $size, $upsize, $taskid,  $threadid, $crawltime){//($country, $from, $size, $total){
        if(empty($country)) $country="us";
        $looptotal = 0;        
        $loopfailing = 0;//连续3轮失败，退出
        $crawltime = time()-24*3600;
        $lid = 0;
        $minid = 0;
        
        $elm = new EbayListingEsModel();
        $spider = new EbaySpider();
        $redis = new EbayRedis();
        
        //只有在无数据可以处理了，才停止，每个机器不分配任务，自动抓取。
        //连续5次没有抓到数据，并且曾经处理过数据，避免在某个id区间没有数据时退出
        while($minid<=self::LISTING_MAXID ){
            $lockkey = "listinginfokey.lock";
            $locked = $redis->lock($lockkey);//循环执行，直到获得锁
            //如果没有获得锁，直接重新获取
            if(!$locked){
                echo "\nLock failed,continue....";
                sleep(2);
                continue;
            }
            $minid = $redis->get("listinginfomaxid");
            if(empty($minid)){
                $minid=self::LISTING_MINID;
            }
            $maxid = $minid +100000000;
            echo "\nInfoMinid:$minid ---> maxid:$maxid";
            $redis->setex("listinginfomaxid",3600,$maxid);
            $redis->unlock($lockkey);
            
            //每次完成1亿编号范围内的抓取。如果连续5次search为[]，则表明该段内已经没有数据，break，开始取下一轮的minid
            $localminid = $minid;
            $roundtotal = 0;
            $failing = 0;//连续失败3次，终止循序
            while($localminid<$maxid){
                $params = $elm->getParams();
                $params["from"]=0;
                $params["size"]=$size;
                $params["_source"]=array("lurl","lid","lidsn");
                $params["body"]=array(
                    "sort"=>array(array("lidsn"=>"asc")),
                    "query"=>array(
                        "bool"=>array( 
                            "must"=>array(
                                array("term"=>array("lformat"=>"BUY")), 
                                array("range"=>array("lidsn"=>array("gte"=>$localminid))),
                            ),
                            "must_not"=>array("exists"=>array("field"=>"soldnum"))
                        )
                    ),
                );
    //             echo "\nparams:".json_encode($params);
                $result = $elm->client->search( $params );
                
                if( (empty($result) || empty($result["hits"]["hits"])) ){
    //                $redis->unlock($lockkey);
                    if( $failing<3){
                        $failing++;
                        continue;
                    }else{
                        $loopfailing++;
                        break;
                    }
                }
                //获取数据正常，则failing置0，只有在连续失败时，才会累加
                $failing = 0;
                $loopfailing = 0;
                
                $hitcounts = count($result["hits"]["hits"]);
                $looptotal += $hitcounts;
                $roundtotal += $hitcounts;
                $localminid = $result["hits"]["hits"][$hitcounts-1]["_source"]["lidsn"]+1;
                
                $sellers = array();
                $bulks = array();
                $starttime = time();            
                $hitloop = 0;
                foreach($result["hits"]["hits"] as $hit){
                    if(empty($hit) || empty($hit["_source"]["lurl"]))continue;
                    $lid = $hit["_id"];
                   //如果id大于当前id范围，则break;
                    if($hit["_source"]["lidsn"]>=$maxid)break;
                    $hitloop++;
                    
//                     echo "\n$lid";
                    $res = $spider->crawlProduct("info", $country, $hit["_source"]["lurl"]);
                    if(empty($res) || count($res)<=1)continue;
                    
                    $t = time();
                    if($res["status"]===0){
                        $res["watchings"]=array(array("t"=>$t,"v"=>$res['watchingnum']));
                        $res["solds"]=array(array("t"=>$t,"v"=>$res['soldnum']));
                    }
                    $res["updatetime"]=$t;
                    $res["lidsn"]=$lid;
                    $res["revenue"]=$res["lprice"]*$res["soldnum"];
                    $bulkhead = $elm->getBulkParams($lid);
                    $bulks['body'][] = array("update"=>$bulkhead);
    //                 $script = array(
    //                 //                                "inline"=>"def list=[params.country];if (list.contains(ctx._source.country)){}else{list.add(ctx._source.country)};ctx._source.country=list;",
    //                 //                                "inline"=>"ctx._source.country+=params.country",
    //                     "inline"=>"if (ctx._source[\"watchinlog\"] == null){ctx._source.watchinlog=params.watching}else{ctx._source.watchinlog.add(params.watching)};if (ctx._source[\"soldlog\"] == null){ctx._source.soldlog=params.sold}else{ctx._source.soldlog.add(params.sold)}",
    //                     "lang"=>"painless",
    //                     "params"=>array(
    //                         "watching" =>array(array("n"=>$res['watchingnum'],"t"=>time())),
    //                         "sold"=>array(array("n"=>$res['soldnum'],"t"=>time())),
    //                     )
    //                 );
                    $bulks['body'][] = array(
    //                     "script"=>$script,
                        "doc"=>$res,
    //                    "upsert"=>$item
                    );  
                    
                    if($res["status"]===0){
                        $sellers[$res["seller"]] = array(
                            "sellerid"=>$res["seller"],
                            "sellerpage"=>$res["sellerpage"],
                            "listings"=>0,
                            "updatetime"=>$t,
                            "crawler"=>1
                        );
                    }
                    
                    //每200条update一下。
                    if($hitloop%$upsize==0 || $hitloop>=$hitcounts){
                        $bulkres = $this->updateSoldBulks($elm, $bulks);
                        echo "\nsize:$size loop:$hitloop  taskid $taskid threadid $threadid size $size  lid $lid  bulk_counts ".$bulkres["bulkitems"]." bulk_errors ".$bulkres["bulkitems"]." stime:$starttime";
                        
                        //SELL
                        $this->updateSellers($sellers);
                        
                        sleep(3);
                        unset($bulks);
                        $bulks = array();  
                    }
                }  
                 //由于在循环内的各种continue和break，在循环内未完成bulk操作。那么在循环出来之后，还需要操作一次。
                if(!empty($bulks)){
                    $bulkres = $this->updateSoldBulks($elm, $bulks);
                    echo "\nsize:$size loop:$hitloop  taskid $taskid threadid $threadid size $size  lid $lid  bulk_counts ".$bulkres["bulkitems"]." bulk_errors ".$bulkres["bulkitems"]." stime:$starttime";
                }
                echo "\ncomplete round $size total $roundtotal time:".time();
            }
            echo "\ncomplete min $minid to max $maxid  total $looptotal  loopfailing $loopfailing time:".time();
        }
        
    }
    
    /**
     * 抓取销售数据，只抓取soldnum>0,或者上架日期在最近15天内的商品，或者crawler=1的
     * 每次抓取时,从中心redis获得from的数据，指定当前抓取偏移量，并且把偏移量增加Size，把当前在抓取中商品排除在外，避免重复抓取。
     * 一批商品抓取完成后，自动减去偏移量。全部自动进行抓取，直到所有符合条件的数据抓取完。
     * //所有机器自动抓取未完成的数据（lidsn>listingsoldmaxid)，每次$size个，直到没有数据可抓为止（总任务完成，所有符合条件的数据都已经抓完）
     * 随时可以加入新的机器和线程，参与抓取。每个机器和每个线程不分配总任务。直到所有抓完为止
     * @param unknown $country
     * @param unknown $from
     * @param unknown $size
     * @param unknown $total
     */
    function fetchListingInfo2($cmd, $country, $querysize, $upsize, $taskid,  $threadid, $crawltime){
        $lockkey = "listingsoldkey.lock";
        $fromkey = "listingsoldfrom";
        if($cmd=="info"){
            $lockkey = "listinginfokey.lock";
            $fromkey = "listinginfofrom";
        }
        if(empty($country)) $country="us";
        $loopfailing = 0;
        $looptotal = 0;
        $failing = 0;//连续失败3次，终止循序
        $loops = 0;
        $hitloop = 0;
        $crawltime = time()-48*3600;//第一次时用>2小时的。因为还有很多没有抓完的数据。
        $lid = 0;
        $minid = 0;
    
        $elm = new EbayListingEsModel();
        $spider = new EbaySpider();
        $redis = new EbayRedis();
    
        //只有在无数据可以处理了，才停止，每个机器不分配任务，自动抓取。
        //连续5次没有抓到数据，并且曾经处理过数据，避免在某个id区间没有数据时退出
        while($loopfailing<10){
            $loops++;
            $from = $redis->getQueryFrom($lockkey,$fromkey,$querysize);
            if($from===false){
                break;//无法获取到偏移量，直接退出
            }
            
            $loopfailing = 0;
            echo "\nquery from: $from ---> size :$querysize";

    
            //每次完成1亿编号范围内的抓取。如果连续5次search为[]，则表明该段内已经没有数据，break，开始取下一轮的minid
            $localminid = $minid;
            $roundtotal = 0;
            //             while($localminid<$maxid){
            $params = $elm->getParams();
            $params["from"]=$from;
            $params["size"]=$querysize;
            $params["_source"]=array("lurl","lid","lidsn");
            $params["body"]=array(
            //                   "sort"=>array(array("lidsn"=>"asc")),
                "query"=>array(
                    "bool"=>array(
                        "must"=>array(
                            array("term"=>array("lformat"=>"BUY")),
                            array("range"=>array("soldnum"=>array("gte"=>1))),
                            array("range"=>array("updatetime"=>array("lt"=>$crawltime))),
                        ),
                        //                         "should"=>array(
                        //                             array("bool"=>array(
                        //                                 "must_not"=>array("exists"=>array("field"=>"soldnum"))
                        //                             )),
                        //                             array("range"=>array("updatetime"=>array("lt"=>$crawltime))),
                        //                         ),
                        //                         "must_not"=>array("exists"=>array("field"=>"soldnum"))
                    )
                ),
                //                 "aggs"=>array(
                //                     "maxid"=>array("max"=>array("field"=>"lidsn"))
                //                 )
            );
            if($cmd=="info"){
                $params["body"]=array(
                    "query"=>array(
                        "bool"=>array(
                            "must"=>array(
                                array("term"=>array("lformat"=>"BUY")),
                            ),
                            "must_not"=>array("exists"=>array("field"=>"soldnum"))
                        )
                    ),
                );
            }
             
            $result = $elm->client->search( $params );
            //            echo "\nparams:".json_encode($params);
    
            if( (empty($result) || empty($result["hits"]["hits"])) ){
                $redis->decQyeryFromSize($lockkey,$fromkey,$querysize);
                if( $failing<3){
                    $failing++;
                    continue;
                }else{
                    break;
                }
            }
            //获取数据正常，则failing置0，只有在连续失败时，才会累加
            $failing = 0;
    
            $hitcounts = count($result["hits"]["hits"]);
            $looptotal += $hitcounts;
            //                $localminid = $result["hits"]["hits"][$hitcounts-1]["_source"]["lidsn"]+1;
    
            $sellers = array();
            $bulks = array();
            $starttime = time();
            $hitloop = 0;
            foreach($result["hits"]["hits"] as $hit){
                if(empty($hit) || empty($hit["_source"]["lurl"]))continue;
                $lid = $hit["_id"];
//                echo "\n$lid";
                
                $hitloop++;
                list($bulkhead,$bulkbody) = $this->fetchInfo($cmd,$country,$hit,$elm,$spider,$sellers);
                if(!empty($bulkhead) && !empty($bulkbody)){
                    $bulks['body'][]=$bulkhead;
                    $bulks['body'][]=$bulkbody;
                }
                
                if($hitloop%$upsize==0 || $hitloop>=$hitcounts){
                    $bulkres = $this->updateSoldBulks($elm, $bulks);
                    sleep(3);
                    if($bulkres["bulkitems"]){
                        //减去下一次抓取时的偏移量
                        $redis->decQyeryFromSize($lockkey,$fromkey,$bulkres["bulkitems"]);
                    }
                    unset($bulks);
                    $bulks = array();
                }
            }
            //由于在循环内的各种continue和break，在循环内未完成bulk操作。那么在循环出来之后，还需要操作一次。
            if(!empty($bulks)){
                $bulkres = $this->updateSoldBulks($elm, $bulks);
                sleep(3);
                if($bulkres["bulkitems"]){
                    $redis->decQyeryFromSize($lockkey,$fromkey,$bulkres["bulkitems"]);
                }
            }            
            //记录卖家信息
           if(!empty($sellers)){
               $this->updateSellers($sellers);
           }
           
           echo "\n".time()." task[$taskid:$threadid] loop $loops  complete $looptotal($hitloop) id $lid";
        }
        echo "\n".time()." finish task[$taskid:$threadid] loop $loops  complete $looptotal($hitloop) id $lid";
    }
    
    /**
     * 抓取指定商品的信息，并返回bulk数据
     * @param unknown $cmd
     * @param unknown $country
     * @param unknown $listing
     * @param EbayListingEsModel $elm
     * @param sellers 引用，用来登记店铺信息
     */
    function fetchInfo($cmd,$country,$listing,EbayListingEsModel $elm,EbaySpider $spider, &$sellers){  
        $bulkresult = array();
        if(empty($cmd) || empty($country) || empty($listing) || empty($elm)){
            return $bulkresult;
        }
        $res = $spider->crawlProduct($cmd,$country, $listing["_source"]["lurl"]);
        if(empty($res) || count($res)<=1)return $bulkresult;
        $t = time();
        if($res["status"]===0 && isset($res['soldnum'])){
            
            $res["watchings"]=array(array("t"=>$t,"v"=>$res['watchingnum']));
            $res["solds"]=array(array("t"=>$t,"v"=>$res['soldnum']));
            $res["updatetime"]=$t;
            $res["lidsn"]=$listing["_id"];
            $res["revenue"]=$res["lprice"]*$res["soldnum"];
            
            //head
            $bulkresult[] = array("update"=>$elm->getBulkParams($listing["_id"]));
            //如果抓取产品详细信息，则直接把抓回的信息保存
            if($cmd=="info"){
                $bulkresult[] = array("doc"=>$res);
                $sellers[$res["seller"]] = array(
                    "sellerid"=>$res["seller"],
                    "sellerpage"=>$res["sellerpage"],
                    "listings"=>0,
                    "updatetime"=>$t,
                    "crawler"=>1
                );
            }else{//如果是每日抓取销量，则只更新销量相关数据
                $script = array(
                    //                                "inline"=>"def list=[params.country];if (list.contains(ctx._source.country)){}else{list.add(ctx._source.country)};ctx._source.country=list;",
                    //                                "inline"=>"ctx._source.country+=params.country",
                    "inline"=>"
                            if(ctx._source.containsKey('watchings') && ctx._source.watchings!=null){ctx._source.watchings.add(params.watchingobj);}else{ctx._source.watchings=[params.watchingobj];}
                            if(ctx._source.containsKey('solds') && ctx._source.solds!=null){ctx._source.solds.add(params.soldobj);}else{ctx._source.solds=[params.soldobj];}
                            ctx._source.updatetime=params.uptime;ctx._source.soldnum=params.soldnum;ctx._source.watchingnum=params.watchingnum;
                            ctx._source.lprice=params.lprice;ctx._source.revenue=params.revenue;ctx._source.lcurrency=params.lcurrency;",
                    "lang"=>"painless",
                    "params"=>array(
                        "t"=>"$t",
                        //                             "watching" =>array(array("n"=>$res['watchingnum'],"t"=>$t)),
                        //                             "soldlog"=>array(array("n"=>$res['soldnum'],"t"=>$t)),
                        "soldnum"=>$res['soldnum'],
                        "soldobj"=>array("t"=>$t,"v"=>$res['soldnum']),
                        "watchingnum"=>$res['watchingnum'],
                        "watchingobj"=>array("t"=>$t,"v"=>$res['watchingnum']),
                        "lprice"=>$res["lprice"],
                        "lcurrency"=>$res["lcurrency"],
                        "revenue"=>floatval($res["lprice"])*$res['soldnum'],
                        "uptime"=>time()
                    )
                );
                $bulkresult[] = array("script"=>$script);
            }
        }
        return $bulkresult;
    }
    
    /**
     * 抓取销售数据，只抓取soldnum>0,或者上架日期在最近15天内的商品，或者crawler=1的
     * 每次抓取时,从中心redis获得from的数据，指定当前抓取偏移量，并且把偏移量增加Size，把当前在抓取中商品排除在外，避免重复抓取。
     * 一批商品抓取完成后，自动减去偏移量。全部自动进行抓取，直到所有符合条件的数据抓取完。
     * //所有机器自动抓取未完成的数据（lidsn>listingsoldmaxid)，每次$size个，直到没有数据可抓为止（总任务完成，所有符合条件的数据都已经抓完）
     * 随时可以加入新的机器和线程，参与抓取。每个机器和每个线程不分配总任务。直到所有抓完为止
     * @param unknown $country
     * @param unknown $from
     * @param unknown $size
     * @param unknown $total
     */
    function fetchListingSold($country, $size, $upsize, $taskid,  $threadid, $crawltime){
        if(empty($country)) $country="us";        
        $loopfailing = 0;
        $looptotal = 0;
        $failing = 0;//连续失败3次，终止循序
        $crawltime = time()-48*3600;//第一次时用>2小时的。因为还有很多没有抓完的数据。
        $lid = 0;
        $minid = 0;
        
        $elm = new EbayListingEsModel();
        $spider = new EbaySpider();       
        $redis = new EbayRedis();
                
        //只有在无数据可以处理了，才停止，每个机器不分配任务，自动抓取。
        //连续5次没有抓到数据，并且曾经处理过数据，避免在某个id区间没有数据时退出
        while(true){
            $lockkey = "listingsoldkey.lock";
            $fromkey = "listingsoldfrom";
            $locked = $redis->lock($lockkey);//循环执行，直到获得锁
            //如果没有获得锁，直接重新获取
            if(!$locked){
                echo "\nLock failed,continue....";
                sleep(2);
                continue;
            }
            $from = $redis->get($fromkey);
            if(empty($from)){
                $from=0;
            }
            //超过了可以偏移的最大量。默认只有1万。通过设置max_result_window的值，增加到20W。
            if($from>200000){
                continue;
            }
            echo "\nSoldMinid:$from ---> size :$size";
            $redis->setex($fromkey,600,$from+$size);
            $redis->unlock($lockkey);
            
            //每次完成1亿编号范围内的抓取。如果连续5次search为[]，则表明该段内已经没有数据，break，开始取下一轮的minid
            $localminid = $minid;
            $roundtotal = 0;           
            $failing = 0;//连续失败3次，终止循序
//             while($localminid<$maxid){
                $params = $elm->getParams();
                $params["from"]=$from;
                $params["size"]=$size;
                $params["_source"]=array("lurl","lid","lidsn");
                $params["body"]=array(
//                   "sort"=>array(array("lidsn"=>"asc")),
                    "query"=>array(
                        "bool"=>array( 
                            "must"=>array(
                                array("term"=>array("lformat"=>"BUY")), 
                                array("range"=>array("soldnum"=>array("gte"=>1))),
                                array("range"=>array("updatetime"=>array("lt"=>$crawltime))),
  //                              array("range"=>array("lidsn"=>array("gte"=>$localminid))),
                            ),
    //                         "should"=>array(
    //                             array("bool"=>array(
    //                                 "must_not"=>array("exists"=>array("field"=>"soldnum"))
    //                             )),
    //                             array("range"=>array("updatetime"=>array("lt"=>$crawltime))),
    //                         ),
   //                         "must_not"=>array("exists"=>array("field"=>"soldnum"))
                        )
                    ),
    //                 "aggs"=>array(
    //                     "maxid"=>array("max"=>array("field"=>"lidsn"))
    //                 )
                );
                 
                $result = $elm->client->search( $params );
    //            echo "\nparams:".json_encode($params);
                
                if( (empty($result) || empty($result["hits"]["hits"])) ){
    //                $redis->unlock($lockkey);
                    if( $failing<3){
                        $failing++;
                        continue;
                    }else{
                        $loopfailing++;
                        break;
                    }
                }
                //获取数据正常，则failing置0，只有在连续失败时，才会累加
                $failing = 0;
                $loopfailing = 0;
                
                $hitcounts = count($result["hits"]["hits"]);     
                $looptotal += $hitcounts;
                $roundtotal += $hitcounts;
//                $localminid = $result["hits"]["hits"][$hitcounts-1]["_source"]["lidsn"]+1;                
                
                $bulks = array();
                $starttime = time();
                $hitloop = 0;
                foreach($result["hits"]["hits"] as $hit){                    
                    if(empty($hit) || empty($hit["_source"]["lurl"]))continue;
                    $lid = $hit["_id"];

                    $hitloop++;
                    
//                    echo "\n$lid";
                    $res = $spider->crawlProduct("sold",$country, $hit["_source"]["lurl"]);
                    if(empty($res) || count($res)<=1)continue;
                    $t = time();
                    if($res["status"]===0 && isset($res['soldnum'])){
                        $bulkhead = $elm->getBulkParams($hit["_id"]);
                        $bulks['body'][] = array("update"=>$bulkhead);
                        $script = array(
                        //                                "inline"=>"def list=[params.country];if (list.contains(ctx._source.country)){}else{list.add(ctx._source.country)};ctx._source.country=list;",
                        //                                "inline"=>"ctx._source.country+=params.country",
                            "inline"=>"
                            if(ctx._source.containsKey('watchings') && ctx._source.watchings!=null){ctx._source.watchings.add(params.watchingobj);}else{ctx._source.watchings=[params.watchingobj];}
                            if(ctx._source.containsKey('solds') && ctx._source.solds!=null){ctx._source.solds.add(params.soldobj);}else{ctx._source.solds=[params.soldobj];}
                            ctx._source.updatetime=params.uptime;ctx._source.soldnum=params.soldnum;ctx._source.watchingnum=params.watchingnum;
                            ctx._source.lprice=params.lprice;ctx._source.revenue=params.revenue;ctx._source.lcurrency=params.lcurrency;",
                            "lang"=>"painless",
                            "params"=>array(
                                "t"=>"$t",
    //                             "watching" =>array(array("n"=>$res['watchingnum'],"t"=>$t)),
    //                             "soldlog"=>array(array("n"=>$res['soldnum'],"t"=>$t)),
                                "soldnum"=>$res['soldnum'],
                                "soldobj"=>array("t"=>$t,"v"=>$res['soldnum']),
                                "watchingnum"=>$res['watchingnum'],
                                "watchingobj"=>array("t"=>$t,"v"=>$res['watchingnum']),
                                "lprice"=>$res["lprice"],
                                "lcurrency"=>$res["lcurrency"],
                                "revenue"=>floatval($res["lprice"])*$res['soldnum'],
                                "uptime"=>time()
                            )
                        );
                        $bulks['body'][] = array(
                             "script"=>$script,
        //                    "doc"=>$res,
        //                    "upsert"=>$item
                        );  
                    }
                    
                    if($hitloop%$upsize==0 || $hitloop>=$hitcounts){     
                        $bulkres = $this->updateSoldBulks($elm, $bulks);
                        echo "\nsize:$size loop:$hitloop  taskid $taskid threadid $threadid size $size  lid $lid  bulk_counts ".$bulkres["bulkitems"]." bulk_errors ".$bulkres["bulkerror"]." stime:$starttime";
                        sleep(3);
                        $redis->decrBy($fromkey,$bulkres["bulkitems"]);//减去下一次抓取时的偏移量
                        unset($bulks);
                        $bulks = array();
                    }
                }
                //由于在循环内的各种continue和break，在循环内未完成bulk操作。那么在循环出来之后，还需要操作一次。
                if(!empty($bulks)){
                    $bulkres = $this->updateSoldBulks($elm, $bulks);
                    echo "\nsize:$size loop:$hitloop  taskid $taskid threadid $threadid size $size  lid $lid  bulk_counts ".$bulkres["bulkitems"]." bulk_errors ".$bulkres["bulkerror"]." stime:$starttime";
                    sleep(3);
                    $redis->decrBy($fromkey,$bulkres["bulkitems"]);
                }
                echo "\ncomplete round $size total $roundtotal time:".time();
//           }//while($localminid<$maxid){
//            echo "\ncomplete loop min $minid to max $maxid  total $looptotal  loopfailing $loopfailing time:".time();
        }
    }
    
    function updateSoldBulks( EbayListingEsModel $elm, $bulks ){
        $result=array("bulkitems"=>0,"bulkerror"=>1);
        try{
            if(count($bulks)>0){
                //                           echo "\nbulks:".json_encode($bulks);
                $responses = $elm->client->bulk($bulks);
                $result["bulkitems"] = count($responses["items"]);
                $result["bulkerror"]  = $responses["errors"];
                if($result["bulkerror"]){
                    echo "\r\n".json_encode($responses);
                }
            }
        }catch(\Exception $e){
            echo "\r\n".json_encode($bulks);
            echo "\r\n".$e->getMessage();
        }
        return $result;
    }
    
    function updateSellers( $sellers ){
        if(empty($sellers))return false;
        $bulks = array();
        $smodel = new EbaySellerEsModel();
        foreach($sellers as $seller){
            if(empty($seller))continue;
            $bulkhead= $smodel->getBulkParams($seller["sellerid"]);
            $bulks['body'][] = array("update"=>$bulkhead);
            $bulks['body'][] = array(
                "doc"=>array(),
                "upsert"=>$seller
            );
        }
        if(!empty($bulks)){
            try{
                $response = $smodel->client->bulk($bulks);
            }catch(\Exception $e){
                echo "\r\n".json_encode($bulks);
                echo "\r\n".$e->getMessage();
            }
        }
        
    }
    
    
    function crawlListingsFromSeller(array $argv){
        $tasktype = 1;
        $rundate = date("Y-m-d",time());
        $starttime = time();
        $taskid = $argv["subtaskid"];
        $threadid = $argv["threadsn"];
        $ctime = $argv["crawltime"];//time()-24*3600;//抓取的店铺要求是在这个时间之前更新过的
    
        $esm  = new EbaySellerEsModel();
        $spider = new EbaySpider();
        $elmodel = new EbayListingEsModel();
        $TLog = new EbayThreadLogEsModel();
    
        $country = $argv["country"];
        $start = $argv["start"];
        $total = $argv["total"];
        $size = $argv["size"];
        $loops = 0;
        $counts=0;
        //循环分类
        while($counts<$total){
            $failing = 0;
            $params = $esm->getParams();
            $params["_source"]=array("sellerid","listings");
            $params["from"]=$start;
            $params["size"]=$size;
            $params["body"]=array("query"=>array(
                "bool"=>array("filter"=>array(
 //                   array("range"=>array("listingupdatetime"=>array("lte"=>$ctime))),
                    array("term"=>array("crawler"=>1)),
                    array("term"=>array("listingupdatetime"=>0)),
                )))
            );
            $result = $esm->client->search($params);
            $itemnum = 0;
            echo "\r\ntotal: ".$result['hits']['total']." hits: ".count($result['hits']['hits']);
            if(!empty($result) && count($result['hits']['hits'])>0){
                $pround = 0;
                $totallistings = 0;
    
                //取出每个分类，然后分页获取商品数据
                foreach($result['hits']['hits'] as $hit){
                    $loops++;
                    $page = 1;
                    $listings = 0;
                    $nullops = 0;//没有获得数据，连续3次就退出
                    $oldcounts = 0;
                    $sellerid = $hit["_source"]["sellerid"];
                    
                    $year = date("Y",time());
                    $btime = time();
    
                    //分页获取产品数据,如果没有数据可抓了，置$page=0
                    while($page){
                        $res = $spider->fetchProductByStore($country, $sellerid,$page,  $year,  $btime);
                        //                       echo "\nres:::".json_encode($res);
                        if(empty($res) || empty($res["items"])){
                            $nullops++;
                            if($nullops>=4){
                                $failing++;
                                break;
                            }else{
                                continue;
                            }
                        }
                        
                        $year = $res["year"];
                        $btime = $res["beforetime"];
                        
                        $listings = $res["listings"];
                        $items = $res["items"];
                        $bulks = array();
                        $uptime = time();
                        foreach($items as $item){
                            if(empty($item) || !isset($item["lid"]))continue;
//                            $item["updatetime"]=$uptime;
                            $item["watchinglog"]=array();
                            $item["soldlog"]=array();
                            $item["lidsn"]=$item["lid"];
    
                            $bulkhead = $elmodel->getBulkParams($item["lid"]);
                            $bulks['body'][] = array("update"=>$bulkhead);
                            $script = array(
                            //                                "inline"=>"def list=[params.country];if (list.contains(ctx._source.country)){}else{list.add(ctx._source.country)};ctx._source.country=list;",
                            //                                "inline"=>"ctx._source.country+=params.country",
                            //                                "inline"=>"if(ctx._source.country.contains(params.country)==false){ctx._source.country.add(params.country)}ctx._source.updatetime=params.utime;",
                                "inline"=>'def clist=[params.country];
                                if(ctx._source.country  instanceof String)
                                {if(ctx._source.country!=params.country){clist.add(ctx._source.country);}ctx._source.country=clist;}
                                else{if(!ctx._source.country.contains(params.country)){ctx._source.country.add(params.country);}}
                                ctx._source.generationtime=params.generationtime;ctx._source.seller=params.seller;',
                                "lang"=>"painless",
                                "params"=>array(
                                    "country" =>$item['country'][0],
//                                    "utime"=>$uptime,
                                    "generationtime"=>$item['generationtime'],
                                    "seller"=>$item['seller'],
                                )
                            );
                            $bulks['body'][] = array(
                                "script"=>$script,
                                "upsert"=>$item
                            );
                            $itemnum++;
                        }
    
    
                        try{
                            $res_items = 0;
                            $res_error = 0;
                            if(count($bulks)>0){
                                //                           echo "\nbulks:".json_encode($bulks);
                                $responses = $elmodel->client->bulk($bulks);
                                $res_items = count($responses["items"]);
                                $res_error = $responses["errors"];
                                //第一次抓取时，此处无用。只在后续追加抓取时才需要
                                //                             if(!empty($responses) && !empty($responses['items'])){
                                //                                 foreach($responses['items'] as $item){
                                //                                     if($item["update"]["result"]!="created" && $item["update"]["_type"]==$ape->type){
                                //                                         $oldcounts++;
                                //                                     }
                                //                                 }
                                //                             }
                                //echo "\r\n".json_encode($responses);
                                //{"took":421,"errors":false,"items":[{"index":{"_index":"matrix_ali_1","_type":"aliproducts_1","_id":"32788003011","_version":1,"result":"created","_shards":{"total":2,"successful":1,"failed":0},"created":true,"status":201}},{"index":{"_index":"matrix_ali_1","_type":"aliproducts_1","_id":"32784685534","_version":1,"result":"created","_shards":{"total":2,"successful":1,"failed":0},"created":true,"status":201}},{"index":{"_index":"matrix_ali_1","_type":"aliproducts_1","_id":"32785432534","_version":1,"result":"created","_shards":{"total":2,"successful":1,"failed":0},"created":true,"status":201}},{"index":{"_index":"matrix_ali_1","_type":"aliproducts_1","_id":"32785450575","_version":1,"result":"created","_shards":{"total":2,"successful":1,"failed":0},"created":true,"status":201}},{"index":{"_index":"matrix_ali_1","_type":"aliproducts_1","_id":"32787491809","_version":1,"result":"created","_shards":{"total":2,"successful":1,"failed":0},"created":true,"status":201}},{"index":{"_index":"matrix_ali_1","_type":"aliproducts_1","_id":"32785352779","_version":1,"result":"created","_shards":{"total":2,"successful":1,"failed":0},"created":true,"status":201}},{"index":{"_index":"matrix_ali_1","_type":"aliproducts_1","_id":"32784685490","_version":1,"result":"created","_shards":{"total":2,"successful":1,"failed":0},"created":true,"status":201}},{"index":{"_index":"matrix_ali_1","_type":"aliproducts_1","_id":"32785396632","_version":1,"result":"created","_shards":{"total":2,"successful":1,"failed":0},"created":true,"status":201}},{"index":{"_index":"matrix_ali_1","_type":"aliproducts_1","_id":"32787571442","_version":1,"result":"created","_shards":{"total":2,"successful":1,"failed":0},"created":true,"status":201}},{"index":{"_index":"matrix_ali_1","_type":"aliproducts_1","_id":"32783324277","_version":1,"result":"created","_shards":{"total":2,"successful":1,"failed":0},"created":true,"status":201}},{"index":{"_index":"matrix_ali_1","_type":"aliproducts_1","_id":"32782774344","_version":1,"result":"created","_shards":{"total":2,"successful":1,"failed":0},"created":true,"status":201}},{"index":{"_index":"matrix_ali_1","_type":"aliproducts_1","_id":"32782037325","_version":1,"result":"created","_shards":{"total":2,"successful":1,"failed":0},"created":true,"status":201}},{"index":{"_index":"matrix_ali_1","_type":"aliproducts_1","_id":"32784843302","_version":1,"result":"created","_shards":{"total":2,"successful":1,"failed":0},"created":true,"status":201}},{"index":{"_index":"matrix_ali_1","_type":"aliproducts_1","_id":"32782021298","_version":1,"result":"created","_shards":{"total":2,"successful":1,"failed":0},"created":true,"status":201}},{"index":{"_index":"matrix_ali_1","_type":"aliproducts_1","_id":"32779434563","_version":1,"result":"created","_shards":{"total":2,"successful":1,"failed":0},"created":true,"status":201}},{"index":{"_index":"matrix_ali_1","_type":"aliproducts_1","_id":"32779007627","_version":1,"result":"created","_shards":{"total":2,"successful":1,"failed":0},"created":true,"status":201}},{"index":{"_index":"matrix_ali_1","_type":"aliproducts_1","_id":"32773564685","_version":1,"result":"created","_shards":{"total":2,"successful":1,"failed":0},"created":true,"status":201}},{"index":{"_index":"matrix_ali_1","_type":"aliproducts_1","_id":"32773634640","_version":1,"result":"created","_shards":{"total":2,"successful":1,"failed":0},"created":true,"status":201}},{"index":{"_index":"matrix_ali_1","_type":"aliproducts_1","_id":"32767417560","_version":5,"result":"updated","_shards":{"total":2,"successful":1,"failed":0},"created":false,"status":200}},{"index":{"_index":"matrix_ali_1","_type":"aliproducts_1","_id":"32765092251","_version":1,"result":"created","_shards":{"total":2,"successful":1,"failed":0},"created":true,"status":201}},{"index":{"_index":"matrix_ali_1","_type":"aliproducts_1","_id":"32764708095","_version":1,"result":"created","_shards":{"total":2,"successful":1,"failed":0},"created":true,"status":201}},{"index":{"_index":"matrix_ali_1","_type":"aliproducts_1","_id":"32764073936","_version":1,"result":"created","_shards":{"total":2,"successful":1,"failed":0},"created":true,"status":201}},{"index":{"_index":"matrix_ali_1","_type":"aliproducts_1","_id":"32767143484","_version":1,"result":"created","_shards":{"total":2,"successful":1,"failed":0},"created":true,"status":201}},{"index":{"_index":"matrix_ali_1","_type":"aliproducts_1","_id":"32764576984","_version":1,"result":"created","_shards":{"total":2,"successful":1,"failed":0},"created":true,"status":201}},{"index":{"_index":"matrix_ali_1","_type":"aliproducts_1","_id":"32764866803","_version":1,"result":"created","_shards":{"total":2,"successful":1,"failed":0},"created":true,"status":201}},{"index":{"_index":"matrix_ali_1","_type":"aliproducts_1","_id":"32767139411","_version":1,"result":"created","_shards":{"total":2,"successful":1,"failed":0},"created":true,"status":201}},{"index":{"_index":"matrix_ali_1","_type":"aliproducts_1","_id":"32764850826","_version":1,"result":"created","_shards":{"total":2,"successful":1,"failed":0},"created":true,"status":201}},{"index":{"_index":"matrix_ali_1","_type":"aliproducts_1","_id":"32764049856","_version":1,"result":"created","_shards":{"total":2,"successful":1,"failed":0},"created":true,"status":201}},{"index":{"_index":"matrix_ali_1","_type":"aliproducts_1","_id":"32764934262","_version":1,"result":"created","_shards":{"total":2,"successful":1,"failed":0},"created":true,"status":201}},{"index":{"_index":"matrix_ali_1","_type":"aliproducts_1","_id":"32764652420","_version":1,"result":"created","_shards":{"total":2,"successful":1,"failed":0},"created":true,"status":201}},{"index":{"_index":"matrix_ali_1","_type":"aliproducts_1","_id":"32764548859","_version":1,"result":"created","_shards":{"total":2,"successful":1,"failed":0},"created":true,"status":201}},{"index":{"_index":"matrix_ali_1","_type":"aliproducts_1","_id":"32766115394","_version":1,"result":"created","_shards":{"total":2,"successful":1,"failed":0},"created":true,"status":201}},{"index":{"_index":"matrix_ali_1","_type":"aliproducts_1","_id":"32766123318","_version":1,"result":"created","_shards":{"total":2,"successful":1,"failed":0},"created":true,"status":201}},{"index":{"_index":"matrix_ali_1","_type":"aliproducts_1","_id":"32766103279","_version":1,"result":"created","_shards":{"total":2,"successful":1,"failed":0},"created":true,"status":201}},{"index":{"_index":"matrix_ali_1","_type":"aliproducts_1","_id":"32760907290","_version":1,"result":"created","_shards":{"total":2,"successful":1,"failed":0},"created":true,"status":201}},{"index":{"_index":"matrix_ali_1","_type":"aliproducts_1","_id":"32751568714","_version":1,"result":"created","_shards":{"total":2,"successful":1,"failed":0},"created":true,"status":201}},{"index":{"_index":"matrix_ali_1","_type":"aliproducts_1","_id":"32747818187","_version":1,"result":"created","_shards":{"total":2,"successful":1,"failed":0},"created":true,"status":201}},{"index":{"_index":"matrix_ali_1","_type":"aliproducts_1","_id":"32747778076","_version":1,"result":"created","_shards":{"total":2,"successful":1,"failed":0},"created":true,"status":201}},{"index":{"_index":"matrix_ali_1","_type":"aliproducts_1","_id":"32742683348","_version":1,"result":"created","_shards":{"total":2,"successful":1,"failed":0},"created":true,"status":201}},{"index":{"_index":"matrix_ali_1","_type":"aliproducts_1","_id":"32740248316","_version":1,"result":"created","_shards":{"total":2,"successful":1,"failed":0},"created":true,"status":201}},{"index":{"_index":"matrix_ali_1","_type":"aliproducts_1","_id":"32737538364","_version":1,"result":"created","_shards":{"total":2,"successful":1,"failed":0},"created":true,"status":201}},{"index":{"_index":"matrix_ali_1","_type":"aliproducts_1","_id":"32739579494","_version":1,"result":"created","_shards":{"total":2,"successful":1,"failed":0},"created":true,"status":201}},{"index":{"_index":"matrix_ali_1","_type":"aliproducts_1","_id":"32737466946","_version":1,"result":"created","_shards":{"total":2,"successful":1,"failed":0},"created":true,"status":201}},{"index":{"_index":"matrix_ali_1","_type":"aliproducts_1","_id":"32739555633","_version":1,"result":"created","_shards":{"total":2,"successful":1,"failed":0},"created":true,"status":201}},{"index":{"_index":"matrix_ali_1","_type":"aliproducts_1","_id":"32739211486","_version":1,"result":"created","_shards":{"total":2,"successful":1,"failed":0},"created":true,"status":201}},{"index":{"_index":"matrix_ali_1","_type":"aliproducts_1","_id":"32735805316","_version":1,"result":"created","_shards":{"total":2,"successful":1,"failed":0},"created":true,"status":201}},{"index":{"_index":"matrix_ali_1","_type":"aliproducts_1","_id":"32732750587","_version":4,"result":"updated","_shards":{"total":2,"successful":1,"failed":0},"created":false,"status":200}},{"index":{"_index":"matrix_ali_1","_type":"aliproducts_1","_id":"32727497959","_version":1,"result":"created","_shards":{"total":2,"successful":1,"failed":0},"created":true,"status":201}}]}
                            }
                        }catch(\Exception $e){
                            echo "\r\n".json_encode($bulks);
                            echo "\r\n".$e->getMessage();
                        }
    
                        echo "\n$loops:  taskid $taskid  threadid $threadid country $country seller $sellerid   page $page listings $listings  bulk_counts $res_items bulk_errors $res_error mem[".memory_get_usage()."]";
    
                        $nullops = 0;
                        if($listings <= $res["count"]*$page || $oldcounts>$res["count"]*2){
                            $page = 0;
                        }else{
                            $page++;
                        }
                    }
                    $updoc = array(
                        "listingupdatetime"=>time(),
                    );
                    if($listings > $hit["_source"]["listings"]){
                         $updoc["listings"]=$listings;
                    }
                    $ecparams = $esm->getParams($hit["_source"]["sellerid"]);
                    $ecparams["body"]["doc"]=$updoc;
                    $esm->client->update($ecparams);
                }
                //每个分类
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
    
    function fetchListingWrapper($cmd, $country, $tasktotal, $tasksn, $taskid, $threadtotal, $threadsn=0,$crawltime ){
        $workers = array();
        if(empty($country)){
            $country = "US";
        }
        if(empty($tasktotal) || $tasksn>=$tasktotal || empty($taskid) ||empty($threadtotal)) return;
        $ecm = new EbayCategoryEsModel();
        
        if($cmd=="seller"){
            $ecm = new EbaySellerEsModel();
        }
        $filter = array(
            array("term"=>array("crawler"=>1))
        );
        if($cmd=="category"){
            $filter[] = array("term"=>array("leaf"=>1));
        }elseif($cmd=="seller"){
            $filter[] = array("term"=>array("listingupdatetime"=>0));
        }
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
            "size"=>200,
            "total"=>$threaditems,
            "crawltime"=>$crawltime
        );
        if($cmd=="category"){
            $this->crawlListingsFromCategory($argv);
        }elseif($cmd=="seller"){
            $argv["start"]= $tasksn*$threadtotal*1000 + $threadsn*1000;
            $this->crawlListingsFromSeller($argv);
        }
        
//         for($i=1;$i<=$threads;$i++){
//             $autoloader = require 'vendor/autoload.php';
//             $workers[$i]=new Listingworker($autoloader, $taskid, $tasksn,$i,$start,200,$threaditems);
//             $workers[$i]->start();
// //             $shellcmd = "cd /data/panda;nohup  /usr/local/php/bin/php WishProduct.php ".$thisminid[0]["id"]." $i  $thislimit  > /dev/null 2>&1 &";
// //             $results = shell_exec($shellcmd);
//             echo "\nstart thread: $i";
//         }
        
    }
    
    function fetchListingInfoWrapper2($cmd, $country, $tasktotal, $tasksn, $taskid, $threadtotal, $threadsn=0,$crawltime ){
        $workers = array();
        if(empty($country)){
            $country = "us";
        }
        $country = "us";
        if(empty($tasktotal) || $tasksn>=$tasktotal || empty($taskid) ||empty($threadtotal)) return;
        $elm = new EbayListingEsModel();
        $param = $elm->getParams();
        $filter = array(
            "must"=>array(array("term"=>array("lformat"=>"BUY")), ),
            "must_not"=>array("exists"=>array("field"=>"soldnum")),
//                     "should"=>array(
//                         array("bool"=>array(
//                             "must_not"=>array("exists"=>array("field"=>"soldnum"))
//                         )),
//                         array("range"=>array("updatetime"=>array("lt"=>$crawltime))),
//                     ),
        );
        if($cmd=="sold"){
            $soldtime = time()-2*3600;
            $filter = array("must"=>array(
//                 array("term"=>array("lid"=>"112304579749")),
                array("term"=>array("lformat"=>"BUY")),
                array("range"=>array("soldnum"=>array("gte"=>1))),
                array("range"=>array("updatetime"=>array("lt"=>$soldtime))),
                )
            );
        }
        $param["body"]=array(
            "query"=>array(
                "bool"=>$filter
            )
        );
        $result = $elm->client->search($param);
        if(empty($result) || empty($result["hits"]["total"])) return;
        $itemtotal = $result["hits"]["total"];
        //每个任务应该完成的数量
        $taskitems = intval( ceil( $itemtotal/$tasktotal ) );
        $taskstart = $tasksn * $taskitems;
        $threaditems =  intval( ceil( $taskitems/$threadtotal ) ) +1000;
        $threadstart = $taskstart + $threadsn*$threaditems;
        $from = $tasksn*$threadtotal*1000 + $threadsn*1000;       
        if($cmd=="info"){
            echo "\n---ListingInfo crawl->$country from $from total $threaditems";
            $this->fetchListingInfo($country, $from, 200, $threaditems);     
        }elseif($cmd=="sold"){
            echo "\n---ListingSold crawl->$country from $from total $threaditems";
            $this->fetchListingSold($country, $from, 10, $threaditems);
        }
    }
    
    function fetchListingInfoWrapper($cmd, $country, $tasktotal, $tasksn, $taskid, $threadtotal, $threadsn=0,$crawltime ){
        $workers = array();
        if(empty($country)){
            $country = "us";
        }
        $country = "us";
        if(empty($tasktotal) || $tasksn>=$tasktotal || empty($taskid) ||empty($threadtotal)) return;
        
        if($cmd=="info"){
            echo "\n---ListingInfo crawl->$country taskid $tasksn threadid $threadsn";
            $this->fetchListingInfo2($cmd, $country, 600, 200, $tasksn,$threadsn, $crawltime);
        }elseif($cmd=="sold"){
            echo "\n---ListingSold crawl->$country taskid $tasksn threadid $threadsn";
            $this->fetchListingSold($country, 1000, 200,  $tasksn,$threadsn, $crawltime);
        }
    }
}

// if(count($argv) >1){
//     $cmd = $argv[1];
//     if($cmd=="crawlListingsFromCategory"){
//         $argv = array(
//             "subtaskid"=>$argv[2],
//             "machineid"=>$argv[3],
//             "threadid"=>$argv[4],
//             "start"=>$argv[5],
//             "size"=>$argv[6],
//             "total"=>$argv[7],
//         );
//         $this->crawlListingsFromCategory($argv);
//     }
// }
?>