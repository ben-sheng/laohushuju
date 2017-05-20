<?php
namespace app\home\controller;
use app\model\EbayListingEsModel;
use ZipArchive;

class Listing  extends BaseController
{
    private $datadir ="/data/appdatas/panda/public/data/downloads/";
//	  private $datadir ="D:\\phpworkspace\\panda\\public\\data\\downloads\\";
    public function index()
    {
        $elm = new EbayListingEsModel();
        $params = $elm->getParams();
        $result = $elm->client->search($params);
        $list = $result["hits"]["hits"];
        $this->assign('list', $list);
        $this->assign('count', count($list));
        return $this->fetch();
    }
    
    /**
     * 根据过滤信息，获取商品列表
     * @return unknown
     */
    function fetchListings($from, $size, $fields){       
        $sort = "soldnum";
        //this surl is invalid,because func sortUrl() replace it perfect, only param to func filterPost now;
        $surl = $_SERVER['REQUEST_URI'];
//         $this->assign("surl",$this->sortUrl());
        
        $sortid = $this->request->param("sort");
        if(empty($sortid)){
            $sortid = 1;
        }
        switch($sortid){
            case 1:
                $sort = "soldnum";
                break;
            case 2:
                $sort = "lprice";
                break;
            case 3:
                $sort = "soldnum";
                break;
            case 4:
                $sort = "generationtime";
                break;
            default:
                $sort = "soldnum";
        }

        
        $must_not = array();
        $filter = array(
 //           array("range"=>array("soldnum"=>array("gte"=>1))),
        );
        $this->filterPost("seller", "seller", "string", "seller", $_GET, $surl, $filter, "term");
        $this->filterPost("lid", "lid", "string", "lid", $_GET, $surl, $filter, "term");
        $this->filterPost("mingt", "maxgt", "date", "generationtime", $_GET, $surl, $filter, "range");
        $this->filterPost("country", "country", "string", "country", $_GET, $surl, $filter, "term");
        $this->filterPost("minprice", "maxprice", "int", "lprice", $_GET, $surl, $filter, "range");
        $variations = trim( $this->request->param("variations") );
        //        echo "<br>++>$variations<++";
        if($variations==='y' || $variations==='n'){
            $_GET["variations"] = $variations;
            $inline = "doc['variations'].value!='[]'  && doc['variations'].value!=null";
            if($variations==='n'){
                $inline = "doc['variations'].value=='[]'  || doc['variations'].value==null";
            }
            $script = array(
                "script"=>array(
                    "inline"=>$inline,
                    "lang"=>"painless"
                )
            );
            $filter[]=array("script"=>$script);
        }
        
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
        
        $query = array();
        $location = $this->request->param("location");
        if(!empty($location)){
            $_GET["location"]=$location;
            $sqlkey = preg_replace("/\s+/",' ',$location);
            $query["must"][] = array("match"=>array("location"=>array("query"=>$sqlkey,"operator"=>"and")));
        }
        $keyword = $this->request->param("keyword");
        if(!empty($keyword)){
            $_GET["keyword"]=$keyword;
            $sqlkey = preg_replace("/\s+/",' ',$keyword);
            $query["must"][] = array("match"=>array("ltitle"=>array("query"=>$sqlkey,"operator"=>"and")));
        }
        
        $elm = new EbayListingEsModel();
        $sparams = $elm->getParams();
        $sparams["size"] = $size;
        $sparams["from"] = $from;    
        $sparams["_source"] = $fields;
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
//                echo json_encode($sparams);
        
        $result = $elm->client->search($sparams);
        return $result;
    }
    
    /**
     * 根据指定的过滤信息，最多一次抓取5000条商品信息，生成商品下载文件，
     * 返回文件名和下载路径
     */
    public function makedl(){
        $data = array("code"=>1);
        $fields = array("lid","ltitle","lprice","location","specifics","variations","soldnum7","soldnum15","imgs","shippingprice");
        $listingary = $this->fetchListings(0, 400, $fields);

        if(empty($listingary) || count( $listingary["hits"]["hits"] )<=0 ){
            echo json_encode($data,true);
            return;
        }
        
        $excelname = $this->download_base($listingary["hits"]["hits"]);
        
        if(!empty($excelname)){
            $file_path = $this->datadir.$excelname;
            if(file_exists($file_path)){
        //         $file_path="/data/downloads/1.xlsx";
                // get file info
                $filename = basename($file_path);
                $filesize = round(filesize($file_path) / 1024); // file size in KB
                
                $data = array(
                    "code"=>0,
                    "filename" => $filename,
                    "filesize" => $filesize,
                    "filepath"=>"/data/downloads/".$excelname
                );
            }
        }
        echo json_encode($data,true);
    }
    
    
    
    /**
     * 把指定的文件打包为Zip文件，提供打包下载
     * @param unknown $files
     * @return boolean|string
     */
    function zipfile( $files ){
        //进入目录打包
        $cdir = getcwd();
        chdir($this->tmpdir);
        $filename=date('YmdHis')."_".rand(10000,99999).".zip"; //最终生成的文件名（含路径）
        if(file_exists($filename)){
            unlink($filename);
        }
        //重新生成文件
        $zip=new ZipArchive();
        if($zip->open($filename,ZIPARCHIVE::CREATE)!==TRUE){
            return false;
        }
    
        foreach($files as $val){
            if(file_exists($val)){
                $zip->addFile($val);
            }
        }
        $zip->close();//关闭
        if(!file_exists($filename)){
            return false; //即使创建，仍有可能失败
        }
        chdir($cdir);
        return $filename;
    }
    
    private function download_base($products){
        $datas = array(
            "1"=>array(
                    'A'=>'Item no','B'=>'标题','C'=>'是否多属性','D'=>'售价','E'=>'运费','F'=>'物品所在地','G'=>'描述',
                    'H'=>'描述图片链接','I'=>'7天销量','J'=>'30天销量','K'=>'橱窗图片链接'
                )
            );

        $row =1;
        foreach($products as $product){
            $row++;
            $cell = $this->formatCell($product);
            $datas[$row] = $cell;            
        }

        $cells = array("A","B","C","D","E","F","G","H","I","J","K");
        $excelname = $this->export($datas,$cells);

        return $excelname;
    }
    
    /**
     * 格式化两种下载数据，店小秘和芒果
     * @param unknown $product
     * @param unknown $v
     * @param unknown $type
     * @return multitype:string number unknown mixed
     */
    function formatCell( $product ){
        $cell = array('A'=>'','B'=>'','C'=>'','D'=>'','E'=>'','F'=>'','G'=>'','H'=>'','I'=>'','J'=>'','K'=>'');
    
        $cell['A']= $product["_source"]["lid"]." ";
        $cell['B']= $product["_source"]["ltitle"];
        $cell['C']= "yes";//多属性
        if(empty($product["_source"]["variations"]) || $product["_source"]["variations"]=="[]"){
            $cell['C']= "no";//多属性
        }
        $cell['D']= $product["_source"]["lprice"];
        $cell['E']= $product["_source"]["shippingprice"];
        $cell['F']= $product["_source"]["location"];
        $cell['G']= $product["_source"]["specifics"];
        $cell['H']= $product["_source"]["specificsimgs"];
        $cell['I']= $product["_source"]["soldnum7"];
        $cell['J']= $product["_source"]["soldnum15"];
        $imgs = "";
        if(!empty($product["_source"]["imgs"])){
            if(is_array($product["_source"]["imgs"])){
                foreach($product["_source"]["imgs"] as $img){
                    if(empty($imgs)){
                        $imgs = "\"".$img;
                    }else{
                        $imgs .= "\r\n".$img;
                    }
                }
                $imgs.= "\"";
            }else{
                $imgs = $product["_source"]["imgs"];
            }
        }
        $cell['K']= $imgs;
        
        return $cell;
    }
    /**
     *
     * @param unknown $datas 数据
     * @param unknown $cells 字段
     */
    public function export($datas, $cells){
        Vendor('PHPExcel.PHPExcel');
        $Excel = new \PHPExcel();
         
        $Excel
        ->getProperties()
        ->setCreator("ebay")
        ->setLastModifiedBy("ebay")
        ->setTitle("EXCEL导出")
        ->setSubject("EXCEL导出")
        ->setDescription("EXCEL导出")
        ->setKeywords("excel")
        ->setCategory("result file");
    
        //	    $datas = array ( 1 => array ( 'A' => '分公司名称', 'B' => '姓名', 'C' => '金额', ), 2 => array ( 'A' => 'A分公司', 'B' => '赵娟', 'C' => 1100, ), 3 => array ( 'A' => 'B分公司', 'B' => '孔坚', 'C' => 1100, ), 4 => array ( 'A' => 'C分公司', 'B' => '王华发', 'C' => 1300, ), 5 => array ( 'A' => 'C分公司', 'B' => '赵辉', 'C' => 700, ), 6 => array ( 'A' => 'B分公司', 'B' => '华发', 'C' => 1400, ), 7 => array ( 'A' => 'A分公司', 'B' => '赵德国', 'C' => 700, ), 8 => array ( 'A' => 'B分公司', 'B' => '沈芳虹', 'C' => 500, ), 9 => array ( 'A' => 'C分公司', 'B' => '周红玉', 'C' => 1100, ), 10 => array ( 'A' => 'A分公司', 'B' => '施芬芳', 'C' => 800, ), 11 => array ( 'A' => 'A分公司', 'B' => '蒋国建', 'C' => 1100, ), 12 => array ( 'A' => 'B分公司', 'B' => '钱毅', 'C' => 1400, ), 13 => array ( 'A' => 'B分公司', 'B' => '陈华惠', 'C' => 1200, ), 14 => array ( 'A' => 'C分公司', 'B' => '曹香', 'C' => 1400, ), 15 => array ( 'A' => 'A分公司', 'B' => '郑红妙', 'C' => 600, ), 16 => array ( 'A' => 'A分公司', 'B' => '王宏仁', 'C' => 800, ), 17 => array ( 'A' => 'C分公司', 'B' => '何丹美', 'C' => 1300, ), );
    
        foreach($datas as $key => $val) { // 注意 key 是从 0 还是 1 开始，此处是 0
            // $num = $key + 1;
             
            //Excel的第A列，uid是你查出数组的键值，下面以此类推
            foreach( $cells as $cell){
                $Excel ->setActiveSheetIndex(0) ->setCellValue($cell.$key, $val[$cell]);
            }
            // 	        $Excel ->setActiveSheetIndex(0)
            // 	        ->setCellValue('A'.$key, $val['A'])
            // 	        ->setCellValue('B'.$key, $val['B'])
            // 	        ->setCellValue('C'.$key, $val['C']);
        }
    
        $Excel->getActiveSheet()->setTitle('EB数据');
        $Excel->setActiveSheetIndex(0);
    
        // 	    header('Content-Type: application/vnd.ms-excel');
        // 	    header('Content-Disposition: attachment; filename='.$name);
        // 	    header('Cache-Control: max-age=0');
    
        $ExcelWriter = \PHPExcel_IOFactory::createWriter($Excel, 'Excel2007');
        //	    $ExcelWriter->save('php://output');
        $ename = date('YmdHis')."_".rand(10000,99999).".xlsx";
//        echo "<br>0000:".$ename;
        $ExcelWriter->save($this->datadir.$ename);
        return $ename;
    }
    
}