<?php
namespace app\model;
use app\model\CommonEsModel;

class EbaySellerEsModel extends CommonEsModel {
	public  $index = "panda_ebay_sellers_1";
	public $type = "ebay_sellers";
	
	function __construct($index=null,$type=null) {
	    if(empty($index))$index=$this->index;
	    if(empty($type))$type=$this->type;
	    parent::__construct( $index, $type);
	}
	
	/**
	 * 创建一个index，并且添加一个type和映射
	 */
	public function create_index_mapping(){
	
	    $indexParams['index'] = $this->index;
	    $properties = array(
	        "sellerid"=>array("type"=>"keyword"),
	        "sellerpage"=>array("type"=>"binary"),
	        "listings"=>array("type"=>"integer","null_value"=>0,),
	        "updatetime"=>array("type"=>"integer","null_value"=>0,),
	        "listingupdatetime"=>array("type"=>"integer","null_value"=>0,),
	        "crawler"=>array("type"=>"short","null_value"=>0,),//是否抓取
	        
	        "feedbackscore"=>array("type"=>"integer","null_value"=>0,),
	        "positivefeedbackrate"=>array("type"=>"integer","null_value"=>0,),
	        "generationtime"=>array("type"=>"integer","null_value"=>0,),
	        "generationcountry"=>array("type"=>"keyword"),
	        "positivefeedbacks"=>array("type"=>"nested","properties"=>array(
	            "t"=>array("type"=>"integer","null_value"=>0),
	            "pf"=>array("type"=>"integer","null_value"=>0),
	        )),
	        "positivefeedback_1"=>array("type"=>"integer","null_value"=>0,),
	        "positivefeedback_6"=>array("type"=>"integer","null_value"=>0,),
	        "positivefeedback_12"=>array("type"=>"integer","null_value"=>0,),
	    );
	    //新创建
// 	    $indexParams['body']['mappings'][$this->type] = array(
// 	    //            "_id"=>array("path"=> "productid"),
// 	        "properties"=>$properties,
// 	    );
// 	    $result = $this->client->indices()->create($indexParams);
	
	    //后续增加
            $indexParams['type'] = $this->type;
           $indexParams['body'][$this->type] = array(
               "properties"=>$properties,
           );
           $result = $this->client->indices()->putMapping($indexParams);
	    return $result;
	}
}
?>