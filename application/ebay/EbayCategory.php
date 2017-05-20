<?php
namespace app\ebay;
use app\ebay\EbaySpider;
use app\model\EbayCategoryEsModel;

class EbayCategory
{

    function __construct()
    {}

    function __destruct()
    {}
    
    /**
     * 抓取所有分类信息，存入到es库
     */
    function crawlEbayCategorys(){
        $spider = new EbaySpider();
        $headcats = $spider->getHeadCategory();
        $loop = 0;
        foreach($headcats as $hc){
            $loop++;
//            if($loop<53)continue;//本地处理到53了
            $curl = $hc['categoryurl'];
            echo "\n cgurl::".$curl;
            $ecs = $spider->fetchLeafCategorys($curl);
            if(empty($ecs))continue;
            $ecm = new EbayCategoryEsModel();
            $bulks = array("body"=>array());
            foreach($ecs as $k=>$v){
                $bulks["body"][] = array("index"=>$ecm->getBulkParams($v["categoryid"]));
                $bulks["body"][] = array(
                    "categoryid"=>$v["categoryid"],
                    "pid"=>$v["pid"],
                    "ebaycategoryid"=>$v["categoryid"],
                    "ebaycategorypid"=>$v["pid"],
                    "categoryname"=>$v["categoryname"],
                    "categorys"=>$v["categorys"],
                    "categoryurl"=>$v["categoryurl"],
                    "level"=>$v["level"],
                    "leaf"=>$v["leaf"],
                    "listings"=>$v["listings"],    
                    "crawler"=>$v["crawler"], 
                );                
            }
            try{
                $res = $ecm->client->bulk( $bulks );
            }catch(\Exception $e){
                echo "\nbulk:".json_encode($bulks);
                echo "\nmessage:".$e->getMessage();
            }
            echo "\r\n$loop: counts:".count($res["items"])."  errors:".$res["errors"]." mem[".memory_get_usage()."]";
            sleep(1);
        }
    }
    
    function tagCategory(){
        require_once  'vendor/autoload.php';
        $filename = 'D:\phpworkspace\panda\category.xlsx';
        $objPHPExcelReader = \PHPExcel_IOFactory::load($filename);  //加载excel文件
        $tags = array();
        foreach($objPHPExcelReader->getWorksheetIterator() as $sheet)  //循环读取sheet
        {
            foreach($sheet->getRowIterator() as $row)  //逐行处理
            {
                if($row->getRowIndex()<1)  //确定从哪一行开始读取
                {
                    continue;
                }
                foreach($row->getCellIterator() as $cell)  //逐列读取
                {
                    $data = $cell->getValue(); //获取cell中数据
                    $tags[$data]=1;

//                     $ecm = new EbayCategoryEsModel();
//                     $sparams = $ecm->getParams();
//                     $sparams["from"] =0;
//                     $sparams["size"] = 10;
//                     $sparams["body"]=array("query"=>array(
//                "bool"=>array("filter"=>array(
//                    array("term"=>array("categoryname"=>$data)),
// //                   array("range"=>array("updatetime"=>array("lte"=>time()-24*3600)))
// //                    array("term"=>array("productid"=>"32728437130"))
//                )))
//             );echo json_encode($sparams);
//                     $res = $ecm->client->search($sparams);
//                     echo "\n".json_encode($res);exit;
//                     if(!empty($res) && count($res['hits']['hits'])>0){
//                         foreach($res['hits']['hits'] as $hit){
//                             if($hit["_source"]["categoryname"]==$data){
//                                 $uparams = $ecm->getParams($hit["_id"]);
//                                 $uparams["body"]["doc"]=array(
//                                     "crawler"=>1
//                                 );
//                                 $ecm->client->update($uparams);
//                             }
//                         }
//                     }
//                     echo "\n".$data;
                }
                
            }
        }
        echo "\n".json_encode($tags);
    }
    
    /**
     * 导出json文档，供应前端使用
     * @return [cgmaps,crawlcgs],cgmaps:cid->cname;crawlcgs:cid->[name,[cid:name]]
     */
    function exportCatgorys(){
        $ecmodel = new EbayCategoryEsModel();
        $params = $ecmodel->getParams();
        $params["_source"]=array("categoryid","categoryname","categoryurl","pid","crawler");
        $params["from"]=0;
        $params["size"]=500;
        $params["body"]=array("query"=>array(
            "bool"=>array("should"=>array(
                array("term"=>array("level"=>1)),
                array("term"=>array("level"=>2))
            )))
        );
        $result = $ecmodel->client->search($params);
        $cgs = $result["hits"]["hits"];
        $cgmap = [];
        foreach($cgs as $cgsouce){
            $cg = $cgsouce["_source"];
            unset($cg["pid"]);
            unset($cg["crawler"]);
            $cgmap[$cg["categoryid"]]=$cg;
        }
        $crawlcgs = array("1"=>["name"=>"全部","items"=>["2"=>"全部"]]);
        foreach($cgs as $cg){
            if($cg["_source"]["crawler"]!==1)continue;
            $pid = $cg["_source"]["pid"];
            $items = ["2"=>"全部"];
            if(isset($crawlcgs[$pid])){
                $items = $crawlcgs[$pid]["items"];
            }else{
                $crawlcgs[$pid] = ["name"=>str_replace("'","‘",$cgmap[$pid]["categoryname"]),"items"=>[]];
            }
            $items[ $cg["_source"]["categoryid"] ] = str_replace("'","‘",$cg["_source"]["categoryname"]);
            $crawlcgs[$pid]["items"] = $items;
        }
        $rcs = [
            "cgmaps"=>$cgmap,
            "crawlcgs"=>$crawlcgs
        ];
//        echo json_encode($rcs);
        return $rcs;
    }
}

?>