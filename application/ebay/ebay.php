<?php
namespace app\ebay;

use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;
use think\Cache;
//use think\Db;
use app\ebay\EbaySpider;
use app\ebay\EbayCategory;
use app\model\EbayListingEsModel;
use app\model\EbayThreadLogEsModel;
use app\model\EbaySubTaskEsModel;
use app\model\EbayCategoryEsModel;
use app\model\EbaySellerEsModel;


//require_once '../model/WishStatEsModel.class.php';

class ebay extends Command
{

protected function configure()
    {
        // 指令配置
        $this
            ->setName('ebay')
            ->addOption('cmd', 'c', Option::VALUE_OPTIONAL, '运行的命令', null)
            ->addOption('country', 'co', Option::VALUE_OPTIONAL, '国家', null)
            ->addOption('totaltask', 't', Option::VALUE_OPTIONAL, '所有机器总任务数', null)
            ->addOption('sn', 's', Option::VALUE_OPTIONAL, '任务序列号', null)
            ->addOption('subtasksn', 'ssn', Option::VALUE_OPTIONAL, '每个机器指定子任务编号', null)
            ->addOption('subtasktotal', 'stt', Option::VALUE_OPTIONAL, '每个机器子任务数', null)
            ->addOption('key', 'k', Option::VALUE_OPTIONAL, '给某个KEY赋值', null)
            ->addOption('value', 'rv', Option::VALUE_OPTIONAL, '设置共享值', null)
            ->setDescription('Ebay Spider');
    }

    protected function execute(Input $input, Output $output)
    {
//         Cache::set('kname',"xxx898989",3600);
//         dump(Cache::get('kname'));exit;
        $cmd = $input->getOption('cmd');
        $output->writeln("option:::$cmd");
        if(empty($cmd)){
            $output->writeln("未指定参数!");
            return;
        }
        switch($cmd){
            case "setkey":
                $this->setKey($input,$output);
                break;
            case "model":
                $this->createModel();
                break;
            case "category":
                $this->crawlCategory();
                break;
            case "listing":
                $this->ebayListing("category",$input,$output);
                break;
            case "listingseller":
                $this->ebayListing("seller",$input,$output);
                break;
            case "listinginfo":
                $this->ebayListingInfo("info",$input,$output);
                break;
            case "listingsold":
                $this->ebayListingInfo("sold",$input,$output);
                break;
            case "sellerinfo":
                $this->ebaySeller("info",$input,$output);
                break;
            default:
                $output->writeln("[$cmd] 未找到匹配的命令！");
                break;
        }        
        $output->writeln("\nPanda Successed");
    }
    
    function setKey(Input $input, Output $output){
        $key = $input->getOption('key');
        $value = $input->getOption('value');
        $redis = new \Redis();
        $redis->connect('127.0.0.1', 6379);
        $res = $redis->set($key, $value);
        if($res){
            $rv = $redis->get($key);
            $output->writeln("\nset rediskey $key = $rv success!");
        }else{
            $output->writeln("\nset rediskey $key = $rv failed!");
        }
    }
    function createModel(){
//         $model = new EbayCategoryEsModel();
//         $res = $model->create_index_mapping_v2();
        
         $model = new EbayListingEsModel();
         $res = $model->create_index_mapping();
         echo '\n'.json_encode($res);
        
//         $model = new EbaySellerEsModel();
//         $res = $model->create_index_mapping();
//         echo '\n'.json_encode($res);
        
//           $model = new EbaySubTaskEsModel();
//         $res = $model->create_index_mapping();
        
//         $model = new EbayThreadLogEsModel();
//         $res = $model->create_index_mapping();
        
    }
    
    function ebayListing($cmd, Input $input, Output $output){
        $country = $input->getOption('country');
        $tt = $input->getOption('totaltask');
        $sn = $input->getOption('sn');
        $ssn = $input->getOption('subtasksn');
        $subtotal = $input->getOption('subtasktotal');
        $crawltime = time();
        if( empty($tt) || !is_numeric($sn) || $sn>=$tt){
            $output->writeln("任务参数错误：totaltask:$tt sn:$sn");
            return;
        }
//             $output->writeln("任务参数错误：totaltask:$tt sn:$sn");
//             return;}
        $el = new EbayListing();
        $el->fetchListingWrapper( $cmd, $country, $tt, $sn, "t$sn",$subtotal,  $ssn, $crawltime);
//        $el->updateListingCountry($tt*1000);
    }
    
    function ebayListingInfo($cmd, Input $input, Output $output){
        $country = $input->getOption('country');
        $tt = $input->getOption('totaltask');
        $sn = $input->getOption('sn');
        $ssn = $input->getOption('subtasksn');
        $subtotal = $input->getOption('subtasktotal');
        $crawltime = time();
        if( empty($tt) || !is_numeric($sn) || $sn>=$tt){
            $output->writeln("任务参数错误：totaltask:$tt sn:$sn");
            return;
        }
        
        $el = new EbayListing();
         $el->fetchListingInfoWrapper($cmd, $country, $tt, $sn, "t$sn",$subtotal,  $ssn,$crawltime);
//        $el->updateListingCountry(0);
    }
    
    function ebaySeller($cmd, Input $input, Output $output){
        $country = $input->getOption('country');
        $tt = $input->getOption('totaltask');
        $sn = $input->getOption('sn');
        $ssn = $input->getOption('subtasksn');
        $subtotal = $input->getOption('subtasktotal');
        $crawltime = time();
        if( empty($tt) || !is_numeric($sn) || $sn>=$tt){
            $output->writeln("任务参数错误：totaltask:$tt sn:$sn");
            return;
        }
        $el = new EbaySeller();
        $el->sellerWrapper( $cmd, $country, $tt, $sn, "t$sn",$subtotal,  $ssn,$crawltime);
    }
    
    function crawlCategory(){
 //       $output->writeln("CrawlCategory....");
        $ec = new EbayCategory();
//        $ec->crawlEbayCategorys();
 //       $ec->tagCategory();
        $ec->exportCatgorys();
    }
}

?>