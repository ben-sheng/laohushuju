<?php
namespace app\ebfront\controller;
use think\Controller;
use app\model\EbayListingEsModel;

class Listing extends Controller
{
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
    
}