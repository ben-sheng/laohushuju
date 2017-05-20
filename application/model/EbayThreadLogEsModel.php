<?php
namespace app\model;
use app\model\CommonEsModel;

class EbayThreadLogEsModel extends CommonEsModel {
	public  $index = "panda_ebay_threadlogs_1";
	public $type = "ebay_threadlogs";
	
	function __construct($index=null,$type=null) {
	    if(empty($index))$index=$this->index;
	    if(empty($type))$type=$this->type;
	    parent::__construct( $index, $type);
	}
	
	/**
	 * 创建一个index，并且添加一个type和映射
	 */
	public function create_index_mapping(){
        //id=任意编号
	    $indexParams['index'] = 'panda_ebay_threadlogs_1';
	    $properties = array(
	        "subtaskid"=>array("type"=>"keyword"),
	        "machineid"=>array("type"=>"keyword"),
	        "ip"=>array("type"=>"keyword"),
	        "rundate"=>array("type"=>"keyword"),//"2017-01-01"
	        "tasktype"=>array("type"=>"keyword"),//任务的类型编号，1：从分类抓取Listing，2：从店铺抓取Listing, 3.从商品列表抓取页面信息
	        "total"=>array("type"=>"integer","null_value"=>0),//总抓取条数
	        "complete"=>array("type"=>"integer","null_value"=>0),//本次记录完成条数
	        "failing"=>array("type"=>"integer","null_value"=>0),//失败数
	        "threadid"=>array("type"=>"short","null_value"=>0),//进程ID,自己指定
	        "starttime"=>array("type"=>"date"),//开始时间
	        "currenttime"=>array("type"=>"date"),//当前记录时间
	        "v1"=>array("type"=>"text"),//其它信息
	    );
	    //新创建
	    $indexParams['body']['mappings']['ebay_threadlogs'] = array(
	    //            "_id"=>array("path"=> "productid"),
	        "properties"=>$properties,
	    );
	    $result = $this->client->indices()->create($indexParams);
	
	    //后续增加
//         $indexParams['type'] = 'ebay_threadlogs';
//        $indexParams['body']['ebay_threadlogs'] = array(
//            "properties"=>$properties,
//        );
//        $result = $this->client->indices()->putMapping($indexParams);
	    return $result;
	}
	
	/**
	 * 记录日志 
	 * @param unknown $logdata=array(subtaskid,machineid,rundate,tasktype,total,complete,failing,threadid,starttime)
	 */
	function log( $logdata ){
	    if(!isset($logdata["subtaskid"]) || !isset($logdata["complete"])) return false;
	    
	    if(!isset($logdata["currenttime"])){
	       $logdata["currenttime"] = time();
	    }
	    if(!isset($logdata["ip"])){
	        $logdata["ip"] = gethostbyname(exec('hostname'));
	    }
	    
	    $params = $this->getParams();
	    $params["body"] = $logdata;
	    $response = $this->client->index($params);
	    return true;
	}
}
?>