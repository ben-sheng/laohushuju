<?php
namespace app\model;
use app\model\CommonEsModel;

class WishStatEsModel extends CommonEsModel {
	public  $index = "matrix_es_1";
	public $type = "wishstat_1";
	
	function __construct($index=null,$type=null) {
	    if(empty($index))$index=$this->index;
	    if(empty($type))$type=$this->type;
	    parent::__construct( $index, $type);
	}
	
	/**
	 * 创建一个index，并且添加一个type和映射
	 */
	public function create_index_mapping(){
	
	    $indexParams['index'] = 'panda_category_1';
	    //        $indexParams['type'] = 'wishstat_1';
	    //       $indexParams['body']['xxxx']=0;
	    //         $indexParams['body']['settings']['number_of_shards'] = 2;
	    //         $indexParams['body']['settings']['number_of_replicas'] = 0;
	    $properties = array(
	        "productid"=>array("type"=>"string","index"=>"not_analyzed"),
	        "uuid"=>array("type"=>"string","index"=>"not_analyzed"),
	        "categoryid"=>array("type"=>"string","index"=>"not_analyzed"),
	        "productname"=>array("type"=>"string"),
	        "producturl"=>array('type'=>"string","index"=>"not_analyzed"),
	        "img"=>array("type"=>"string","index"=>"not_analyzed"),
	        "stock"=>array("type"=>"short","null_value"=>1),
	        "status"=>array("type"=>"short","null_value"=>0),
	        "price"=>array("type"=>"float","null_value"=>0.0),
	        "oriprice"=>array("type"=>"float","null_value"=>0.0),
	        "brand"=>array("type"=>"string","index"=>"not_analyzed"),
	        "categoryname"=>array("type"=>"string","index"=>"not_analyzed"),
	        "reviewcount"=>array("type"=>"short","null_value"=>0,),
	        "reviewlasttime"=>array("type"=>"integer","null_value"=>0,),
	        "rating"=>array("type"=>"float","null_value"=>0.0),
	        "ratingcount"=>array("type"=>"short","null_value"=>0),
	        "sizes"=>array("type"=>"string","index"=>"not_analyzed"),
	        "colors"=>array("type"=>"string","index"=>"not_analyzed"),
	        "specifications"=>array("type"=>"string","index"=>"no"),
	        "description"=>array("type"=>"string","index"=>"no"),
	        "merchant"=>array("type"=>"string","index"=>"not_analyzed"),
	        "content"=>array("type"=>"string","index"=>"no"),
	        //http://106.186.120.253/preview/nested-mapping.html
	        "reviews"=>array("type"=>"nested","properties"=>array(
	            "author"=>array("type"=>"string","index"=>"not_analyzed"),
	            "reviewtime"=>array("type"=>"long","null_value"=>0,),
	            "star"=>array("type"=>"short","null_value"=>0),
	        ))
	    );
	    //新创建
	    $indexParams['body']['mappings']['souq_products_1'] = array(
	    //            "_id"=>array("path"=> "productid"),
	        "properties"=>$properties,
	    );
	    $result = $this->esClient->indices()->create($indexParams);
	
	    //后续增加
	    //         $indexParams['type'] = 'souq_products_1';
	    //        $indexParams['body']['souq_products_2'] = array(
	    //            "properties"=>$properties,
	    //        );
	    //        $result = $this->esClient->indices()->putMapping($indexParams);
	    return $result;
	}
}

?>