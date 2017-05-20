<?php
namespace app\home\controller;

use app\model\EbayListingEsModel;
use app\ebay\EbayCategory;
class Index extends BaseController
{
    
    public function _initialize()
    {
        if(!is_login()){
            $this->success('登录以后才能访问!',url('User/login'));
            return;
        }
    }
    
public function index()
    {
        $page = intval( $this->request->param("page") );        
        if($page<=0){
            $page = 1;
        }
        if($page>=200){
            $page = 200;
        }
        
        $pagesize = 40;
        $sort = "soldnum";
        $surl = $_SERVER['REQUEST_URI'];
        $sortid = $this->request->param("sort");
        if(empty($sortid)){
            $sortid = 5;
        }
        switch($sortid){
            case 1:
                $sort = "soldnum";
                break;
            case 2:
                $sort = "lprice";
                break;
            case 3:
                $sort = "revenue";
                break;
            case 4:
                $sort = "generationtime";
                break;
            case 5:
                $sort = "soldnum7";
                break;
            case 6:
                $sort = "revenue7";
                break;
            case 7:
                $sort = "growthrate7";
                break;
            default:
                $sort = "soldnum";
        }        
        $this->assign("surl",$this->sortUrl());
        
        $must_not = array();
        $filter = array(
            array("range"=>array("soldnum"=>array("gte"=>1))),
            array("match"=>array("location"=>"China,Hong Kong")),
        );
        $this->filterPost("seller", "seller", "string", "seller", $_GET, $surl, $filter, "term");
        $this->filterPost("lid", "lid", "string", "lid", $_GET, $surl, $filter, "term");
        $this->filterPost("minweek", "maxweek", "int", "soldnum7", $_GET, $surl, $filter, "range");
        $this->filterPost("minrevenue", "maxrevenue", "float", "revenue7", $_GET, $surl, $filter, "range");
        $this->filterPost("mingt", "maxgt", "date", "generationtime", $_GET, $surl, $filter, "range");
        $this->filterPost("country", "country", "string", "country", $_GET, $surl, $filter, "term");
        $this->filterPost("minprice", "maxprice", "int", "lprice", $_GET, $surl, $filter, "range");
        $variations = trim( $this->request->param("variations") );
//        echo "<br>++>$variations<++";
        if($variations==='y' || $variations==='n'){
            $_GET["variations"] = $variations;
            $inline = "doc['variations'].value!='[]'  && doc['variations'].value!=null";
            if($variations==='n'){
                $inline = "doc['variations'].value=='[]'  || doc['variations'].value==null";
            }
            $script = array(
                "script"=>array(
                    "inline"=>$inline,
                    "lang"=>"painless"
                )
            );
            $filter[]=array("script"=>$script);
        }
        
        $cg1 = trim( $this->request->param("cg1") );
        $cg2 = trim( $this->request->param("cg2") );
        //第一次都为全部
        if(empty($cg1) || (intval($cg1)===1 && intval($cg2)===2)){
            $_GET["cg1"] = 1;
            $_GET["cg2"] = 2;
        }else{
            $_GET["cg1"] = $cg1;
            
            if(intval($cg2)>2){//如果2级被选择了，则只查第二级的
                $this->filterPost("cg2", "", "int", "categorys", $_GET, $surl, $filter, "term");
            }else{//如果2级没有被选择，则查第一级的
                $_GET["cg2"] = 2;
                $this->filterPost("cg1", "", "int", "categorys", $_GET, $surl, $filter, "term");
            }
        }
        
        $query = array();
        $location = $this->request->param("location");
        if(!empty($location)){
            $_GET["location"]=$location;
            $sqlkey = preg_replace("/\s+/",' ',$location);
            $query["must"][] = array("match"=>array("location"=>array("query"=>$sqlkey,"operator"=>"and")));
        }
        $keyword = $this->request->param("keyword");
        if(!empty($keyword)){
            $_GET["keyword"]=$keyword;
            $sqlkey = preg_replace("/\s+/",' ',$keyword);
            $query["must"][] = array("match"=>array("ltitle"=>array("query"=>$sqlkey,"operator"=>"and")));
        }
        
        $elm = new EbayListingEsModel();
        $sparams = $elm->getParams();
        $sparams["size"] = $pagesize;
        $sparams["from"] = ($page-1)*$pagesize;
        $sparams['body']['sort'] = array($sort=>array("order"=>"desc"));
        if(!empty($query)){
            $sparams['body']["query"]["bool"]=$query;
        }
        if(!empty($filter)){
            $sparams['body']["query"]["bool"]["filter"]=$filter;
        }
        if(!empty($must_not)){
            $sparams['body']["query"]["bool"]["must_not"]=$must_not;
        }
//        echo json_encode($sparams);
        
        $result = $elm->client->search($sparams);
        $list = $result["hits"]["hits"];
        $count = count($list);
        $total = $result["hits"]["total"];
        
        $this->assign('list', $list);
        $this->assign('count', $count);
        $this->assign('total', $result["hits"]["total"]);
        $this->assign("formget",$_GET);
        
        $params = $this->request->param();
        $page = $this->page($list, $total,$pagesize, $page, array("query"=>$params));

        $this->assign('_page', $page);
        
        //分类信息，供前端使用
        $ec = new EbayCategory();
        $ecs = $ec->exportCatgorys();
        $this->assign("ecs",$ecs);       
        
        return $this->fetch();

    }
    
    function filterPost($start_key,$end_key,$keytype, $filterkey, &$gets, &$surl, &$filter, $filtertype="term"){
        if(empty($filterkey) || $filter===null)return;
        if($filtertype==="range"){
            $start = -1;
            //	    echo "<br>start:-->".$_REQUEST["start_boughter"]."<--ene::".$_REQUEST["end_boughter"];//exit;
            if($_REQUEST["$start_key"]===0 || $_REQUEST["$start_key"]>0 || strlen(trim($_REQUEST[$start_key]))>0){
                $start = 0;
                if($keytype=="date"){
                    $d = trim($_REQUEST[$start_key]);
                    $start = strtotime($d);
                }elseif($keytype=="float"){
                    $start = floatval($_REQUEST[$start_key]);
                }elseif($keytype=="int"){
                    $start = intval($_REQUEST[$start_key]);
                }else{
                    $start = trim($_REQUEST[$start_key]);
                }
                $gets[$start_key] = trim($_REQUEST[$start_key]);
            }
    
            $end = -1;
            if($_REQUEST[$end_key]===0 || intval($_REQUEST[$end_key])>0 || strlen(trim($_REQUEST[$end_key]))>0){
                $end= 0;
                if($keytype=="date"){
                    $d = trim($_REQUEST[$end_key]);
                    $end = strtotime($d);
                }elseif($keytype=="float"){
                    $end = floatval($_REQUEST[$end_key]);
                }elseif($keytype=="int"){
                    $end = intval($_REQUEST[$end_key]);
                }else{
                    $end = trim($_REQUEST[$end_key]);
                }
                $gets[$end_key] = trim($_REQUEST[$end_key]);
            }
            if($start>=0 || $end>=0){
                $fcase = array();
                if($start>=0 ){
                    if($start_key=="hy_start") $start = $start*100;//海鹰指数在db中是以int值存储的（*100）
                    $fcase["gte"]=$start;
                }
                if($end>=0 ){
                    if($end_key=="hy_end") $end = $end*100;
                    $fcase["lte"]=$end;
                }
                $filter[] = array('range'=>array($filterkey=>$fcase));
            }
        }else{
            $v = trim( $_REQUEST[$start_key] );
            if(!empty($v)){
                $gets[$start_key] = $v;
                $filter[] = array('term'=>array($filterkey=>$v));
            }
        }
    }
}
