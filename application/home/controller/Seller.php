<?php
namespace app\home\controller;

use app\model\EbaySellerEsModel;
use app\model\EbayListingEsModel;
use app\ebay\EbayCategory;

class Seller extends BaseController
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
        $sort = "positivefeedback_1";
        $surl = $_SERVER['REQUEST_URI'];
        $sortid = $this->request->param("sort");
        if(empty($sortid)){
            $sortid = 7;
        }
        switch($sortid){
            case 1:
                $sort = "positivefeedback_1";
                break;
            case 2:
                $sort = "listings";
                break;
            case 3:
                $sort = "soldlistings";
                break;
            case 4:
                $sort = "hotlistings";
                break;
            case 5:
                $sort = "hotlistingrate";
                break;
            case 6:
                $sort = "soldnum7";
                break;
            case 7:
                $sort = "revenue7";
                break;
            case 8:
                $sort = "growthrate7";
                break;
            default:
                $sort = "positivefeedback_1";
        }        
        $this->assign("surl",$this->sortUrl("/home/seller/index.html"));
        
        $must_not = array();
        $filter = array(
//            array("range"=>array("slodlistings"=>array("gte"=>1))),
            array("terms"=>array("generationcountry"=>array("China","Hong Kong")))
        );
        $this->filterPost("seller", "seller", "string", "sellerid", $_GET, $surl, $filter, "term");
        $this->filterPost("min_s7", "max_s7", "int", "soldnum7", $_GET, $surl, $filter, "range");
        $this->filterPost("min_r7", "max_r7", "int", "revenue7", $_GET, $surl, $filter, "range");
        $variations = trim( $this->request->param("variations") );
//        echo "<br>++>$variations<++";
               
        $query = array();
        $location = $this->request->param("location");
        if(!empty($location)){
            $_GET["location"]=$location;
            $sqlkey = preg_replace("/\s+/",' ',$location);
            $query["must"][] = array("match"=>array("location"=>array("query"=>$sqlkey,"operator"=>"and")));
        }
        
        $elm = new EbaySellerEsModel();
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
        
        return $this->fetch();

    }
    
    /**
     * 店铺商品列表
     * @return mixed
     */
    function sellerListings(){
        $page = intval( $this->request->param("page") );
        if($page<=0){
            $page = 1;
        }
        if($page>=200){
            $page = 200;
        }
        
        $sellerid = $this->request->param("seller");
        if(empty($sellerid)){
            $sellerid = "sexycoolgirl";
        }
        
        $this->assign("sellerid",$sellerid);
        $sm = new EbaySellerEsModel();
        $params = $sm->getParams($sellerid);
        $sellerres = $sm->client->search();
        if(!empty($sellerres) && count($sellerres["hits"]["hits"])>0){
            $this->assign("seller",$sellerres["hits"]["hits"][0]);
        }
        
        $pagesize = 40;
        $sort = "soldnum";
        $surl = $_SERVER['REQUEST_URI'];
        $sortid = $this->request->param("sort");
        if(empty($sortid)){
            $sortid = 1;
        }
        switch($sortid){
            case 1:
                $sort = "soldnum";
                break;
            case 2:
                $sort = "lprice";
                break;
            case 3:
                $sort = "soldnum";
                break;
            case 4:
                $sort = "generationtime";
                break;
            default:
                $sort = "soldnum";
        }
        $this->assign("surl",$this->sortUrl("/home/seller/sellerListings.html"));
        
        $must_not = array();
        $filter = array(
            array("term"=>array("seller"=>$sellerid)),
        );
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
        
        
        $query = array();
        $location = $this->request->param("location");
        if(!empty($location)){
            $_GET["location"]=$location;
            $sqlkey = preg_replace("/\s+/",' ',$location);
            $query["must"][] = array("match"=>array("location"=>array("query"=>$sqlkey,"operator"=>"and")));
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
    
}
