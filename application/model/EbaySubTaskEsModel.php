<?php
namespace app\model;
use app\model\CommonEsModel;

class EbaySubTaskEsModel extends CommonEsModel {
	public  $index = "panda_ebay_subtasks_1";
	public $type = "ebay_subtasks";
	
	function __construct($index=null,$type=null) {
	    if(empty($index))$index=$this->index;
	    if(empty($type))$type=$this->type;
	    parent::__construct( $index, $type);
	}
	
	/**
	 * 创建一个index，并且添加一个type和映射
	 */
	public function create_index_mapping(){
        //id="2017-03-01-任务类型（1-6）-机器编号（1-6）-子任务编号（1-10）
	    $indexParams['index'] = 'panda_ebay_subtasks_1';
	    $properties = array(
	        "machineid"=>array("type"=>"keyword"),
	        "ip"=>array("type"=>"keyword"),
	        "tasktype"=>array("type"=>"keyword"),//任务的类型编号，1：从分类抓取Listing，2：从店铺抓取Listing, 3.从商品列表抓取页面信息
	        "rundate"=>array("type"=>"keyword"),//"2017-01-01"
	        "total"=>array("type"=>"integer","null_value"=>0),//总抓取条数
	        "current"=>array("type"=>"integer","null_value"=>0),//已经完成数
	        "failing"=>array("type"=>"integer","null_value"=>0),//失败数
	        "threads"=>array("type"=>"short","null_value"=>0),//进程数
	        "starttime"=>array("type"=>"date"),//开始时间
	        "endtime"=>array("type"=>"date"),//完成时间，每次上报数据，都更新这个时间，到最后没有更新了，即最后完成时间
	        "endtime"=>array("type"=>"half_float","null_value"=>0.00,),
	        "endthreads"=>array("type"=>"short","null_value"=>0),//已经完成的进程数      
	    );
	    //新创建
	    $indexParams['body']['mappings']['ebay_subtasks'] = array(
	    //            "_id"=>array("path"=> "productid"),
	        "properties"=>$properties,
	    );
	    $result = $this->client->indices()->create($indexParams);
	
	    //后续增加
	    //         $indexParams['type'] = 'souq_products_1';
	    //        $indexParams['body']['souq_products_2'] = array(
	    //            "properties"=>$properties,
	    //        );
	    //        $result = $this->client->indices()->putMapping($indexParams);
	    return $result;
	}
}
?>