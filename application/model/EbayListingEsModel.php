<?php
namespace app\model;
use app\model\CommonEsModel;

class EbayListingEsModel extends CommonEsModel {
	public  $index = "panda_ebay_listings_1";
	public $type = "ebay_listings";
	
	function __construct($index=null,$type=null) {
	    if(empty($index))$index=$this->index;
	    if(empty($type))$type=$this->type;
	    parent::__construct( $index, $type);
	}
	
	/**
	 * 创建一个index，并且添加一个type和映射
	 */
	public function create_index_mapping(){
	
	    $indexParams['index'] = 'panda_ebay_listings_1';
	    $properties = array(
	        "categorys"=>array("type"=>"keyword"),
	        "lid"=>array("type"=>"keyword"),	        
	        "ended"=>array("type"=>"short","null_value"=>0),
	        "status"=>array("type"=>"short","null_value"=>0),//0健康
	        "ltitle"=>array("type"=>"text"),
	        "lurl"=>array("type"=>"keyword"),
	        "lthumb"=>array("type"=>"keyword"),
	        "mainimg"=>array("type"=>"keyword"),
	        "imgs"=>array("type"=>"keyword"),
	        "seller"=>array("type"=>"keyword"),
	        "sellerpage"=>array("type"=>"keyword"),
	        "storepage"=>array("type"=>"keyword"),
	        "specifics"=>array("type"=>"binary"),
	        "lcurrency"=>array("type"=>"keyword"),
	        "lprice"=>array("type"=>"half_float","null_value"=>0.00,),
	        "lorgprice"=>array("type"=>"half_float","null_value"=>0.00,),
	        "lformat"=>array("type"=>"keyword"),
	        "country"=>array("type"=>"keyword"),
	        "updatetime"=>array("type"=>"integer","null_value"=>0),	       
	        "generationtime "=>array("type"=>"long","null_value"=>0),
	        "shippingto"=>array("type"=>"keyword"),
	        "location"=>array("type"=>"text"),
	        "excludes"=>array("type"=>"keyword"),
	        "soldnum"=>array("type"=>"integer","null_value"=>0),
	        "watchingnum"=>array("type"=>"integer","null_value"=>0),
	        "watchinglog"=>array("type"=>"nested","properties"=>array(
	            "t"=>array("type"=>"integer","null_value"=>0),
                "n"=>array("type"=>"integer","null_value"=>0),  
            )),
	        "soldlog"=>array("type"=>"nested","properties"=>array(
	            "t"=>array("type"=>"integer","null_value"=>0),
	            "n"=>array("type"=>"integer","null_value"=>0),
	        )),
	        "variations2"=>array("type"=>"binary"),
	        "variations"=>array("type"=>"keyword"),
	        "lidsn"=>array("type"=>"long","null_value"=>0),
	        "revenue"=>array("type"=>"double","null_value"=>0),//lprice*soldnum
	        "solds"=>array("type"=>"object"),
	        "watchings"=>array("type"=>"object"),
	        "revenue7"=>array("type"=>"double","null_value"=>0),
	        "soldnum7"=>array("type"=>"integer","null_value"=>0),
	        "soldnum15"=>array("type"=>"integer","null_value"=>0),
	        "growthrate"=>array("type"=>"short","null_value"=>0),
	        "stattime "=>array("type"=>"long","null_value"=>0),
	        
	        //update nested:
	        /**
	         * {
  "script": "if (ctx._source[\"watchinglog\"] == null) { ctx._source.watchinglog = watching } else { 
ctx._source.watchinglog += watching }",
  "params": {
    "watching": [ "t": 123, "n": "boom" ]
  }
}
	         */
	    );
	    //新创建
// 	    $indexParams['body']['mappings']['ebay_listings'] = array(
// 	    //            "_id"=>array("path"=> "productid"),
// 	        "properties"=>$properties,
// 	    );
// 	    $result = $this->client->indices()->create($indexParams);
	
	    //后续增加
	            $indexParams['type'] = 'ebay_listings';
	           $indexParams['body']['ebay_listings'] = array(
	               "properties"=>$properties,
	           );
	           $result = $this->client->indices()->putMapping($indexParams);
	    return $result;
	}
}
?>