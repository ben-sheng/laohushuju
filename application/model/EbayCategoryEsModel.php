<?php
namespace app\model;
use app\model\CommonEsModel;

class EbayCategoryEsModel extends CommonEsModel {
	public  $index = "panda_ebay_categorys_2";
	public $type = "ebay_categorys";
	
	function __construct($index=null,$type=null) {
	    if(empty($index))$index=$this->index;
	    if(empty($type))$type=$this->type;
	    parent::__construct( $index, $type);
	}
	
	/**
	 * 创建一个index，并且添加一个type和映射
	 */
	public function create_index_mapping(){
	
	    $indexParams['index'] = 'panda_ebay_categorys_1';
	    $properties = array(
	        "categoryid"=>array("type"=>"text"),
	        "pid"=>array("type"=>"text"),
	        "ebaycategoryid"=>array("type"=>"keyword"),
	        "ebaycategorypid"=>array("type"=>"keyword"),
	        "categoryname"=>array("type"=>"text"),
	        "categoryurl"=>array("type"=>"keyword"),
	        "level"=>array("type"=>"short","null_value"=>0,),
	        "leaf"=>array("type"=>"short","null_value"=>0,),
	        "listings"=>array("type"=>"integer","null_value"=>0,),
	        "soldlistings"=>array("type"=>"integer","null_value"=>0,),
	        "avgprice"=>array("type"=>"half_float","null_value"=>0.00,),
	        "revenue7"=>array("type"=>"double","null_value"=>0),
	        "soldnum7"=>array("type"=>"integer","null_value"=>0),
	        "soldnum15"=>array("type"=>"integer","null_value"=>0),
	        "growthrate"=>array("type"=>"short","null_value"=>0),
	        "crawler"=>array("type"=>"short","null_value"=>0,),//是否抓取
	    );
	    //新创建
// 	    $indexParams['body']['mappings']['ebay_categorys'] = array(
// 	    //            "_id"=>array("path"=> "productid"),
// 	        "properties"=>$properties,
// 	    );
// 	    $result = $this->client->indices()->create($indexParams);
	
	    //后续增加
            $indexParams['type'] = 'ebay_categorys';
           $indexParams['body']['ebay_categorys'] = array(
               "properties"=>$properties,
           );
           $result = $this->client->indices()->putMapping($indexParams);
	    return $result;
	}
	
	/**
	 * 创建一个index，并且添加一个type和映射
	 */
	public function create_index_mapping_v2(){
	
	    $indexParams['index'] = 'panda_ebay_categorys_2';
	    $properties = array(
	        "categoryid"=>array("type"=>"keyword"),
	        "pid"=>array("type"=>"keyword"),
	        "ebaycategoryid"=>array("type"=>"keyword"),
	        "ebaycategorypid"=>array("type"=>"keyword"),
	        "categoryname"=>array("type"=>"keyword"),
	        "categoryurl"=>array("type"=>"keyword"),
	        "categorys"=>array("type"=>"keyword"),
	        "level"=>array("type"=>"short","null_value"=>0,),
	        "leaf"=>array("type"=>"short","null_value"=>0,),
	        "listings"=>array("type"=>"integer","null_value"=>0,),
	        "soldlistings"=>array("type"=>"integer","null_value"=>0,),
	        "avgprice"=>array("type"=>"half_float","null_value"=>0.00,),
	        "revenue7"=>array("type"=>"double","null_value"=>0),
	        "soldnum7"=>array("type"=>"integer","null_value"=>0),
	        "soldnum15"=>array("type"=>"integer","null_value"=>0),
	        "growthrate"=>array("type"=>"short","null_value"=>0),
	        "updatetime"=>array("type"=>"long","null_value"=>0),
	        "stattime"=>array("type"=>"long","null_value"=>0),
	        "crawler"=>array("type"=>"short","null_value"=>0,),//是否抓取
	    );
	    //新创建
// 	    $indexParams['body']['mappings']['ebay_categorys'] = array(
// 	    //            "_id"=>array("path"=> "productid"),
// 	        "properties"=>$properties,
// 	    );
// 	    $result = $this->client->indices()->create($indexParams);
	
	    //后续增加
	    $indexParams['type'] = 'ebay_categorys';
	    $indexParams['body']['ebay_categorys'] = array(
	        "properties"=>$properties,
	    );
	    $result = $this->client->indices()->putMapping($indexParams);

	    return $result;
	}
}
?>