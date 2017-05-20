<?php
namespace app\home\controller;

use app\model\EbayCategoryEsModel;
use app\ebay\EbayCategory;

class Category extends BaseController
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
            $sortid = 5;
        }
        switch($sortid){
            case 1:
                $sort = "listings";
                break;
            case 2:
                $sort = "soldlistings";
                break;
            case 3:
                $sort = "soldrate";
                break;
            case 4:
                $sort = "avgprice";
                break;
            case 5:
                $sort = "soldnum7";
                break;
            case 6:
                $sort = "revenue7";
                break;
            case 7:
                $sort = "growthrate";
                break;
            default:
                $sort = "listings";
        }        
        $this->assign("surl",$this->sortUrl("/home/category/index.html"));
        
        $must_not = array();
        $filter = array(
            array("term"=>array("level"=>2)),
        );
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
        
        $elm = new EbayCategoryEsModel();
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
