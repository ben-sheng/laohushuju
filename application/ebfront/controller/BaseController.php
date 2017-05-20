<?php
namespace app\ebfront\controller;

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
    
    protected function sortUrl(){
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
        if(empty($sortquery)){
            $surl .= "?";
        }else{//有可能最后一个是问号。就不用加&了
            $surl .= "?$sortquery&";
        }
        return $surl;
    }
}

?>