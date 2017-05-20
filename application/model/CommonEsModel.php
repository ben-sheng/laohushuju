<?php

/* * 
 * 公共模型
 */

namespace app\model;
use Elasticsearch\ClientBuilder;
use Elasticsearch\Client;
//Vendor('elasticsearch5.autoload');
//require 'vendor/autoload.php';

class CommonEsModel{

protected  $index = "";
    protected  $type="";
    public $client = null;
    
    function __construct( $index, $type){
        $this->index = $index;
        $this->type = $type;
        $hosts=array('http://'.config('es_user').':'.config('es_pwd').'@'.config('es_host').":".config('es_port'));
        $this->client = ClientBuilder::create()->setHosts($hosts)->build();
    }
    
    function __destruct(){
        unset($this->client);
        $this->client = null;
    }
    
    function getParams($id=null){
        $params = array(
            "index"=>$this->index,
            "type"=>$this->type
        );
        if(!empty($id)){
            $params['id']=$id;
        }
        return $params;
    }
    
    function getBulkParams( $id=null ){
        $params = array(
            "_index"=>$this->index,
            "_type"=>$this->type,
        );
        if(!empty($id)){
            $params['_id'] = $id;
        }
        return $params;
    }

}

?>