<?php
namespace app\home\controller;

use think\Controller;
use think\paginator\driver\Bootstrap;

class BaseController extends Controller
{

    function __construct()
    {
        parent::__construct();
    }

    function __destruct()
    {}
    
    //分页
    protected function page($items, $Total_Size = 1, $Page_Size = 0, $Current_Page = 1, $options=[]) {
        $path = Bootstrap::getCurrentPath();
        //        $path = Request::instance()->url();
        $options["path"]=$path;
        $page = Bootstrap::make($items, $Page_Size, $Current_Page, $Total_Size,false,$options);
        // 获取分页显示
        return $page->render();
    }
    
    protected function sortUrl($selfurl=null){
        //去除url中的sort字段，因为在页面会重组
        $sortparams = $this->request->param();
        if(isset($sortparams["sort"])){
            unset($sortparams["sort"]);
        }
        if(isset($sortparams["page"])){
            unset($sortparams["page"]);
        }
    
        $sortquery = "";
        foreach($sortparams as $k=>$sp){
            if(empty($sp)) continue;
            if(empty($sortquery)){
                $sortquery .= "$k=$sp";
            }else{
                $sortquery .= "&$k=$sp";
            }
        }
    
        $surl = $_SERVER['PHP_SELF'];
        if(!empty($selfurl)){
            $surl = $selfurl;
        }
        if(empty($sortquery)){
            $surl .= "?";
        }else{//有可能最后一个是问号。就不用加&了
            $surl .= "?$sortquery&";
        }
        return $surl;
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
                    $fcase["gte"]=$start;
                }
                if($end>=0 ){
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

?>