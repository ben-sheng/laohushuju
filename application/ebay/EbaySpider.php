<?php
namespace app\ebay;
use Snoopy\Snoopy;
use  HtmlParser\ParserDom;

//require_once  'vendor/autoload.php';

class EbaySpider{

    function __construct()
    {}

    function __destruct()
    {}
    
    /**
     * 从所有分类页，获得第一层分类列表
     * @return multitype:multitype:string
     */
    function getHeadCategory(){
        $cats = array();
        $url = "http://www.ebay.com/sch/sch/allcategories/all-categories";
        $snoopy = new Snoopy;
        $snoopy->fetch($url);
        $response = $snoopy->results;
        $html_dom = new  ParserDom($response);
        $lis = $html_dom->find("li");
        foreach($lis as $li){
            $a = $li->find("a",0);
            if(empty($a)) continue;
            
            $href =$a->getAttr("href");
            if(empty($href) || strpos($href, "i.html")==false ) continue;
            $cats[] = array(
                "categoryname"=>trim( $a->getPlainText() ),
                "categoryurl"=>trim($href)
            );
        }
        return $cats;
    }
    
    /**
     * 根据指定分类url，抓取该分类的所有子类
     * @param unknown $catsurl
     * @param $crawltags 指定哪些分类需要被抓取，因为存在嵌套抓取，所以在此指定
     */
    function fetchLeafCategorys( $catsurl, $crawltags=null, $loop=0 ){
        if(empty($catsurl)) return null;
        $catsurl = str_replace(" ", "-", trim($catsurl));
        if(empty($crawltags)){
            $crawltags = $this->getCrawlerTag();
        }
        $allcats = array();
        $snoopy = new Snoopy;
        $snoopy->agent = $this->ua();
        try{
            $snoopy->fetch($catsurl);
        }catch(\Exception $e){
            echo "\nfetch exception::".$e->getMessage();
            if($loop>=3){
                return null;
            }else{
                $loop++;
                return $this->fetchLeafCategorys($catsurl, $crawltags,$loop);
           }
        }

        $response = $snoopy->results;
//       file_put_contents("cat.html", $response, FILE_APPEND|FILE_USE_INCLUDE_PATH);
//        Log::info($response);
//         echo "\nres::".$response;
        try{
                $html_dom = new  ParserDom($response);
        }catch(\Exception $e){
            echo "\n PaserDom err,continue.";
            if($loop>=3){
                return null;
            }else{
                $loop++;
                return $this->fetchLeafCategorys($catsurl, $crawltags,$loop);
            }
        }
        $leaf = $html_dom->find("div.leafsiblings",0);
        if(!empty($leaf)){
            //获取父子分类(id，级别，url，是否leaf，商品数，父id)
            $pcats = $html_dom->find("div.brcrCatAsp ul.bc-cat li[itemprop=itemListElement]");
            if(!is_array($pcats)) return null;
            $level = 0;
            $pid = 0;    
            $crawler = 0;
            $categorys = array();
            foreach($pcats as $c){
                $leaf = 0;
                $listings = 0;
                $level++;
                $cid = $c->getAttr("data-categoryid");                
                $cname = trim( $c->find("span[itemprop=name]",0)->getPlainText());
                $c_url = $catsurl;
                $a = $c->find("a",0);
                if(!empty($a)){
                    $c_url =$a->getAttr("href");
                }else{
                    $lspan = $html_dom->find("span.listingscnt",0);
                    if(!empty($lspan)){
                        $ltxt = trim( $lspan->getPlainText() );
                        $ltxt = trim( substr($ltxt, 0, strpos($ltxt, " ")) );
                        $ltxt = str_replace(",", "", $ltxt);
                        $listings = intval($ltxt);
                    }
                    $leaf = 1;
                }
                if(isset( $crawltags[$cname]) ){
                    $crawler = 1;
                }
                $categorys[] = $cid;                
                $allcats["cc$cid"] = array(
                    "categoryid"=>$cid,
                    "pid"=>$pid,
                    "categoryname"=>$cname,
                    "categoryurl"=>$c_url,
                    "level"=>$level,
                    "leaf"=>$leaf,
                    "listings"=>$listings,
                    "categorys"=>$categorys,
                    "crawler"=>$crawler,
                );
//                echo "\r\ncid:$cid++pid:$pid++lv:$level+++listings:$listings";
                $pid = $cid;
            }
            
        }else{
            //取出子分类，循环查找叶子
            $subcats = array();
            $subcats1 = $html_dom->find("div.cat-c",0);
            if($subcats1){
                $subcats = $subcats1->find("a");
            }else{
                $subcats2 = $html_dom->find("div.nav-module li",0);
 //               echo "\n".$subcats2->innerHtml();
                if(!empty($subcats2)){
                    $subcats = $subcats2->find("a");
                }
            }
            if(!empty($subcats)){
                unset($html_dom);
                unset($response);
                foreach($subcats as $sc){
                    $href = $sc->getAttr("href");
//                    echo "\n href::$href";
                    if(empty($href) || $href=="#") continue;
                    //获得子分类信息，并且合并到分类表中
                    $childcats = $this->fetchLeafCategorys($href, $crawltags);
                    if(!empty($childcats)){
                        $allcats = array_merge($allcats, $childcats);
                    }
                }
            }

        }
        
        return $allcats;
    }
    
    /**
     * 指定国家和分类，抓取分类中的信息
     * http://www.ebay.com/sch/AR-IA-KS-LA-MO-NE/163062/i.html
     * http://www.ebay.com/sch/AR-IA-KS-LA-MO-NE/163062/i.html?_ipg=100&rt=nc
     * http://www.ebay.com/sch/AR-IA-KS-LA-MO-NE/163062/i.html?_pgn=2&_skc=100
     * //?_sop=12&_pgn=2&_skc=50
     * @param $country 国家，US，UK。。。
     * @param $categoryid 分类id
     * @param unknown $cgurl 分类url
     * @param number $page 页数
     * @param number $sort 10:newly; 12:match 排序方案，初始化时按照match，以后都按照newly
     * @param number $count 每页多少条，目前固定50条
     * @return 返回产品列表[listings,page,count,items[]]
     */
    function crawlProductsByCategory( $country, $categoryid, $cgurl, $page=1, $sort=10, $count=50, $loop=0 ){
//        xdebug_break();
        if(empty($country)) $country = "US";
        $result = array();
        $snoopy = new Snoopy;
        $snoopy->agent = $this->ua();
        $snoopy->rawheaders["X_FORWARDED_FOR"] = $this->radomIp();
        $snoopy->rawheaders["CLIENT-IP"] = $this->radomIp();
        
        if($page==1){
            $snoopy->referer = $cgurl;
            $cgurl .= "?_ipg=$count&rt=nc&_dmd=1&_sop=10&LH_BIN=1=1";
        }elseif($page==2){
            $snoopy->referer = $cgurl."?_ipg=$count&rt=nc&_dmd=1&_sop=10&LH_BIN=1=1";
            $cgurl .= "?_pgn=2&_skc=$count&_dmd=1&_sop=10&LH_BIN=1=1";
        }else{
            $snoopy->referer = $cgurl."?_pgn=".($page-1)."&_skc=".(($page-2)*$count)."&_dmd=1&_sop=10&LH_BIN=1=1";
            $cgurl .= "?_pgn=$page&_skc=".(($page-1)*$count)."&_dmd=1&_sop=10&LH_BIN=1";
        }
        
        $cgurl = $this->getUrlByContry($country,$cgurl);
        try{
            $snoopy->fetch($cgurl);
            $response = $snoopy->results;
//            echo "\nRES::".$response;
            //       file_put_contents("cat.html", $response, FILE_APPEND|FILE_USE_INCLUDE_PATH);
            //        Log::info($response);
            $html_dom = new  ParserDom($response);
        }catch(\Exception $e){
            echo "\nfetch exception::category:$categoryid page:$page E:".$e->getMessage();
            if($loop>=3){
                return null;
            }else{
                $loop++;
                sleep(rand(1,3));
                return $this->crawlProductsByCategory($country, $categoryid, $cgurl, $page, $sort, $count, $loop );
            }
        }
              
        $listings = 0;
        $lspan = $html_dom->find("span.listingscnt",0);
        if(!empty($lspan)){
            $ltxt = trim( $lspan->getPlainText() );
            $ltxt = trim( substr($ltxt, 0, strpos($ltxt, " ")) );
            $ltxt = str_replace(",", "", $ltxt);
            $listings = intval($ltxt);
        }
        $result["listings"] = $listings;
        $result["page"] = $page;
        $result["count"] = $count;
        $result["categoryid"]=$categoryid;
        $litems = array();
        $itemlist = $html_dom->find("ul[id=ListViewInner] li");
        if(!empty($itemlist)){
            foreach($itemlist as $item){
                $lid = $item->getAttr("listingid");
                if(empty($lid))continue;
                $ltitle_a = $item->find("h3.lvtitle a",0);
                if(empty($ltitle_a))continue;
                $ltitle = trim( $ltitle_a->getPlainText() );
                if(strpos($ltitle, "New listing")!==false){
                    $ltitle = trim(str_replace("New listing", "", $ltitle));
                }
                $lurl = trim( $ltitle_a->getAttr("href") );
                
                $thumb = "";
                $imgel = $item->find("a.img img",0);
                if(!empty($imgel)){
                    $thumb = trim( $imgel->getAttr("src") );
                }
                
                $lpspan = $item->find("li.lvprice span",0);
                $lcurrency = "US";
                $lprice = 0;
                if(!empty($lpspan)){
                    $lcb = $lpspan->find("b",0);
                    if(!empty($lcb)){
                        $lcurrency = $lcb->getPlainText();
                        $lprice = trim($lpspan->getPlainText());
                        $lprice = trim( substr($lprice, strpos($lprice, " ")+1) );
                        if(strpos($lprice, " ")!==false){
                            $lprice = trim( substr($lprice, 0, strpos($lprice, " ")) );
                        }
                        $lprice = floatval( str_replace(",", "", $lprice) );
                    }else{
                        $lphtml = trim(htmlentities($lpspan->innerHtml()));//&Acirc;&pound;472.59
                        $lptxt = substr($lphtml, strrpos($lphtml,";")+1);
                        $lprice = floatval( str_replace(",", "", trim($lptxt)) );
                        $lcurrency = $this->convertCurrency($lphtml);
                    }                    
                }
                
                $lformat = "BUY";
                $lfspan = $item->find("li.lvformat span",0);
                if(!empty($lfspan)){
                    $lftxt= trim( $lfspan->getPlainText() );
                    if(strpos($lftxt, "bid")>0 || strpos($lftxt, "bid")===0){
                        $lformat = "BID";
                    }
                }
                $it = array(
                    "categorys"=>$categoryid,
                    "lid"=>$lid,
                    "ltitle"=>$ltitle,
                    "lurl"=>$lurl,
                    "lthumb"=>$thumb,
                    "lcurrency"=>$lcurrency,
                    "lprice"=>$lprice,
                    "lformat"=>$lformat,
                    "country"=>array($country),
                );
                $litems[]=$it;
            }            
        }
        $result["items"] = $litems;
        return $result;  
        //{"categorys":["63861","175633"],"variations":{"colors":["Light Blue","Dark Blue","White","Black","Pink","Red","Rose","Purple","Yellow","Green"],"size.":["S","M","L","XL","2XL","3XL"]},"sold":1731,"lcurrency":"US","lprice":9,"lorgprice":9,"watching":2664,"location":"Hongkong, Hong Kong","shipto":"Worldwide","excludes":"Algeria, Angola, Benin, Botswana, Burkina Faso, Burundi, Cameroon, Cape Verde Islands, Central African Republic, Chad, Comoros, Congo, Democratic Republic of the, Congo, Republic of the, CÃ´te d'Ivoire (Ivory Coast), Djibouti, Egypt, Equatorial Guinea, Eritrea, Ethiopia, Gabon Republic, Gambia, Ghana, Guinea, Guinea-Bissau, Kenya, Lesotho, Liberia, Libya, Madagascar, Malawi, Mali, Mauritania, Mayotte, Morocco, Mozambique, Namibia, Niger, Nigeria, Reunion, Rwanda, Saint Helena, Senegal, Seychelles, Sierra Leone, Somalia, South Africa, Swaziland, Tanzania, Togo, Tunisia, Uganda, Western Sahara, Zambia, Zimbabwe, Iraq, Lebanon, Qatar, Yemen, Afghanistan, China, Georgia, Sri Lanka, American Samoa, Cook Islands, Fiji, French Polynesia","specifics":""}
    }
    
    /**
     * 根据指定的商品URL，抓取商品的详细信息
     * http://www.ebay.com/itm/Fashion-Women-Lace-Short-Dress-Prom-Evening-Party-Cocktail-Bridesmaid-Wedding-/322215834489?var=&hash=item4b058f7379:m:mo8oFDTlg_jt3dRVQlZdV1A#shpCntId
     * http://www.ebay.com/itm/Selah-Washington-Naches-River-Yakima-River-vintage-1960-old-USGS-Topo-chart-/122315165836?hash=item1c7a8d508c:g:3TMAAOSw-0xYfr70&autorefresh=true
     * @param string $purl
     */
    function crawlProduct($type, $country, $purl, $loop=0 ){
        $result = array(
            "status"=>0
        );
        if(empty($purl)) return $result;
        if(empty($country)) $country = "us";    
        $purl = $this->getUrlByContry($country, $purl);
        $purl .= "&orig_cvip=true";//如果商品销售完了，查看原页面
        
        $snoopy = new Snoopy;
        $snoopy->agent = $this->ua();
        $snoopy->rawheaders["X_FORWARDED_FOR"] = $this->radomIp();
        $snoopy->rawheaders["CLIENT-IP"] = $this->radomIp();
//        $snoopy->fetch($purl);
        try{
            $snoopy->fetch($purl);
            $response = $snoopy->results;
            $html_dom = new  ParserDom($response);
        }catch(\Exception $e){
            echo "\nfetch exception::prul $purl \nE:".$e->getMessage();
            if($loop>=3){
                return $result;
            }else{
                $loop++;
                sleep(rand(1,3));
                return $this->crawlProduct($type, $country, $purl, $loop );
            }
        }
//        echo "\n".json_encode($response);
        $ctd = $html_dom->find("td[id=vi-VR-brumb-lnkLst] table",0);
        if(empty($ctd)){
            $msg = $html_dom->find("div.sml-cnt",0);
            if(!empty($msg)){
                $result["status"] = 1;
            }else{
                $result["status"] = 2;
            }
            return $result;
        }
        
        
        //price
        $result["lcurrency"] = "US";
        $result["lprice"] = 0;
        $price_el = $html_dom->find("span[id=prcIsum]",0);
        if(empty($price_el)){
            $price_el = $html_dom->find("span[id=mm-saleDscPrc]",0);
        }
        if(empty($price_el)){
            $price_el = $html_dom->find("div[id=finalPrc]",0);
        }
        if(!empty($price_el)){
            $price_txt = trim( $price_el->getPlainText() );//US $31.16
            if(strpos($price_txt, " ")!==false){
                $result["lcurrency"] = trim( substr($price_txt, 0, strpos($price_txt, " ")) );
                $ptxt = trim(substr($price_txt, strrpos($price_txt, " ")+1));
                if(is_numeric($ptxt)){
                    $result["lprice"] = floatval(str_replace(",", "", $ptxt));
                }else{
                    //                $currencytxt = trim( substr($ptxt),0,1);
                    $ptxt2 = trim( substr($ptxt,1));
                    $result["lprice"] = floatval(str_replace(",", "", $ptxt2));
                }
            }
        }
        $result["lorgprice"] = $result["lprice"];
        
        $orgprice_el = $html_dom->find("span[id=mm-saleOrgPrc]",0);
        if(empty($orgprice_el)){
            $orgprice_el = $html_dom->find("span[id=orgPrc]",0);
        }
        if(!empty($orgprice_el)){
            $optxt = trim( $orgprice_el->getPlainText() );
            $result["lorgprice"] = floatval( substr($optxt, 1) );
            //如果没有抓到价格，就用原价
            if(empty($result["lprice"]))$result["lprice"]=$result["lorgprice"];
        }
        
        $soldel = $html_dom->find("span.vi-qty-pur-lnk",0);//vi-qty-pur-lnk//vi-qtyS
        $sold = 0;
        if(!empty($soldel)){
            $sael = $soldel->find("a",0);
            if(!empty($sael)){
                $sold = trim($sael->getPlainText());
                $sold = substr($sold, 0,strpos($sold, " "));
                $sold = intval( str_replace(",", "", $sold) );
            }
        }
        $result["soldnum"] = $sold;
        
        //watching
        $result["watchingnum"] = 0;
        $w_el = $html_dom->find("span[id=vi-bybox-watchers] span.vi-buybox-watchcount",0);
        if(!empty($w_el)){
            $wnum = trim( $w_el->getPlainText() );
            //            echo "\r\nwatching:::".$wnum;
            $wnum = str_replace(",", "", $wnum);
            $result["watchingnum"] = intval($wnum);
        }
        
        if($type=="sold"){
            return $result;
        }
        
//        echo "\r\n111->".$ctd->innerHtml();
        $ctrs = $ctd->find("a[itemprop=item]");
//        echo "\r\n222->".$ctrs->getPlainText();
        $categorys = array();
        foreach($ctrs as $a){
//            $a = $tr->find("a[itemprop=item]",-1);     
            $href = $a->getAttr("href");
            $htxt = substr($href, 0,strrpos($href, "/"));
            $htxt = substr($htxt, strrpos($htxt, "/")+1);
            $categorys[]=trim($htxt);
            //http://www.ebay.com/sch/Dresses-/63861/i.html;
        }
        $result["categorys"] = $categorys;
        
        //ended
        $result["ended"]=0;
        $msgp = $html_dom->find("div[id=msgPanel]",0);
        if(!empty($msgp)){
            $msg = $msgp->getPlainText();
            $endtxt = "This listing has ended";
            if( strpos($msg, $endtxt)!==false){
                $result["ended"]=1;
            }
        }
        
        //SKU
        $skudiv = $html_dom->find("div.vi-msku-cntr");
        $variations = array();
        if(!empty($skudiv)){
            foreach($skudiv as $sv){
                $vtitle = trim( $sv->find("label",0)->getPlainText() );
                $vtitle = strtolower( substr($vtitle,0,strlen($vtitle)-1) );
                
                $vitem = array();
                $option = $sv->find("option");
                if(!empty($option)){
                    for($i=0;$i<count($option);$i++){
                        if($i==0)continue;
                        $vitem[] = $option[$i]->getPlainText();
                    }
                }
                $variations[$vtitle]=$vitem;
            }
        }
        $result["variations"] = json_encode($variations);
        
        
        
        //mainimg icImg
        $mimgel = $html_dom->find("img[id=icImg]",0);
        if(!empty($mimgel)){
            $result["mainimg"] = $mimgel->getAttr("src");
        }
        //imgs
        //http://i.ebayimg.com/images/g/gu0AAOSwIgNXoprX/s-l64.jpg
        //64,225,500
        $imgs = array();
        $imgblock = $html_dom->find("div[id=vi_main_img_fs]",0);
        if(!empty($imgblock)){
            $imglis = $imgblock->find("li");
            if(!empty($imglis)){
                foreach($imglis as $li){
                    $imgel = $li->find("img",0);
                    if(!empty($imgel)){
                        $img = trim( $imgel->getAttr("src") );
                        $imgs[]=$img;
                    }
                }
            }
        }else{
            $imgs=$result["mainimg"];
        }
        $result["imgs"] = $imgs;
        
        //price
        $result["lcurrency"] = "US";
        $result["lprice"] = 0;
        $price_el = $html_dom->find("span[id=prcIsum]",0);
        if(empty($price_el)){
            $price_el = $html_dom->find("span[id=mm-saleDscPrc]",0);          
        }
        if(empty($price_el)){
            $price_el = $html_dom->find("div[id=finalPrc]",0);
        }
        if(!empty($price_el)){
            $price_txt = trim( $price_el->getPlainText() );//US $31.16
            if(strpos($price_txt, " ")!==false){
                $result["lcurrency"] = trim( substr($price_txt, 0, strpos($price_txt, " ")) );
                $ptxt = trim(substr($price_txt, strrpos($price_txt, " ")+1));
                if(is_numeric($ptxt)){
                    $result["lprice"] = floatval(str_replace(",", "", $ptxt));
                }else{
    //                $currencytxt = trim( substr($ptxt),0,1);
                    $ptxt2 = trim( substr($ptxt,1));
                    $result["lprice"] = floatval(str_replace(",", "", $ptxt2));
                }
            }
        }
        $result["lorgprice"] = $result["lprice"];
        
        $orgprice_el = $html_dom->find("span[id=mm-saleOrgPrc]",0);
        if(empty($orgprice_el)){
            $orgprice_el = $html_dom->find("span[id=orgPrc]",0);
        }
        if(!empty($orgprice_el)){
            $optxt = trim( $orgprice_el->getPlainText() );
            $result["lorgprice"] = floatval( substr($optxt, 1) );
            //如果没有抓到价格，就用原价
            if(empty($result["lprice"]))$result["lprice"]=$result["lorgprice"];
        }
        
        
        
        $sellerel = $html_dom->find("a[id=mbgLink]",0);
        $result["seller"] = trim($sellerel->getPlainText());
        $sellerurl = trim($sellerel->getAttr("href"));
        $result["sellerpage"] = substr($sellerurl,0,strpos($sellerurl, "?_t"));
        
        $storeel = $html_dom->find("div[id=storeSeller]",0);
        $ael = $storeel->find("a",0);
        if(!empty($ael)){
            $storeurl = trim($ael->getAttr("href"));
            $result["storepage"] = substr($storeurl,0,strpos($storeurl, "?_t"));
        }
        
        //shipping
        $shipping_el =$html_dom->find("div[id=shipNHadling]",0);
        if(!empty($shipping_el)){
            $loc_el = $shipping_el->find("div.sh-loc",0);
            if(!empty($loc_el)){
                $loc_txt = trim( $loc_el->getPlainText() );
                $result["location"] = trim( substr($loc_txt, strpos($loc_txt, ":")+1) );
            }
            
            $st_el = $shipping_el->find("div.sh-sLoc",0);
            if(!empty($st_el)){
                $st_txt = trim( $st_el->getPlainText() );
                $shippingto = trim( substr($st_txt, strpos($st_txt, ":")+1) );
                $result["shippingto"] = explode(",", $shippingto);
            }
            
//             $ex_el = $shipping_el->find("div.sh-sLoc",1);
//             if(!empty($ex_el)){
//                 $ex_txt = trim( $ex_el->getPlainText() );
//                 $excludes = trim( substr($ex_txt, strpos($ex_txt, ":")+1) );
//                 $result["excludes"] = split(",", $excludes);
//             }
        }
        
        //Item specifics
        $sp_el = $html_dom->find("div.itemAttr",0);
        if(!empty($sp_el)){
            $sp_table = $sp_el->find("table",0);
            if(!empty($sp_table)){
                $result["specifics"] = trim($sp_table->outerHtml());
            }
        }
        
//         $options = $html_dom->find("select[id=shCountry] option");
//         $oarray = array();
//         foreach($options as $o){
//             $oarray[trim($o->getPlainText())] = trim($o->getAttr("value"));
//         }
//         echo "\r\n".var_dump($oarray);
//         echo "\r\n".json_encode($oarray);
        return $result;
    }
    
    /**
     * 抓取订单列表
     * 但是订单列表总数跟商品页上面的售出数据不一致。所以不能使用。
     * http://offer.ebay.com/ws/eBayISAPI.dll?ViewBidsLogin&item=322215834489
     * @param unknown $itemid
     */
    function crawlPurchaseHistory( $itemid ){
        if(empty($itemid))return null;
        $purl = "http://offer.ebay.com/ws/eBayISAPI.dll?ViewBidsLogin&item=$itemid";
        $snoopy = new Snoopy;
        $snoopy->agent = $this->ua();
        $snoopy->rawheaders["X_FORWARDED_FOR"] = $this->radomIp();
        $snoopy->rawheaders["CLIENT-IP"] = $this->radomIp();
        $snoopy->fetch($purl);
        $response = $snoopy->results;
        $html_dom = new  ParserDom($response);
        
        $tds = $html_dom->find("td[colspan=10]");
        $ptotal = 0;
        foreach($tds as $td){
            $ptxt = $td->getPlainText();
            if(strpos($ptxt, "transactions of")>0){
                $ptotal = substr($ptxt, strpos($ptxt,"of ")+3);
                $ptotal = trim( substr($ptotal, 0, strpos($ptxt,"sold")) );
                $ptotal = intval($ptotal);
                break;
            }
        }
        
        //如果销售不够100个，则需要分析销售记录，统计总共有多少销售
        if($ptotal==0){
            $plist = array();
            $firstpurchanse = 1960000000;//2032年2月10日
            $trs = $html_dom->find("tr");
            foreach($trs as $tr){
                $tname_el = $tr->find("td.onheadNav",0);
                if(empty($tname_el)) continue;
                $tds = $tr->find("td");
                $pname = trim( $tds[1]->getPlainText() );
                $pname = substr($pname, 0, strpos($pname, " ")-1);
                
                $variation = trim( $tds[2]->getPlainText() );
                $price = trim( $tds[3]->getPlainText() );
                $quantity = trim( $tds[4]->getPlainText() );
                $ptime = strtotime( trim( $tds[5]->getPlainText() ) );
                
                if(is_numeric($quantity)===false) continue;
                
                $plist[] = array(
                    "customer"=>$pname,
                    "variation"=>$variation,
                    "price"=>$price,
                    "quantity"=>$quantity,
                    "ptime"=>$ptime
                );
                if($ptime>0 && $ptime<$firstpurchanse)$firstpurchanse=$ptime;
            }
            $ptotal = count($plist);
        }
        
        $phistory = array(
 //           "plist"=>$plist,
            "ptotal"=>$ptotal,
            "firstpurchanse"=>$firstpurchanse
        );
        return $phistory;        
    }
    
    /**
     * 不需要从此页面抓取数据。可以在feedback页面获得同样信息，并且可以获取到最近一个月的好评。
     * 在此可以获得商店的总商品数。但在店铺商品列表页面同样可以获取
     * @param unknown $storename
     * @return NULL
     */
    function crawlSellerInfo($storename){
        if(empty($storename)) return null;
        $surl = "http://www.ebay.com/usr/$storename";
        
        $snoopy = new Snoopy;
        $snoopy->agent = $this->ua();
        $snoopy->rawheaders["X_FORWARDED_FOR"] = $this->radomIp();
        $snoopy->rawheaders["CLIENT-IP"] = $this->radomIp();
        $snoopy->fetch($surl);
        $response = $snoopy->results;
        
        if(empty($response)) return null;
        $html_dom = new  ParserDom($response);
        
        //ToDo
        
    }
    
    //http://feedback.ebay.com/ws/eBayISAPI.dll?ViewFeedback2&userid=antiquemapsprints&ftab=FeedbackForItem&searchInterval=30
    //最近一个月的好评
    function crawlStoreFeedbackInfo( $sellerid, $loop=0 ){
        if(empty($sellerid)) return null;
        $surl = "http://feedback.ebay.com/ws/eBayISAPI.dll?ViewFeedback2&userid=$sellerid&ftab=FeedbackForItem&searchInterval=30";
        
        $storeinfo = array();
        $snoopy = new Snoopy;
        $snoopy->agent = $this->ua();
        $snoopy->rawheaders["X_FORWARDED_FOR"] = $this->radomIp();
        $snoopy->rawheaders["CLIENT-IP"] = $this->radomIp();
        
        try{
            $snoopy->fetch($surl);
            $response = $snoopy->results;
            $html_dom = new  ParserDom($response);
        }catch(\Exception $e){
            echo "\nfetch exception::prul $surl \nE:".$e->getMessage();
            if($loop>=3){
                return $result;
            }else{
                $loop++;
                sleep(rand(1,3));
                return $this->crawlStoreFeedbackInfo($sellerid, $loop );
            }
        }
        
        $feedscore_el = $html_dom->find("td[id=memberBadgeId]",0);
        if(!empty($feedscore_el)){
            $span0 = $feedscore_el->find("span.mbg-l",0);
            if(!empty($span0)){
                $fstxt = $span0->getPlainText();
                $fstxt = str_replace("(", "", $fstxt);
                $fstxt = trim( str_replace(")", "", $fstxt) );
                $storeinfo["feedbackscore"] = intval( str_replace(",", "", $fstxt));
            }
        }
        
        $positivefsp_el = $html_dom->find("div.g-novisited span.posFb12Months",0);
        if(!empty($positivefsp_el)){
            $pfs_txt = $positivefsp_el->getPlainText();
            $pfs_txt = trim(substr($pfs_txt, strpos($pfs_txt, ":")+1));
            $pfs_txt = trim(str_replace("%","",$pfs_txt));
            $storeinfo["positivefeedbackrate"] = intval( str_replace(",", "", $pfs_txt));
        }
        
        $generation_el = $html_dom->find("span.ds2arial13color3",0);
        if(!empty($generation_el)){
            $gtxt = trim($generation_el->getPlainText());            
            $gtime = strtotime( trim( substr($gtxt, 0, strpos($gtxt, " ")) ) );
            $gcountry = trim( substr($gtxt, strpos($gtxt,"in ")+3) );
            $storeinfo["generationtime"] = $gtime;
            $storeinfo["generationcountry"] = $gcountry;
        }
        
        $mpf_el = $html_dom->find("tr.fbsSmallYukon",0);
        if(!empty($mpf_el)){
            $mpfa_tds = $mpf_el->find("td");
            if(!empty($mpfa_tds)){
                $m1pf = 0;
                $m1a = $mpfa_tds[2]->find("a",0);
                if(!empty($m1a)){
                    $m1pf = intval(trim($m1a->getPlainText()));
                }
                
                $m6pf = 0;
                $m6a = $mpfa_tds[3]->find("a",0);
                if(!empty($m6a)){
                    $m6pf = intval(trim($m6a->getPlainText()));
                }
                
                $m12pf = 0;
                $m12a = $mpfa_tds[4]->find("a",0);
                if(!empty($m12a)){
                    $m12pf = intval(trim($m12a->getPlainText()));
                }                
            }
            $storeinfo["positivefeedbacks"] = array(
                "t"=>time(),
                "pf"=>array($m1pf,$m6pf,$m12pf)
            );
            $storeinfo["positivefeedback_1"] =$m1pf;
            $storeinfo["positivefeedback_6"] =$m6pf;
            $storeinfo["positivefeedback_12"] =$m12pf;
        }
       return $storeinfo; 
    }
    
    /**
     * 
     * http://www.ebay.com/sch/m.html?_nkw=&_armrs=1&_ipg=&_from=&_ssn=antiquemapsprints&_sop=10
http://www.ebay.com/sch/m.html?_nkw=&_armrs=1&_from=&_ssn=antiquemapsprints&_sop=10&_pgn=2&_skc=50&rt=nc
http://www.ebay.com/sch/m.html?_nkw=&_armrs=1&_from=&_ssn=antiquemapsprints&_sop=10&_pgn=3&_skc=100&rt=nc
     * @param unknown $sellerid 商家ID
     * @param unknown $year 由于商品的上架日期只有月日，所以需要一个年度标示。从第一页起，当年
     * *@param unknown $beforetime 用来进行比较的时间，只要获得的时间-（$before*365*3600）后，还大于这个时间，则$before++
     * @param unknown $page
     * @param number $count
     * @return NULL|multitype:number unknown NULL multitype:multitype:unknown string Ambigous <string, NULL>
     */
    function fetchProductByStore( $country, $seller, $page, $year, $beforetime, $count=50, $loop=0 ){
        if(empty($seller) || empty($page)) return null;
        $referer = null;        
        $surl = "http://www.ebay.com/sch/m.html?_nkw=&_armrs=1&_ipg=&_from=&_ssn=$seller&_sop=10&LH_BIN=1";
        if($page==1){
            //Don't do anything
        }elseif ($page==2){
            $referer = "http://www.ebay.com/sch/m.html?_nkw=&_armrs=1&_ipg=&_from=&_ssn=$seller&_sop=10&LH_BIN=1";
            $surl = "http://www.ebay.com/sch/m.html?_nkw=&_armrs=1&_from=&_ssn=$seller&_sop=10&_pgn=2&LH_BIN=1&_skc=$count&rt=nc";
        }else{
            $referer = "http://www.ebay.com/sch/m.html?_nkw=&_armrs=1&_from=&_ssn=$seller&_sop=10&_pgn=2&LH_BIN=1&_skc=".(($page-2)*$count);
            $surl = "http://www.ebay.com/sch/m.html?_nkw=&_armrs=1&_from=&_ssn=$seller&_sop=10&_pgn=2&LH_BIN=1&_skc=".(($page-1)*$count);
        }
        
        $snoopy = new Snoopy;
        $snoopy->agent = $this->ua();
        $snoopy->rawheaders["X_FORWARDED_FOR"] = $this->radomIp();
        $snoopy->rawheaders["CLIENT-IP"] = $this->radomIp();
        if(!empty($referer)){
            $snoopy->referer = $referer;
        }
        $surl = $this->getUrlByContry($country, $surl);
        echo "\n".$surl;
        
        try{
            $snoopy->fetch($surl);
            $response = $snoopy->results;
            $html_dom = new  ParserDom($response);
        }catch(\Exception $e){
            echo "\nfetch exception::prul $surl \nE:".$e->getMessage();
            if($loop>=3){
                return null;
            }else{
                $loop++;
                sleep(rand(1,3));
                return $this->fetchProductByStore($country, $seller, $page, $year, $beforetime, $count, $loop );
            }
        }        
        
        //feedbackscore
        $soib_el = $html_dom->find("div[id=soiBanner]",0);
        if(!empty($soib_el)){
            $fbs_el = $soib_el->find("span.mbg-l",1);
            if(!empty($fbs_el)){
                $fbs_ael = $fbs_el->find("a",0);
                $fbs_txt = trim( $fbs_ael->getPlainText() );
                $fbs_txt = trim(str_replace("(", "", $fbs_txt));
                $fbs_txt = trim(str_replace(")", "", $fbs_txt));
                $fbs_txt = trim(str_replace(",", "", $fbs_txt));
                $result["feedbackscore"] = intval($fbs_txt);
            }
        }        
        
        $cbrt = $html_dom->find("div[id=cbrt]",0);
        if(empty($cbrt)) return null;
        $rcnt = $cbrt->find("span.rcnt",0);
        if(!empty($rcnt)){
            $pns = trim( $rcnt->getPlainText() );
            $pns = trim( str_replace(",", "", $pns) );
            $result["listings"] = intval($pns);
        }
       
        $result["page"] = $page;
        $result["count"] = $count;
        $litems = array();
        $itemlist = $html_dom->find("ul[id=ListViewInner] li");
        if(!empty($itemlist)){
            foreach($itemlist as $item){
                $lid = $item->getAttr("listingid");
                if(empty($lid))continue;
                $ltitle_a = $item->find("h3.lvtitle a",0);
                if(empty($ltitle_a))continue;
                $ltitle = trim( $ltitle_a->getPlainText() );
                if(strpos($ltitle, "New listing")!==false){
                    $ltitle = trim(str_replace("New listing", "", $ltitle));
                }
                $lurl = trim( $ltitle_a->getAttr("href") );
        
                $imgel = $item->find("a.img img",0);
                $thumb = trim( $imgel->getAttr("src") );
        
                $lprice = 0;
                $lcurrency = "US";
                $lpspan = $item->find("li.lvprice span",0);
                if(!empty($lpspan)){
                    $lprice = trim($lpspan->getPlainText());
                    $lprice = trim( substr($lprice, strpos($lprice, " ")+1) );
                    $lprice = floatval( str_replace(",", "", $lprice) );
                    
                    $lcb = $lpspan->find("b",0);
                    if(!empty($lcb)){
                        $lcurrency = $lcb->getPlainText();
                    }
                }              
        
                $lformat = "BUY";
                $lfspan = $item->find("li.lvformat span",0);
                if(!empty($lfspan)){
                    $lftxt= trim( $lfspan->getPlainText() );
                    if(strpos($lftxt, "bid")!==false){
                        $lformat = "BID";
                    }
                }
                
                $genul = $item->find("ul.lvdetails",0);
                if(!empty($genul)){
                    $timeel = $genul->find("span.tme",0);
                    if(!empty($timeel)){
                        $timetxt = trim($timeel->getPlainText());
                        $time_y = str_replace(" ", "-$year ", $timetxt);
                        $generationtime = strtotime($time_y) ;
                        //最起码要大上7天，扣减天数时，要按年来算，有的是365天，有的是366天
                        if($generationtime>$beforetime+10*24*3600){
                            echo "\n----$lid------->$generationtime++++++before:::$beforetime";
                            $year--;
                            $generationtime = strtotime(str_replace(" ", "-$year ", $timetxt)) ;
                        }
                        //因为商品列表都是按照时间倒序排列的，最新的在最前面，所以给把gentime赋值给beforetime，一旦gentime>beforetime,说明又搜索到前一年商品了
                        $beforetime = $generationtime;   
                    }
                }
                $litems[]=array(
//                    "categoryid"=>$categoryid,
                    "lid"=>$lid,
                    "ltitle"=>$ltitle,
                    "seller"=>$seller,
                    "lurl"=>$lurl,
                    "lthumb"=>$thumb,
                    "lcurrency"=>$lcurrency,
                    "lprice"=>$lprice,
                    "lformat"=>$lformat,
                    "generationtime"=>$generationtime,
                    "country"=>array($country),
                );
//                if($before>=2)break;
            }           
        }
        $result["items"] = $litems;
        $result["year"] = $year;
        $result["beforetime"]=$beforetime;
        return $result;
    }
    
    /**
     * 根据指定的国家和商品，获得shipping价格
     * http://www.ebay.com/itm/getrates?item=322215834489&quantity=1&country=1&zipCode=&co=0&cb=jQuery1709778593553140667_1488336153360&_=1488340907192
     * @param unknown $itemid
     * @param unknown $country
     * @return free shipping 0，其它返回数字
     */
    function getShippingRates( $itemid, $country ){
        $shipping = -1;
        if(empty($itemid) || empty($country)) return $shipping;
        $code = $this->getCountryShipCode($country);
        if(empty($code)) return $shipping;
        
        $t = time()-rand(300,500);
        $reatesurl = "http://www.ebay.com/itm/getrates?item=$itemid&quantity=1&country=$code&zipCode=&co=0";
        $jcode = "jQuery1709".rand(70000,99999).rand(50000,99999).rand(10000,99999)."_$t".rand(100,999);
        $reatesurl .= "&cb=$jcode&_=".time().rand(100,999);

        $snoopy = new Snoopy;
        $snoopy->agent = $this->ua();
        $snoopy->rawheaders["X_FORWARDED_FOR"] = $this->radomIp();
        $snoopy->rawheaders["CLIENT-IP"] = $this->radomIp();
        $snoopy->fetch($reatesurl);
        $response = $snoopy->results;
        $response = substr($response, strpos($response, "(")+1);
        $response = substr($response, 0, strrpos($response, ")"));
        $res = json_decode($response,true);
        $shippingTable = $res["shippingTable"];
        
        if(empty($shippingTable)) return $shipping;
        $html_dom = new  ParserDom($shippingTable);
        
        $tr = $html_dom->find("tr",1);
        if(empty($tr)) return $shipping;
        $ftd = $tr->find("td",0);
        if(!empty($ftd)){
            $shippinttxt = trim( $ftd->find("div",0)->getPlainText() );//US $3.95//Free shipping
            echo "\n55[".strpos($shippinttxt, "Free")."]";
            if(strpos($shippinttxt, "Free")===false){
                //得到数字 //US $3.95
                $shipping = substr($shippinttxt,strpos($shippinttxt, " ")+2);                
            }else{
                $shipping = 0;
            }
            
        }
        return $shipping;
    }
    
    function ua(){
        $uas = array(
            "Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/55.0.2883.87 Safari/537.36",
            "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/51.0.2704.79 Safari/537.36 Edge/14.14393",
            "Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/45.0.2454.101 Safari/537.36 QIHU 360SE",
        );
    
        return array_rand($uas);
    }
    
    /**
     * 根据指定的国家，返回shipping国家代码
     * United States ：1；United Kingdom：3；Germany：77; France:71;  Australia:15; Italy:101; Spain:186; Canada:2  
     * @param unknown $country
     * @return number|Ambigous <number, mixed>
     */
    function getCountryShipCode( $country ){
        $code = -1;
        if(empty($country)) return $code;
        $codejson = '{"Afghanistan":"4","Albania":"5","Andorra":"8","Anguilla":"10","Antigua and Barbuda":"11","Argentina":"12","Armenia":"13","Aruba":"14","Australia":"15","Austria":"16","Azerbaijan Republic":"17","Bahamas":"18","Bahrain":"19","Bangladesh":"20","Barbados":"21","Belarus":"22","Belgium":"23","Belize":"24","Bermuda":"26","Bhutan":"27","Bolivia":"28","Bosnia and Herzegovina":"29","Brazil":"31","British Virgin Islands":"32","Brunei Darussalam":"33","Bulgaria":"34","Cambodia":"38","Canada":"2","Cayman Islands":"41","Chile":"44","China":"45","Colombia":"46","Costa Rica":"51","Croatia, Republic of":"53","Cyprus":"55","Czech Republic":"56","Denmark":"57","Dominica":"59","Dominican Republic":"60","Ecuador":"61","El Salvador":"63","Estonia":"66","Falkland Islands (Islas Malvinas)":"68","Finland":"70","France":"71","French Guiana":"72","Georgia":"76","Germany":"77","Gibraltar":"79","Greece":"80","Greenland":"81","Grenada":"82","Guadeloupe":"83","Guatemala":"85","Guernsey":"86","Guyana":"89","Haiti":"90","Honduras":"91","Hong Kong":"92","Hungary":"93","Iceland":"94","India":"95","Indonesia":"96","Iraq":"98","Ireland":"99","Israel":"100","Italy":"101","Jamaica":"102","Japan":"104","Jersey":"105","Jordan":"106","Kazakhstan":"107","Korea, South":"111","Kuwait":"112","Kyrgyzstan":"113","Laos":"114","Latvia":"115","Lebanon":"116","Liechtenstein":"120","Lithuania":"121","Luxembourg":"122","Macau":"123","Macedonia":"124","Malaysia":"127","Maldives":"128","Malta":"130","Martinique":"132","Mexico":"136","Moldova":"137","Monaco":"138","Mongolia":"139","Montenegro":"228","Montserrat":"140","Nepal":"145","Netherlands":"146","Netherlands Antilles":"147","Nicaragua":"150","Norway":"154","Oman":"155","Pakistan":"156","Panama":"158","Paraguay":"160","Peru":"161","Philippines":"162","Poland":"163","Portugal":"164","Puerto Rico":"165","Qatar":"166","Romania":"167","Russian Federation":"168","Saint Kitts-Nevis":"171","Saint Lucia":"172","Saint Pierre and Miquelon":"173","Saint Vincent and the Grenadines":"174","San Marino":"175","Saudi Arabia":"176","Serbia":"229","Singapore":"180","Slovakia":"181","Slovenia":"182","Spain":"186","Sri Lanka":"187","Suriname":"189","Sweden":"192","Switzerland":"193","Taiwan":"196","Tajikistan":"197","Thailand":"199","Trinidad and Tobago":"202","Turkey":"204","Turkmenistan":"205","Turks and Caicos Islands":"206","Ukraine":"209","United Arab Emirates":"210","United Kingdom":"3","United States":"1","Uruguay":"211","Uzbekistan":"212","Vatican City State":"214","Venezuela":"215","Vietnam":"216","Virgin Islands (U.S.)":"217","Yemen":"221"}';
        $codemap = json_decode($codejson,true) ;
        if(isset($codemap[$country])){
            $code = $codemap[$country];
        }
        return $code;
    }
    
    /**
     * 根据指定国家，获得EB国家域名
     * @param unknown $country US UK
     */
    function getUrlByContry( $country, $url ){
        $domain = "http://www.ebay.com";
         if(empty($url)) return null;
        if(!empty($country)){
            $country = strtolower($country);
            switch($country){
                case "us":
                    $domain = "http://www.ebay.com";
                    break;
                case "uk":
                    $domain = "http://www.ebay.co.uk";
                    break;
                default:
                    $domain = "http://www.ebay.com";
            }
        }
        //找到url中域名后面的第一个反斜线
        $domainpos = 0;
        if(strpos($url, "/")>0){
            $domainpos = strpos($url, "/",8);
        }
        return $domain.substr($url, $domainpos);
        
    }
    
    /**
     * 把特殊符号转换成货币单位名称
     * @param unknown $string
     * @return NULL|Ambigous <NULL, string>
     */
    function convertCurrency( $string ){
        $currency = null;
        if(empty($string)) return null;
        if(strpos($string, "&pound;")!==false){
            $currency="GBP";
        }elseif(strpos($string, "&euro;")!==false){
            $currency="EUR";
        }elseif(strpos($string, "&yen;")!==false){
            $currency="RMB";
        }elseif(strpos($string, "$")!==false){
            $currency="US";
        }else{
            $currency = null;
        }
        
        return $currency;
    }
    
    /**
     * 随机生成一个IP，用来伪造IP地址
     * 返回“201.76.55.198”形式地址
     */
    function radomIp(){
        $iprange = array(
            array('66.250.57.0','66.251.127.255'),
            array('66.251.193.0','66.251.215.255'),
            array('45.58.112.0','45.58.191.255'),
            array('45.59.190.0','45.60.255.255'),
            array('45.62.124.0','45.62.191.255'),
            array('45.63.0.0','45.63.23.255'),
            array('38.107.96.0','38.108.64.255'),
            array('38.110.72.0','38.110.79.255'),
            array('23.250.79.0','23.250.106.159'),
            array('23.254.65.80','23.254.82.63'),
            array('5.152.181.0','5.152.191.255'),
            array('5.255.4.0','5.255.63.255'),
            array('5.255.250.0','5.255.250.255'),
            array('6.0.0.0','8.0.15.255'),
            array('6.0.0.0','8.0.15.255'),
            array('4.69.153.194','5.0.0.0'),
            array('3.0.0.0','4.18.65.255'),
            array('4.18.68.0','4.34.129.255'),
            array('4.69.153.194','5.0.0.0'),
        );
    
        $ipitem = $iprange[rand(0,11)];
        $ipmin = ip2long($ipitem[0]);
        $ipmax = ip2long($ipitem[1]);
    
        $rip = rand($ipmin, $ipmax);
        return long2ip($rip);
    }
    
    /**
     * 获得指定需要抓取的分类列表，在分类抓取时，直接设置该分类的抓取标志
     * @return multitype:number
     */
    function getCrawlerTag(){
//         require_once  'vendor/autoload.php';
//        $filename = 'D:\phpworkspace\panda\category.xlsx';
         $filename = '/data/spider/category.xlsx';
        $objPHPExcelReader = \PHPExcel_IOFactory::load($filename);  //加载excel文件
        $tags = array();
        foreach($objPHPExcelReader->getWorksheetIterator() as $sheet)  //循环读取sheet
        {
            foreach($sheet->getRowIterator() as $row)  //逐行处理
            {
                if($row->getRowIndex()<1)  //确定从哪一行开始读取
                {
                    continue;
                }
                foreach($row->getCellIterator() as $cell)  //逐列读取
                {
                    $data = $cell->getValue(); //获取cell中数据
                    $tags[$data]=1;
                }
        
            }
        }
        $objPHPExcelReader->disconnectWorksheets();
        unset($objPHPExcelReader);
//        echo "\n".json_encode($tags);
       return $tags; 
    }
    
    function test(){
//        $esp = new EbaySpider();
//         //$res = $esp->getHeadCategory();
//         //[{"categoryname":"Antiquities","categoryurl":"http:\/\/www.ebay.com\/sch\/Antiquities\/37903\/i.html"},{"categoryname":"Architectural & Garden","categoryurl":"http:\/\/www.ebay.com\/sch\/Architectural-Garden\/4707\/i.html"},{"categoryname":"Asian Antiques","categoryurl":"http:\/\/www.ebay.com\/sch\/Asian-Antiques\/20082\/i.html"},{"categoryname":"Decorative Arts","categoryurl":"http:\/\/www.ebay.com\/sch\/Decorative-Arts\/20086\/i.html"},{"categoryname":"Ethnographic","categoryurl":"http:\/\/www.ebay.com\/sch\/Ethnographic\/2207\/i.html"},{"categoryname":"Home & Hearth","categoryurl":"http:\/\/www.ebay.com\/sch\/Home-Hearth\/163008\/i.html"},{"categoryname":"Incunabula","categoryurl":"http:\/\/www.ebay.com\/sch\/Incunabula\/22422\/i.html"},{"categoryname":"Linens & Textiles (Pre-1930)","categoryurl":"http:\/\/www.ebay.com\/sch\/Linens-Textiles-Pre-1930\/181677\/i.html"},{"categoryname":"Manuscripts","categoryurl":"http:\/\/www.ebay.com\/sch\/Manuscripts\/23048\/i.html"},{"categoryname":"Maps, Atlases & Globes","categoryurl":"http:\/\/www.ebay.com\/sch\/Maps-Atlases-Globes\/37958\/i.html"},{"categoryname":"Maritime","categoryurl":"http:\/\/www.ebay.com\/sch\/Maritime\/37965\/i.html"},{"categoryname":"Mercantile, Trades & Factories","categoryurl":"http:\/\/www.ebay.com\/sch\/Mercantile-Trades-Factories\/163091\/i.html"},{"categoryname":"Musical Instruments (Pre-1930)","categoryurl":"http:\/\/www.ebay.com\/sch\/Musical-Instruments-Pre-1930\/181726\/i.html"},{"categoryname":"Other Antiques","categoryurl":"http:\/\/www.ebay.com\/sch\/Other-Antiques\/12\/i.html"},{"categoryname":"Periods & Styles","categoryurl":"http:\/\/www.ebay.com\/sch\/Periods-Styles\/100927\/i.html"},{"categoryname":"Primitives","categoryurl":"http:\/\/www.ebay.com\/sch\/Primitives\/1217\/i.html"},{"categoryname":"Reproduction Antiques","categoryurl":"http:\/\/www.ebay.com\/sch\/Reproduction-Antiques\/22608\/i.html"},{"categoryname":"Restoration & Care","categoryurl":"http:\/\/www.ebay.com\/sch\/Restoration-Care\/163101\/i.html"},{"categoryname":"Rugs & Carpets","categoryurl":"http:\/\/www.ebay.com\/sch\/Rugs-Carpets\/37978\/i.html"},{"categoryname":"Science & Medicine (Pre-1930)","categoryurl":"http:\/\/www.ebay.com\/sch\/Science-Medicine-Pre-1930\/20094\/i.html"},{"categoryname":"Sewing (Pre-1930)","categoryurl":"http:\/\/www.ebay.com\/sch\/Sewing-Pre-1930\/156323\/i.html"},{"categoryname":"Silver","categoryurl":"http:\/\/www.ebay.com\/sch\/Silver\/20096\/i.html"},{"categoryname":"Art from Dealers & Resellers","categoryurl":"http:\/\/www.ebay.com\/sch\/Art-from-Dealers-Resellers\/158658\/i.html"},{"categoryname":"Direct from the Artist","categoryurl":"http:\/\/www.ebay.com\/sch\/Direct-from-the-Artist\/60435\/i.html"},{"categoryname":"Baby Gear","categoryurl":"http:\/\/www.ebay.com\/sch\/Baby-Gear\/100223\/i.html"},{"categoryname":"Baby Safety & Health","categoryurl":"http:\/\/www.ebay.com\/sch\/Baby-Safety-Health\/20433\/i.html"},{"categoryname":"Bathing & Grooming","categoryurl":"http:\/\/www.ebay.com\/sch\/Bathing-Grooming\/20394\/i.html"},{"categoryname":"Car Safety Seats","categoryurl":"http:\/\/www.ebay.com\/sch\/Car-Safety-Seats\/66692\/i.html"},{"categoryname":"Carriers, Slings & Backpacks","categoryurl":"http:\/\/www.ebay.com\/sch\/Carriers-Slings-Backpacks\/100982\/i.html"},{"categoryname":"Diapering","categoryurl":"http:\/\/www.ebay.com\/sch\/Diapering\/45455\/i.html"},{"categoryname":"Feeding","categoryurl":"http:\/\/www.ebay.com\/sch\/Feeding\/20400\/i.html"},{"categoryname":"Keepsakes & Baby Announcements","categoryurl":"http:\/\/www.ebay.com\/sch\/Keepsakes-Baby-Announcements\/117388\/i.html"},{"categoryname":"Nursery Bedding","categoryurl":"http:\/\/www.ebay.com\/sch\/Nursery-Bedding\/20416\/i.html"},{"categoryname":"Nursery D\u00e9cor","categoryurl":"http:\/\/www.ebay.com\/sch\/Nursery-Decor\/66697\/i.html"},{"categoryname":"Nursery Furniture","categoryurl":"http:\/\/www.ebay.com\/sch\/Nursery-Furniture\/20422\/i.html"},{"categoryname":"Other Baby","categoryurl":"http:\/\/www.ebay.com\/sch\/Other-Baby\/1261\/i.html"},{"categoryname":"Potty Training","categoryurl":"http:\/\/www.ebay.com\/sch\/Potty-Training\/37631\/i.html"},{"categoryname":"Strollers & Accessories","categoryurl":"http:\/\/www.ebay.com\/sch\/Strollers-Accessories\/66698\/i.html"},{"categoryname":"Toys for Baby","categoryurl":"http:\/\/www.ebay.com\/sch\/Toys-for-Baby\/19068\/i.html"},{"categoryname":"Accessories","categoryurl":"http:\/\/www.ebay.com\/sch\/Accessories\/45110\/i.html"},{"categoryname":"Antiquarian & Collectible","categoryurl":"http:\/\/www.ebay.com\/sch\/Antiquarian-Collectible\/29223\/i.html"},{"categoryname":"Audiobooks","categoryurl":"http:\/\/www.ebay.com\/sch\/Audiobooks\/29792\/i.html"},{"categoryname":"Catalogs","categoryurl":"http:\/\/www.ebay.com\/sch\/Catalogs\/118254\/i.html"},{"categoryname":"Children & Young Adults","categoryurl":"http:\/\/www.ebay.com\/sch\/Children-Young-Adults\/182882\/i.html"},{"categoryname":"Cookbooks","categoryurl":"http:\/\/www.ebay.com\/sch\/Cookbooks\/11104\/i.html"},{"categoryname":"Fiction & Literature","categoryurl":"http:\/\/www.ebay.com\/sch\/Fiction-Literature\/171228\/i.html"},{"categoryname":"Magazine Back Issues","categoryurl":"http:\/\/www.ebay.com\/sch\/Magazine-Back-Issues\/280\/i.html"},{"categoryname":"Nonfiction","categoryurl":"http:\/\/www.ebay.com\/sch\/Nonfiction\/171243\/i.html"},{"categoryname":"Other Books","categoryurl":"http:\/\/www.ebay.com\/sch\/Other-Books\/268\/i.html"},{"categoryname":"Textbooks, Education","categoryurl":"http:\/\/www.ebay.com\/sch\/Textbooks-Education\/2228\/i.html"},{"categoryname":"Wholesale & Bulk Lots","categoryurl":"http:\/\/www.ebay.com\/sch\/Wholesale-Bulk-Lots\/29399\/i.html"},{"categoryname":"Agriculture & Forestry","categoryurl":"http:\/\/www.ebay.com\/sch\/Agriculture-Forestry\/11748\/i.html"},{"categoryname":"Automation, Motors & Drives","categoryurl":"http:\/\/www.ebay.com\/sch\/Automation-Motors-Drives\/42892\/i.html"},{"categoryname":"Construction","categoryurl":"http:\/\/www.ebay.com\/sch\/Construction\/11765\/i.html"},{"categoryname":"Electrical & Test Equipment","categoryurl":"http:\/\/www.ebay.com\/sch\/Electrical-Test-Equipment\/92074\/i.html"},{"categoryname":"Fuel & Energy","categoryurl":"http:\/\/www.ebay.com\/sch\/Fuel-Energy\/159693\/i.html"},{"categoryname":"Healthcare, Lab & Life Science","categoryurl":"http:\/\/www.ebay.com\/sch\/Healthcare-Lab-Life-Science\/11815\/i.html"},{"categoryname":"Heavy Equipment","categoryurl":"http:\/\/www.ebay.com\/sch\/Heavy-Equipment\/177641\/i.html"},{"categoryname":"Heavy Equipment Attachments","categoryurl":"http:\/\/www.ebay.com\/sch\/Heavy-Equipment-Attachments\/177647\/i.html"},{"categoryname":"Heavy Equipment Parts & Accs","categoryurl":"http:\/\/www.ebay.com\/sch\/Heavy-Equipment-Parts-Accs\/41489\/i.html"},{"categoryname":"Light Equipment & Tools","categoryurl":"http:\/\/www.ebay.com\/sch\/Light-Equipment-Tools\/61573\/i.html"},{"categoryname":"Manufacturing & Metalworking","categoryurl":"http:\/\/www.ebay.com\/sch\/Manufacturing-Metalworking\/11804\/i.html"},{"categoryname":"MRO & Industrial Supply","categoryurl":"http:\/\/www.ebay.com\/sch\/MRO-Industrial-Supply\/1266\/i.html"},{"categoryname":"Office","categoryurl":"http:\/\/www.ebay.com\/sch\/Office\/25298\/i.html"},{"categoryname":"Other Business & Industrial","categoryurl":"http:\/\/www.ebay.com\/sch\/Other-Business-Industrial\/26261\/i.html"},{"categoryname":"Packing & Shipping","categoryurl":"http:\/\/www.ebay.com\/sch\/Packing-Shipping\/19273\/i.html"},{"categoryname":"Printing & Graphic Arts","categoryurl":"http:\/\/www.ebay.com\/sch\/Printing-Graphic-Arts\/26238\/i.html"},{"categoryname":"Restaurant & Catering","categoryurl":"http:\/\/www.ebay.com\/sch\/Restaurant-Catering\/11874\/i.html"},{"categoryname":"Retail & Services","categoryurl":"http:\/\/www.ebay.com\/sch\/Retail-Services\/11890\/i.html"},{"categoryname":"Websites & Businesses for Sale","categoryurl":"http:\/\/www.ebay.com\/sch\/Websites-Businesses-for-Sale\/11759\/i.html"},{"categoryname":"Binoculars & Telescopes","categoryurl":"http:\/\/www.ebay.com\/sch\/Binoculars-Telescopes\/28179\/i.html"},{"categoryname":"Camcorders","categoryurl":"http:\/\/www.ebay.com\/sch\/Camcorders\/11724\/i.html"},{"categoryname":"Camera & Photo Accessories","categoryurl":"http:\/\/www.ebay.com\/sch\/Camera-Photo-Accessories\/15200\/i.html"},{"categoryname":"Camera Drone Parts & Accs","categoryurl":"http:\/\/www.ebay.com\/sch\/Camera-Drone-Parts-Accs\/182969\/i.html"},{"categoryname":"Camera Drones","categoryurl":"http:\/\/www.ebay.com\/sch\/Camera-Drones\/179697\/i.html"},{"categoryname":"Camera Manuals & Guides","categoryurl":"http:\/\/www.ebay.com\/sch\/Camera-Manuals-Guides\/4684\/i.html"},{"categoryname":"Digital Cameras","categoryurl":"http:\/\/www.ebay.com\/sch\/Digital-Cameras\/31388\/i.html"},{"categoryname":"Digital Photo Frames","categoryurl":"http:\/\/www.ebay.com\/sch\/Digital-Photo-Frames\/150044\/i.html"},{"categoryname":"Film Photography","categoryurl":"http:\/\/www.ebay.com\/sch\/Film-Photography\/69323\/i.html"},{"categoryname":"Flashes & Flash Accessories","categoryurl":"http:\/\/www.ebay.com\/sch\/Flashes-Flash-Accessories\/64353\/i.html"},{"categoryname":"Lenses & Filters","categoryurl":"http:\/\/www.ebay.com\/sch\/Lenses-Filters\/78997\/i.html"},{"categoryname":"Lighting & Studio","categoryurl":"http:\/\/www.ebay.com\/sch\/Lighting-Studio\/30078\/i.html"},{"categoryname":"Other Cameras & Photo","categoryurl":"http:\/\/www.ebay.com\/sch\/Other-Cameras-Photo\/27432\/i.html"},{"categoryname":"Replacement Parts & Tools","categoryurl":"http:\/\/www.ebay.com\/sch\/Replacement-Parts-Tools\/182074\/i.html"},{"categoryname":"Tripods & Supports","categoryurl":"http:\/\/www.ebay.com\/sch\/Tripods-Supports\/30090\/i.html"},{"categoryname":"Video Production & Editing","categoryurl":"http:\/\/www.ebay.com\/sch\/Video-Production-Editing\/21162\/i.html"},{"categoryname":"Vintage Movie & Photography","categoryurl":"http:\/\/www.ebay.com\/sch\/Vintage-Movie-Photography\/3326\/i.html"},{"categoryname":"Cell Phone & Smartphone Parts","categoryurl":"http:\/\/www.ebay.com\/sch\/Cell-Phone-Smartphone-Parts\/43304\/i.html"},{"categoryname":"Cell Phone Accessories","categoryurl":"http:\/\/www.ebay.com\/sch\/Cell-Phone-Accessories\/9394\/i.html"},{"categoryname":"Cell Phones & Smartphones","categoryurl":"http:\/\/www.ebay.com\/sch\/Cell-Phones-Smartphones\/9355\/i.html"},{"categoryname":"Display Phones","categoryurl":"http:\/\/www.ebay.com\/sch\/Display-Phones\/136699\/i.html"},{"categoryname":"Other Cell Phones & Accs","categoryurl":"http:\/\/www.ebay.com\/sch\/Other-Cell-Phones-Accs\/42428\/i.html"},{"categoryname":"Phone Cards & SIM Cards","categoryurl":"http:\/\/www.ebay.com\/sch\/Phone-Cards-SIM-Cards\/146492\/i.html"},{"categoryname":"Smart Watch Accessories","categoryurl":"http:\/\/www.ebay.com\/sch\/Smart-Watch-Accessories\/182064\/i.html"},{"categoryname":"Smart Watches","categoryurl":"http:\/\/www.ebay.com\/sch\/Smart-Watches\/178893\/i.html"},{"categoryname":"Vintage Cell Phones","categoryurl":"http:\/\/www.ebay.com\/sch\/Vintage-Cell-Phones\/182073\/i.html"},{"categoryname":"Baby & Toddler Clothing","categoryurl":"http:\/\/www.ebay.com\/sch\/Baby-Toddler-Clothing\/3082\/i.html"},{"categoryname":"Costumes, Reenactment, Theater","categoryurl":"http:\/\/www.ebay.com\/sch\/Costumes-Reenactment-Theater\/163147\/i.html"},{"categoryname":"Cultural & Ethnic Clothing","categoryurl":"http:\/\/www.ebay.com\/sch\/Cultural-Ethnic-Clothing\/155240\/i.html"},{"categoryname":"Dancewear","categoryurl":"http:\/\/www.ebay.com\/sch\/Dancewear\/112425\/i.html"},{"categoryname":"Kids' Clothing, Shoes & Accs","categoryurl":"http:\/\/www.ebay.com\/sch\/Kids-Clothing-Shoes-Accs\/171146\/i.html"},{"categoryname":"Men's Accessories","categoryurl":"http:\/\/www.ebay.com\/sch\/Mens-Accessories\/4250\/i.html"},{"categoryname":"Men's Clothing","categoryurl":"http:\/\/www.ebay.com\/sch\/Mens-Clothing\/1059\/i.html"},{"categoryname":"Men's Shoes","categoryurl":"http:\/\/www.ebay.com\/sch\/Mens-Shoes\/93427\/i.html"},{"categoryname":"Uniforms & Work Clothing","categoryurl":"http:\/\/www.ebay.com\/sch\/Uniforms-Work-Clothing\/28015\/i.html"},{"categoryname":"Unisex Clothing, Shoes & Accs","categoryurl":"http:\/\/www.ebay.com\/sch\/Unisex-Clothing-Shoes-Accs\/155184\/i.html"},{"categoryname":"Vintage","categoryurl":"http:\/\/www.ebay.com\/sch\/Vintage\/175759\/i.html"},{"categoryname":"Wedding & Formal Occasion","categoryurl":"http:\/\/www.ebay.com\/sch\/Wedding-Formal-Occasion\/3259\/i.html"},{"categoryname":"Wholesale, Large & Small Lots","categoryurl":"http:\/\/www.ebay.com\/sch\/Wholesale-Large-Small-Lots\/41964\/i.html"},{"categoryname":"Women's Accessories","categoryurl":"http:\/\/www.ebay.com\/sch\/Womens-Accessories\/4251\/i.html"},{"categoryname":"Women's Clothing","categoryurl":"http:\/\/www.ebay.com\/sch\/Womens-Clothing\/15724\/i.html"},{"categoryname":"Women's Handbags & Bags","categoryurl":"http:\/\/www.ebay.com\/sch\/Womens-Handbags-Bags\/169291\/i.html"},{"categoryname":"Women's Shoes","categoryurl":"http:\/\/www.ebay.com\/sch\/Womens-Shoes\/3034\/i.html"},{"categoryname":"Bullion","categoryurl":"http:\/\/www.ebay.com\/sch\/Bullion\/39482\/i.html"},{"categoryname":"Coins: Ancient","categoryurl":"http:\/\/www.ebay.com\/sch\/Coins-Ancient\/4733\/i.html"},{"categoryname":"Coins: Canada","categoryurl":"http:\/\/www.ebay.com\/sch\/Coins-Canada\/3377\/i.html"},{"categoryname":"Coins: Medieval","categoryurl":"http:\/\/www.ebay.com\/sch\/Coins-Medieval\/18466\/i.html"},{"categoryname":"Coins: US","categoryurl":"http:\/\/www.ebay.com\/sch\/Coins-US\/253\/i.html"},{"categoryname":"Coins: World","categoryurl":"http:\/\/www.ebay.com\/sch\/Coins-World\/256\/i.html"},{"categoryname":"Exonumia","categoryurl":"http:\/\/www.ebay.com\/sch\/Exonumia\/3452\/i.html"},{"categoryname":"Other Coins & Paper Money","categoryurl":"http:\/\/www.ebay.com\/sch\/Other-Coins-Paper-Money\/169305\/i.html"},{"categoryname":"Paper Money: US","categoryurl":"http:\/\/www.ebay.com\/sch\/Paper-Money-US\/3412\/i.html"},{"categoryname":"Paper Money: World","categoryurl":"http:\/\/www.ebay.com\/sch\/Paper-Money-World\/3411\/i.html"},{"categoryname":"Publications & Supplies","categoryurl":"http:\/\/www.ebay.com\/sch\/Publications-Supplies\/83274\/i.html"},{"categoryname":"Stocks & Bonds, Scripophily","categoryurl":"http:\/\/www.ebay.com\/sch\/Stocks-Bonds-Scripophily\/3444\/i.html"},{"categoryname":"Virtual Currency","categoryurl":"http:\/\/www.ebay.com\/sch\/Virtual-Currency\/179197\/i.html"},{"categoryname":"Advertising","categoryurl":"http:\/\/www.ebay.com\/sch\/Advertising\/34\/i.html"},{"categoryname":"Animals","categoryurl":"http:\/\/www.ebay.com\/sch\/Animals\/1335\/i.html"},{"categoryname":"Animation Art & Characters","categoryurl":"http:\/\/www.ebay.com\/sch\/Animation-Art-Characters\/13658\/i.html"},{"categoryname":"Arcade, Jukeboxes & Pinball","categoryurl":"http:\/\/www.ebay.com\/sch\/Arcade-Jukeboxes-Pinball\/66502\/i.html"},{"categoryname":"Autographs","categoryurl":"http:\/\/www.ebay.com\/sch\/Autographs\/14429\/i.html"},{"categoryname":"Banks, Registers & Vending","categoryurl":"http:\/\/www.ebay.com\/sch\/Banks-Registers-Vending\/66503\/i.html"},{"categoryname":"Barware","categoryurl":"http:\/\/www.ebay.com\/sch\/Barware\/3265\/i.html"},{"categoryname":"Beads","categoryurl":"http:\/\/www.ebay.com\/sch\/Beads\/156277\/i.html"},{"categoryname":"Bottles & Insulators","categoryurl":"http:\/\/www.ebay.com\/sch\/Bottles-Insulators\/29797\/i.html"},{"categoryname":"Breweriana, Beer","categoryurl":"http:\/\/www.ebay.com\/sch\/Breweriana-Beer\/562\/i.html"},{"categoryname":"Casino","categoryurl":"http:\/\/www.ebay.com\/sch\/Casino\/898\/i.html"},{"categoryname":"Clocks","categoryurl":"http:\/\/www.ebay.com\/sch\/Clocks\/397\/i.html"},{"categoryname":"Comics","categoryurl":"http:\/\/www.ebay.com\/sch\/Comics\/63\/i.html"},{"categoryname":"Credit, Charge Cards","categoryurl":"http:\/\/www.ebay.com\/sch\/Credit-Charge-Cards\/1462\/i.html"},{"categoryname":"Cultures & Ethnicities","categoryurl":"http:\/\/www.ebay.com\/sch\/Cultures-Ethnicities\/3913\/i.html"},{"categoryname":"Decorative Collectibles","categoryurl":"http:\/\/www.ebay.com\/sch\/Decorative-Collectibles\/13777\/i.html"},{"categoryname":"Disneyana","categoryurl":"http:\/\/www.ebay.com\/sch\/Disneyana\/137\/i.html"},{"categoryname":"Fantasy, Mythical & Magic","categoryurl":"http:\/\/www.ebay.com\/sch\/Fantasy-Mythical-Magic\/10860\/i.html"},{"categoryname":"Historical Memorabilia","categoryurl":"http:\/\/www.ebay.com\/sch\/Historical-Memorabilia\/13877\/i.html"},{"categoryname":"Holiday & Seasonal","categoryurl":"http:\/\/www.ebay.com\/sch\/Holiday-Seasonal\/907\/i.html"},{"categoryname":"Kitchen & Home","categoryurl":"http:\/\/www.ebay.com\/sch\/Kitchen-Home\/13905\/i.html"},{"categoryname":"Knives, Swords & Blades","categoryurl":"http:\/\/www.ebay.com\/sch\/Knives-Swords-Blades\/1401\/i.html"},{"categoryname":"Lamps, Lighting","categoryurl":"http:\/\/www.ebay.com\/sch\/Lamps-Lighting\/1404\/i.html"},{"categoryname":"Linens & Textiles (1930-Now)","categoryurl":"http:\/\/www.ebay.com\/sch\/Linens-Textiles-1930-Now\/940\/i.html"},{"categoryname":"Metalware","categoryurl":"http:\/\/www.ebay.com\/sch\/Metalware\/1430\/i.html"},{"categoryname":"Militaria","categoryurl":"http:\/\/www.ebay.com\/sch\/Militaria\/13956\/i.html"},{"categoryname":"Non-Sport Trading Cards","categoryurl":"http:\/\/www.ebay.com\/sch\/Non-Sport-Trading-Cards\/182982\/i.html"},{"categoryname":"Paper","categoryurl":"http:\/\/www.ebay.com\/sch\/Paper\/124\/i.html"},{"categoryname":"Pens & Writing Instruments","categoryurl":"http:\/\/www.ebay.com\/sch\/Pens-Writing-Instruments\/966\/i.html"},{"categoryname":"Pez, Keychains, Promo Glasses","categoryurl":"http:\/\/www.ebay.com\/sch\/Pez-Keychains-Promo-Glasses\/14005\/i.html"},{"categoryname":"Phone Cards","categoryurl":"http:\/\/www.ebay.com\/sch\/Phone-Cards\/1463\/i.html"},{"categoryname":"Photographic Images","categoryurl":"http:\/\/www.ebay.com\/sch\/Photographic-Images\/14277\/i.html"},{"categoryname":"Pinbacks, Bobbles, Lunchboxes","categoryurl":"http:\/\/www.ebay.com\/sch\/Pinbacks-Bobbles-Lunchboxes\/39507\/i.html"},{"categoryname":"Postcards","categoryurl":"http:\/\/www.ebay.com\/sch\/Postcards\/914\/i.html"},{"categoryname":"Radio, Phonograph, TV, Phone","categoryurl":"http:\/\/www.ebay.com\/sch\/Radio-Phonograph-TV-Phone\/29832\/i.html"},{"categoryname":"Religion & Spirituality","categoryurl":"http:\/\/www.ebay.com\/sch\/Religion-Spirituality\/1446\/i.html"},{"categoryname":"Rocks, Fossils & Minerals","categoryurl":"http:\/\/www.ebay.com\/sch\/Rocks-Fossils-Minerals\/3213\/i.html"},{"categoryname":"Science & Medicine (1930-Now)","categoryurl":"http:\/\/www.ebay.com\/sch\/Science-Medicine-1930-Now\/412\/i.html"},{"categoryname":"Science Fiction & Horror","categoryurl":"http:\/\/www.ebay.com\/sch\/Science-Fiction-Horror\/152\/i.html"},{"categoryname":"Sewing (1930-Now)","categoryurl":"http:\/\/www.ebay.com\/sch\/Sewing-1930-Now\/113\/i.html"},{"categoryname":"Souvenirs & Travel Memorabilia","categoryurl":"http:\/\/www.ebay.com\/sch\/Souvenirs-Travel-Memorabilia\/165800\/i.html"},{"categoryname":"Tobacciana","categoryurl":"http:\/\/www.ebay.com\/sch\/Tobacciana\/593\/i.html"},{"categoryname":"Tools, Hardware & Locks","categoryurl":"http:\/\/www.ebay.com\/sch\/Tools-Hardware-Locks\/13849\/i.html"},{"categoryname":"Transportation","categoryurl":"http:\/\/www.ebay.com\/sch\/Transportation\/417\/i.html"},{"categoryname":"Vanity, Perfume & Shaving","categoryurl":"http:\/\/www.ebay.com\/sch\/Vanity-Perfume-Shaving\/597\/i.html"},{"categoryname":"Vintage, Retro, Mid-Century","categoryurl":"http:\/\/www.ebay.com\/sch\/Vintage-Retro-Mid-Century\/69851\/i.html"},{"categoryname":"3D Printers & Supplies","categoryurl":"http:\/\/www.ebay.com\/sch\/3D-Printers-Supplies\/183062\/i.html"},{"categoryname":"Computer Cables & Connectors","categoryurl":"http:\/\/www.ebay.com\/sch\/Computer-Cables-Connectors\/182094\/i.html"},{"categoryname":"Computer Components & Parts","categoryurl":"http:\/\/www.ebay.com\/sch\/Computer-Components-Parts\/175673\/i.html"},{"categoryname":"Desktops & All-In-Ones","categoryurl":"http:\/\/www.ebay.com\/sch\/Desktops-All-In-Ones\/171957\/i.html"},{"categoryname":"Drives, Storage & Blank Media","categoryurl":"http:\/\/www.ebay.com\/sch\/Drives-Storage-Blank-Media\/165\/i.html"},{"categoryname":"Enterprise Networking, Servers","categoryurl":"http:\/\/www.ebay.com\/sch\/Enterprise-Networking-Servers\/175698\/i.html"},{"categoryname":"Home Networking & Connectivity","categoryurl":"http:\/\/www.ebay.com\/sch\/Home-Networking-Connectivity\/11176\/i.html"},{"categoryname":"Keyboards, Mice & Pointers","categoryurl":"http:\/\/www.ebay.com\/sch\/Keyboards-Mice-Pointers\/3676\/i.html"},{"categoryname":"Laptop & Desktop Accessories","categoryurl":"http:\/\/www.ebay.com\/sch\/Laptop-Desktop-Accessories\/31530\/i.html"},{"categoryname":"Laptops & Netbooks","categoryurl":"http:\/\/www.ebay.com\/sch\/Laptops-Netbooks\/175672\/i.html"},{"categoryname":"Manuals & Resources","categoryurl":"http:\/\/www.ebay.com\/sch\/Manuals-Resources\/3516\/i.html"},{"categoryname":"Monitors, Projectors & Accs","categoryurl":"http:\/\/www.ebay.com\/sch\/Monitors-Projectors-Accs\/162497\/i.html"},{"categoryname":"Other Computers & Networking","categoryurl":"http:\/\/www.ebay.com\/sch\/Other-Computers-Networking\/162\/i.html"},{"categoryname":"Power Protection, Distribution","categoryurl":"http:\/\/www.ebay.com\/sch\/Power-Protection-Distribution\/86722\/i.html"},{"categoryname":"Printers, Scanners & Supplies","categoryurl":"http:\/\/www.ebay.com\/sch\/Printers-Scanners-Supplies\/171961\/i.html"},{"categoryname":"Software","categoryurl":"http:\/\/www.ebay.com\/sch\/Software\/18793\/i.html"},{"categoryname":"Tablet & eBook Reader Accs","categoryurl":"http:\/\/www.ebay.com\/sch\/Tablet-eBook-Reader-Accs\/176970\/i.html"},{"categoryname":"Tablet & eBook Reader Parts","categoryurl":"http:\/\/www.ebay.com\/sch\/Tablet-eBook-Reader-Parts\/180235\/i.html"},{"categoryname":"Tablets & eBook Readers","categoryurl":"http:\/\/www.ebay.com\/sch\/Tablets-eBook-Readers\/171485\/i.html"},{"categoryname":"Vintage Computing","categoryurl":"http:\/\/www.ebay.com\/sch\/Vintage-Computing\/11189\/i.html"},{"categoryname":"Gadgets & Other Electronics","categoryurl":"http:\/\/www.ebay.com\/sch\/Gadgets-Other-Electronics\/14948\/i.html"},{"categoryname":"Home Automation","categoryurl":"http:\/\/www.ebay.com\/sch\/Home-Automation\/50582\/i.html"},{"categoryname":"Home Surveillance","categoryurl":"http:\/\/www.ebay.com\/sch\/Home-Surveillance\/48633\/i.html"},{"categoryname":"Home Telephones & Accessories","categoryurl":"http:\/\/www.ebay.com\/sch\/Home-Telephones-Accessories\/3286\/i.html"},{"categoryname":"Multipurpose Batteries & Power","categoryurl":"http:\/\/www.ebay.com\/sch\/Multipurpose-Batteries-Power\/48446\/i.html"},{"categoryname":"Portable Audio & Headphones","categoryurl":"http:\/\/www.ebay.com\/sch\/Portable-Audio-Headphones\/15052\/i.html"},{"categoryname":"Radio Communication","categoryurl":"http:\/\/www.ebay.com\/sch\/Radio-Communication\/1500\/i.html"},{"categoryname":"TV, Video & Home Audio","categoryurl":"http:\/\/www.ebay.com\/sch\/TV-Video-Home-Audio\/32852\/i.html"},{"categoryname":"Vehicle Electronics & GPS","categoryurl":"http:\/\/www.ebay.com\/sch\/Vehicle-Electronics-GPS\/3270\/i.html"},{"categoryname":"Vintage Electronics","categoryurl":"http:\/\/www.ebay.com\/sch\/Vintage-Electronics\/183077\/i.html"},{"categoryname":"Virtual Reality","categoryurl":"http:\/\/www.ebay.com\/sch\/Virtual-Reality\/183067\/i.html"},{"categoryname":"Art Supplies","categoryurl":"http:\/\/www.ebay.com\/sch\/Art-Supplies\/11783\/i.html"},{"categoryname":"Beads & Jewelry Making","categoryurl":"http:\/\/www.ebay.com\/sch\/Beads-Jewelry-Making\/31723\/i.html"},{"categoryname":"Fabric","categoryurl":"http:\/\/www.ebay.com\/sch\/Fabric\/28162\/i.html"},{"categoryname":"Fabric Painting & Decorating","categoryurl":"http:\/\/www.ebay.com\/sch\/Fabric-Painting-Decorating\/183118\/i.html"},{"categoryname":"Glass & Mosaics","categoryurl":"http:\/\/www.ebay.com\/sch\/Glass-Mosaics\/163778\/i.html"},{"categoryname":"Handcrafted & Finished Pieces","categoryurl":"http:\/\/www.ebay.com\/sch\/Handcrafted-Finished-Pieces\/71183\/i.html"},{"categoryname":"Home Arts & Crafts","categoryurl":"http:\/\/www.ebay.com\/sch\/Home-Arts-Crafts\/160667\/i.html"},{"categoryname":"Kids' Crafts","categoryurl":"http:\/\/www.ebay.com\/sch\/Kids-Crafts\/116652\/i.html"},{"categoryname":"Leathercrafts","categoryurl":"http:\/\/www.ebay.com\/sch\/Leathercrafts\/28131\/i.html"},{"categoryname":"Multi-Purpose Craft Supplies","categoryurl":"http:\/\/www.ebay.com\/sch\/Multi-Purpose-Craft-Supplies\/28102\/i.html"},{"categoryname":"Needlecrafts & Yarn","categoryurl":"http:\/\/www.ebay.com\/sch\/Needlecrafts-Yarn\/160706\/i.html"},{"categoryname":"Other Crafts","categoryurl":"http:\/\/www.ebay.com\/sch\/Other-Crafts\/75576\/i.html"},{"categoryname":"Scrapbooking & Paper Crafts","categoryurl":"http:\/\/www.ebay.com\/sch\/Scrapbooking-Paper-Crafts\/11788\/i.html"},{"categoryname":"Sculpting, Molding & Ceramics","categoryurl":"http:\/\/www.ebay.com\/sch\/Sculpting-Molding-Ceramics\/183302\/i.html"},{"categoryname":"Sewing","categoryurl":"http:\/\/www.ebay.com\/sch\/Sewing\/160737\/i.html"},{"categoryname":"Stamping & Embossing","categoryurl":"http:\/\/www.ebay.com\/sch\/Stamping-Embossing\/3122\/i.html"},{"categoryname":"Bear Making Supplies","categoryurl":"http:\/\/www.ebay.com\/sch\/Bear-Making-Supplies\/50253\/i.html"},{"categoryname":"Bears","categoryurl":"http:\/\/www.ebay.com\/sch\/Bears\/386\/i.html"},{"categoryname":"Dollhouse Miniatures","categoryurl":"http:\/\/www.ebay.com\/sch\/Dollhouse-Miniatures\/1202\/i.html"},{"categoryname":"Dolls","categoryurl":"http:\/\/www.ebay.com\/sch\/Dolls\/238\/i.html"},{"categoryname":"Paper Dolls","categoryurl":"http:\/\/www.ebay.com\/sch\/Paper-Dolls\/2440\/i.html"},{"categoryname":"DVDs & Blu-ray Discs","categoryurl":"http:\/\/www.ebay.com\/sch\/DVDs-Blu-ray-Discs\/617\/i.html"},{"categoryname":"Film Stock","categoryurl":"http:\/\/www.ebay.com\/sch\/Film-Stock\/63821\/i.html"},{"categoryname":"Laserdiscs","categoryurl":"http:\/\/www.ebay.com\/sch\/Laserdiscs\/381\/i.html"},{"categoryname":"Storage & Media Accessories","categoryurl":"http:\/\/www.ebay.com\/sch\/Storage-Media-Accessories\/52554\/i.html"},{"categoryname":"UMDs","categoryurl":"http:\/\/www.ebay.com\/sch\/UMDs\/132975\/i.html"},{"categoryname":"VHS Tapes","categoryurl":"http:\/\/www.ebay.com\/sch\/VHS-Tapes\/309\/i.html"},{"categoryname":"Boats","categoryurl":"http:\/\/www.ebay.com\/sch\/Boats\/26429\/i.html"},{"categoryname":"Cars & Trucks","categoryurl":"http:\/\/www.ebay.com\/sch\/Cars-Trucks\/6001\/i.html"},{"categoryname":"Motorcycles","categoryurl":"http:\/\/www.ebay.com\/sch\/Motorcycles\/6024\/i.html"},{"categoryname":"Other Vehicles & Trailers","categoryurl":"http:\/\/www.ebay.com\/sch\/Other-Vehicles-Trailers\/6038\/i.html"},{"categoryname":"Parts & Accessories","categoryurl":"http:\/\/www.ebay.com\/sch\/Parts-Accessories\/6028\/i.html"},{"categoryname":"Powersports","categoryurl":"http:\/\/www.ebay.com\/sch\/Powersports\/66466\/i.html"},{"categoryname":"Movie Memorabilia","categoryurl":"http:\/\/www.ebay.com\/sch\/Movie-Memorabilia\/196\/i.html"},{"categoryname":"Music Memorabilia","categoryurl":"http:\/\/www.ebay.com\/sch\/Music-Memorabilia\/2329\/i.html"},{"categoryname":"Other Entertainment Mem","categoryurl":"http:\/\/www.ebay.com\/sch\/Other-Entertainment-Mem\/2312\/i.html"},{"categoryname":"Television Memorabilia","categoryurl":"http:\/\/www.ebay.com\/sch\/Television-Memorabilia\/1424\/i.html"},{"categoryname":"Theater Memorabilia","categoryurl":"http:\/\/www.ebay.com\/sch\/Theater-Memorabilia\/2362\/i.html"},{"categoryname":"Video Game Memorabilia","categoryurl":"http:\/\/www.ebay.com\/sch\/Video-Game-Memorabilia\/45101\/i.html"},{"categoryname":"Coupons","categoryurl":"http:\/\/www.ebay.com\/sch\/Coupons\/172010\/i.html"},{"categoryname":"Digital Gifts","categoryurl":"http:\/\/www.ebay.com\/sch\/Digital-Gifts\/176950\/i.html"},{"categoryname":"eBay Gift Cards","categoryurl":"http:\/\/www.ebay.com\/sch\/eBay-Gift-Cards\/172036\/i.html"},{"categoryname":"Gift Cards","categoryurl":"http:\/\/www.ebay.com\/sch\/Gift-Cards\/172009\/i.html"},{"categoryname":"Gift Certificates","categoryurl":"http:\/\/www.ebay.com\/sch\/Gift-Certificates\/31411\/i.html"},{"categoryname":"Bath & Body","categoryurl":"http:\/\/www.ebay.com\/sch\/Bath-Body\/11838\/i.html"},{"categoryname":"Fragrances","categoryurl":"http:\/\/www.ebay.com\/sch\/Fragrances\/180345\/i.html"},{"categoryname":"Hair Care & Styling","categoryurl":"http:\/\/www.ebay.com\/sch\/Hair-Care-Styling\/11854\/i.html"},{"categoryname":"Health Care","categoryurl":"http:\/\/www.ebay.com\/sch\/Health-Care\/67588\/i.html"},{"categoryname":"Makeup","categoryurl":"http:\/\/www.ebay.com\/sch\/Makeup\/31786\/i.html"},{"categoryname":"Massage","categoryurl":"http:\/\/www.ebay.com\/sch\/Massage\/36447\/i.html"},{"categoryname":"Medical, Mobility & Disability","categoryurl":"http:\/\/www.ebay.com\/sch\/Medical-Mobility-Disability\/11778\/i.html"},{"categoryname":"Nail Care, Manicure & Pedicure","categoryurl":"http:\/\/www.ebay.com\/sch\/Nail-Care-Manicure-Pedicure\/47945\/i.html"},{"categoryname":"Natural & Alternative Remedies","categoryurl":"http:\/\/www.ebay.com\/sch\/Natural-Alternative-Remedies\/67659\/i.html"},{"categoryname":"Oral Care","categoryurl":"http:\/\/www.ebay.com\/sch\/Oral-Care\/31769\/i.html"},{"categoryname":"Other Health & Beauty","categoryurl":"http:\/\/www.ebay.com\/sch\/Other-Health-Beauty\/1277\/i.html"},{"categoryname":"Salon & Spa Equipment","categoryurl":"http:\/\/www.ebay.com\/sch\/Salon-Spa-Equipment\/177731\/i.html"},{"categoryname":"Shaving & Hair Removal","categoryurl":"http:\/\/www.ebay.com\/sch\/Shaving-Hair-Removal\/31762\/i.html"},{"categoryname":"Skin Care","categoryurl":"http:\/\/www.ebay.com\/sch\/Skin-Care\/11863\/i.html"},{"categoryname":"Sun Protection & Tanning","categoryurl":"http:\/\/www.ebay.com\/sch\/Sun-Protection-Tanning\/31772\/i.html"},{"categoryname":"Tattoos & Body Art","categoryurl":"http:\/\/www.ebay.com\/sch\/Tattoos-Body-Art\/33914\/i.html"},{"categoryname":"Vision Care","categoryurl":"http:\/\/www.ebay.com\/sch\/Vision-Care\/31414\/i.html"},{"categoryname":"Vitamins & Dietary Supplements","categoryurl":"http:\/\/www.ebay.com\/sch\/Vitamins-Dietary-Supplements\/180959\/i.html"},{"categoryname":"Bath","categoryurl":"http:\/\/www.ebay.com\/sch\/Bath\/26677\/i.html"},{"categoryname":"Bedding","categoryurl":"http:\/\/www.ebay.com\/sch\/Bedding\/20444\/i.html"},{"categoryname":"Food & Beverages","categoryurl":"http:\/\/www.ebay.com\/sch\/Food-Beverages\/14308\/i.html"},{"categoryname":"Fresh Cut Flowers & Supplies","categoryurl":"http:\/\/www.ebay.com\/sch\/Fresh-Cut-Flowers-Supplies\/178069\/i.html"},{"categoryname":"Furniture","categoryurl":"http:\/\/www.ebay.com\/sch\/Furniture\/3197\/i.html"},{"categoryname":"Greeting Cards & Party Supply","categoryurl":"http:\/\/www.ebay.com\/sch\/Greeting-Cards-Party-Supply\/16086\/i.html"},{"categoryname":"Holiday & Seasonal D\u00e9cor","categoryurl":"http:\/\/www.ebay.com\/sch\/Holiday-Seasonal-Decor\/38227\/i.html"},{"categoryname":"Home D\u00e9cor","categoryurl":"http:\/\/www.ebay.com\/sch\/Home-Decor\/10033\/i.html"},{"categoryname":"Home Improvement","categoryurl":"http:\/\/www.ebay.com\/sch\/Home-Improvement\/159907\/i.html"},{"categoryname":"Household Supplies & Cleaning","categoryurl":"http:\/\/www.ebay.com\/sch\/Household-Supplies-Cleaning\/299\/i.html"},{"categoryname":"Kids & Teens at Home","categoryurl":"http:\/\/www.ebay.com\/sch\/Kids-Teens-at-Home\/176988\/i.html"},{"categoryname":"Kitchen, Dining & Bar","categoryurl":"http:\/\/www.ebay.com\/sch\/Kitchen-Dining-Bar\/20625\/i.html"},{"categoryname":"Lamps, Lighting & Ceiling Fans","categoryurl":"http:\/\/www.ebay.com\/sch\/Lamps-Lighting-Ceiling-Fans\/20697\/i.html"},{"categoryname":"Major Appliances","categoryurl":"http:\/\/www.ebay.com\/sch\/Major-Appliances\/20710\/i.html"},{"categoryname":"Other Home & Garden","categoryurl":"http:\/\/www.ebay.com\/sch\/Other-Home-Garden\/181076\/i.html"},{"categoryname":"Tools","categoryurl":"http:\/\/www.ebay.com\/sch\/Tools\/631\/i.html"},{"categoryname":"Wedding Supplies","categoryurl":"http:\/\/www.ebay.com\/sch\/Wedding-Supplies\/11827\/i.html"},{"categoryname":"Window Treatments & Hardware","categoryurl":"http:\/\/www.ebay.com\/sch\/Window-Treatments-Hardware\/63514\/i.html"},{"categoryname":"Yard, Garden & Outdoor Living","categoryurl":"http:\/\/www.ebay.com\/sch\/Yard-Garden-Outdoor-Living\/159912\/i.html"},{"categoryname":"Children's Jewelry","categoryurl":"http:\/\/www.ebay.com\/sch\/Childrens-Jewelry\/84605\/i.html"},{"categoryname":"Engagement & Wedding","categoryurl":"http:\/\/www.ebay.com\/sch\/Engagement-Wedding\/91427\/i.html"},{"categoryname":"Ethnic, Regional & Tribal","categoryurl":"http:\/\/www.ebay.com\/sch\/Ethnic-Regional-Tribal\/11312\/i.html"},{"categoryname":"Fashion Jewelry","categoryurl":"http:\/\/www.ebay.com\/sch\/Fashion-Jewelry\/10968\/i.html"},{"categoryname":"Fine Jewelry","categoryurl":"http:\/\/www.ebay.com\/sch\/Fine-Jewelry\/4196\/i.html"},{"categoryname":"Handcrafted, Artisan Jewelry","categoryurl":"http:\/\/www.ebay.com\/sch\/Handcrafted-Artisan-Jewelry\/110633\/i.html"},{"categoryname":"Jewelry Boxes & Organizers","categoryurl":"http:\/\/www.ebay.com\/sch\/Jewelry-Boxes-Organizers\/10321\/i.html"},{"categoryname":"Jewelry Design & Repair","categoryurl":"http:\/\/www.ebay.com\/sch\/Jewelry-Design-Repair\/164352\/i.html"},{"categoryname":"Loose Beads","categoryurl":"http:\/\/www.ebay.com\/sch\/Loose-Beads\/179264\/i.html"},{"categoryname":"Loose Diamonds & Gemstones","categoryurl":"http:\/\/www.ebay.com\/sch\/Loose-Diamonds-Gemstones\/491\/i.html"},{"categoryname":"Men's Jewelry","categoryurl":"http:\/\/www.ebay.com\/sch\/Mens-Jewelry\/10290\/i.html"},{"categoryname":"Other Jewelry & Watches","categoryurl":"http:\/\/www.ebay.com\/sch\/Other-Jewelry-Watches\/98863\/i.html"},{"categoryname":"Vintage & Antique Jewelry","categoryurl":"http:\/\/www.ebay.com\/sch\/Vintage-Antique-Jewelry\/48579\/i.html"},{"categoryname":"Watches, Parts & Accessories","categoryurl":"http:\/\/www.ebay.com\/sch\/Watches-Parts-Accessories\/14324\/i.html"},{"categoryname":"Cassettes","categoryurl":"http:\/\/www.ebay.com\/sch\/Cassettes\/176983\/i.html"},{"categoryname":"CDs","categoryurl":"http:\/\/www.ebay.com\/sch\/CDs\/176984\/i.html"},{"categoryname":"Other Formats","categoryurl":"http:\/\/www.ebay.com\/sch\/Other-Formats\/618\/i.html"},{"categoryname":"Records","categoryurl":"http:\/\/www.ebay.com\/sch\/Records\/176985\/i.html"},{"categoryname":"Wholesale Lots","categoryurl":"http:\/\/www.ebay.com\/sch\/Wholesale-Lots\/31608\/i.html"},{"categoryname":"Brass","categoryurl":"http:\/\/www.ebay.com\/sch\/Brass\/16212\/i.html"},{"categoryname":"DJ Equipment","categoryurl":"http:\/\/www.ebay.com\/sch\/DJ-Equipment\/48458\/i.html"},{"categoryname":"Equipment","categoryurl":"http:\/\/www.ebay.com\/sch\/Equipment\/180008\/i.html"},{"categoryname":"Guitars & Basses","categoryurl":"http:\/\/www.ebay.com\/sch\/Guitars-Basses\/3858\/i.html"},{"categoryname":"Instruction Books, CDs & Video","categoryurl":"http:\/\/www.ebay.com\/sch\/Instruction-Books-CDs-Video\/182150\/i.html"},{"categoryname":"Karaoke Entertainment","categoryurl":"http:\/\/www.ebay.com\/sch\/Karaoke-Entertainment\/175696\/i.html"},{"categoryname":"Other Musical Instruments","categoryurl":"http:\/\/www.ebay.com\/sch\/Other-Musical-Instruments\/308\/i.html"},{"categoryname":"Percussion","categoryurl":"http:\/\/www.ebay.com\/sch\/Percussion\/180012\/i.html"},{"categoryname":"Pianos, Keyboards & Organs","categoryurl":"http:\/\/www.ebay.com\/sch\/Pianos-Keyboards-Organs\/180010\/i.html"},{"categoryname":"Pro Audio Equipment","categoryurl":"http:\/\/www.ebay.com\/sch\/Pro-Audio-Equipment\/180014\/i.html"},{"categoryname":"Sheet Music & Song Books","categoryurl":"http:\/\/www.ebay.com\/sch\/Sheet-Music-Song-Books\/180015\/i.html"},{"categoryname":"Stage Lighting & Effects","categoryurl":"http:\/\/www.ebay.com\/sch\/Stage-Lighting-Effects\/12922\/i.html"},{"categoryname":"String","categoryurl":"http:\/\/www.ebay.com\/sch\/String\/180016\/i.html"},{"categoryname":"Vintage Musical Instruments","categoryurl":"http:\/\/www.ebay.com\/sch\/Vintage-Musical-Instruments\/181162\/i.html"},{"categoryname":"Wind & Woodwind","categoryurl":"http:\/\/www.ebay.com\/sch\/Wind-Woodwind\/10181\/i.html"},{"categoryname":"Backyard Poultry Supplies","categoryurl":"http:\/\/www.ebay.com\/sch\/Backyard-Poultry-Supplies\/177801\/i.html"},{"categoryname":"Bird Supplies","categoryurl":"http:\/\/www.ebay.com\/sch\/Bird-Supplies\/20734\/i.html"},{"categoryname":"Cat Supplies","categoryurl":"http:\/\/www.ebay.com\/sch\/Cat-Supplies\/20737\/i.html"},{"categoryname":"Dog Supplies","categoryurl":"http:\/\/www.ebay.com\/sch\/Dog-Supplies\/20742\/i.html"},{"categoryname":"Fish & Aquariums","categoryurl":"http:\/\/www.ebay.com\/sch\/Fish-Aquariums\/20754\/i.html"},{"categoryname":"Other Pet Supplies","categoryurl":"http:\/\/www.ebay.com\/sch\/Other-Pet-Supplies\/301\/i.html"},{"categoryname":"Pet Memorials & Urns","categoryurl":"http:\/\/www.ebay.com\/sch\/Pet-Memorials-Urns\/116391\/i.html"},{"categoryname":"Reptile Supplies","categoryurl":"http:\/\/www.ebay.com\/sch\/Reptile-Supplies\/1285\/i.html"},{"categoryname":"Small Animal Supplies","categoryurl":"http:\/\/www.ebay.com\/sch\/Small-Animal-Supplies\/26696\/i.html"},{"categoryname":"Glass","categoryurl":"http:\/\/www.ebay.com\/sch\/Glass\/50693\/i.html"},{"categoryname":"Pottery & China","categoryurl":"http:\/\/www.ebay.com\/sch\/Pottery-China\/18875\/i.html"},{"categoryname":"Commercial","categoryurl":"http:\/\/www.ebay.com\/sch\/Commercial\/15825\/i.html"},{"categoryname":"Land","categoryurl":"http:\/\/www.ebay.com\/sch\/Land\/15841\/i.html"},{"categoryname":"Manufactured Homes","categoryurl":"http:\/\/www.ebay.com\/sch\/Manufactured-Homes\/94825\/i.html"},{"categoryname":"Other Real Estate","categoryurl":"http:\/\/www.ebay.com\/sch\/Other-Real-Estate\/1607\/i.html"},{"categoryname":"Residential","categoryurl":"http:\/\/www.ebay.com\/sch\/Residential\/12605\/i.html"},{"categoryname":"Timeshares for Sale","categoryurl":"http:\/\/www.ebay.com\/sch\/Timeshares-for-Sale\/15897\/i.html"},{"categoryname":"Artistic Services","categoryurl":"http:\/\/www.ebay.com\/sch\/Artistic-Services\/47126\/i.html"},{"categoryname":"Custom Clothing & Jewelry","categoryurl":"http:\/\/www.ebay.com\/sch\/Custom-Clothing-Jewelry\/50343\/i.html"},{"categoryname":"eBay Auction Services","categoryurl":"http:\/\/www.ebay.com\/sch\/eBay-Auction-Services\/50349\/i.html"},{"categoryname":"Graphic & Logo Design","categoryurl":"http:\/\/www.ebay.com\/sch\/Graphic-Logo-Design\/47131\/i.html"},{"categoryname":"Home Improvement Services","categoryurl":"http:\/\/www.ebay.com\/sch\/Home-Improvement-Services\/170048\/i.html"},{"categoryname":"Item Based Services","categoryurl":"http:\/\/www.ebay.com\/sch\/Item-Based-Services\/175814\/i.html"},{"categoryname":"Media Editing & Duplication","categoryurl":"http:\/\/www.ebay.com\/sch\/Media-Editing-Duplication\/50355\/i.html"},{"categoryname":"Other Specialty Services","categoryurl":"http:\/\/www.ebay.com\/sch\/Other-Specialty-Services\/317\/i.html"},{"categoryname":"Printing & Personalization","categoryurl":"http:\/\/www.ebay.com\/sch\/Printing-Personalization\/20943\/i.html"},{"categoryname":"Restoration & Repair","categoryurl":"http:\/\/www.ebay.com\/sch\/Restoration-Repair\/47119\/i.html"},{"categoryname":"Web & Computer Services","categoryurl":"http:\/\/www.ebay.com\/sch\/Web-Computer-Services\/47104\/i.html"},{"categoryname":"Boxing, Martial Arts & MMA","categoryurl":"http:\/\/www.ebay.com\/sch\/Boxing-Martial-Arts-MMA\/179767\/i.html"},{"categoryname":"Cycling","categoryurl":"http:\/\/www.ebay.com\/sch\/Cycling\/7294\/i.html"},{"categoryname":"Fishing","categoryurl":"http:\/\/www.ebay.com\/sch\/Fishing\/1492\/i.html"},{"categoryname":"Fitness, Running & Yoga","categoryurl":"http:\/\/www.ebay.com\/sch\/Fitness-Running-Yoga\/15273\/i.html"},{"categoryname":"Golf","categoryurl":"http:\/\/www.ebay.com\/sch\/Golf\/1513\/i.html"},{"categoryname":"Hunting","categoryurl":"http:\/\/www.ebay.com\/sch\/Hunting\/7301\/i.html"},{"categoryname":"Indoor Games","categoryurl":"http:\/\/www.ebay.com\/sch\/Indoor-Games\/36274\/i.html"},{"categoryname":"Other Sporting Goods","categoryurl":"http:\/\/www.ebay.com\/sch\/Other-Sporting-Goods\/310\/i.html"},{"categoryname":"Outdoor Sports","categoryurl":"http:\/\/www.ebay.com\/sch\/Outdoor-Sports\/159043\/i.html"},{"categoryname":"Team Sports","categoryurl":"http:\/\/www.ebay.com\/sch\/Team-Sports\/159049\/i.html"},{"categoryname":"Tennis & Racquet Sports","categoryurl":"http:\/\/www.ebay.com\/sch\/Tennis-Racquet-Sports\/159134\/i.html"},{"categoryname":"Water Sports","categoryurl":"http:\/\/www.ebay.com\/sch\/Water-Sports\/159136\/i.html"},{"categoryname":"Winter Sports","categoryurl":"http:\/\/www.ebay.com\/sch\/Winter-Sports\/36259\/i.html"},{"categoryname":"Autographs-Original","categoryurl":"http:\/\/www.ebay.com\/sch\/Autographs-Original\/51\/i.html"},{"categoryname":"Autographs-Reprints","categoryurl":"http:\/\/www.ebay.com\/sch\/Autographs-Reprints\/50115\/i.html"},{"categoryname":"Fan Apparel & Souvenirs","categoryurl":"http:\/\/www.ebay.com\/sch\/Fan-Apparel-Souvenirs\/24409\/i.html"},{"categoryname":"Game Used Memorabilia","categoryurl":"http:\/\/www.ebay.com\/sch\/Game-Used-Memorabilia\/50116\/i.html"},{"categoryname":"Sports Stickers, Sets & Albums","categoryurl":"http:\/\/www.ebay.com\/sch\/Sports-Stickers-Sets-Albums\/141755\/i.html"},{"categoryname":"Sports Trading Cards","categoryurl":"http:\/\/www.ebay.com\/sch\/Sports-Trading-Cards\/212\/i.html"},{"categoryname":"Vintage Sports Memorabilia","categoryurl":"http:\/\/www.ebay.com\/sch\/Vintage-Sports-Memorabilia\/50123\/i.html"},{"categoryname":"Africa","categoryurl":"http:\/\/www.ebay.com\/sch\/Africa\/181423\/i.html"},{"categoryname":"Asia","categoryurl":"http:\/\/www.ebay.com\/sch\/Asia\/181416\/i.html"},{"categoryname":"Australia & Oceania","categoryurl":"http:\/\/www.ebay.com\/sch\/Australia-Oceania\/181424\/i.html"},{"categoryname":"British Colonies & Territories","categoryurl":"http:\/\/www.ebay.com\/sch\/British-Colonies-Territories\/65174\/i.html"},{"categoryname":"Canada","categoryurl":"http:\/\/www.ebay.com\/sch\/Canada\/3478\/i.html"},{"categoryname":"Caribbean","categoryurl":"http:\/\/www.ebay.com\/sch\/Caribbean\/179377\/i.html"},{"categoryname":"Europe","categoryurl":"http:\/\/www.ebay.com\/sch\/Europe\/4742\/i.html"},{"categoryname":"Great Britain","categoryurl":"http:\/\/www.ebay.com\/sch\/Great-Britain\/3499\/i.html"},{"categoryname":"Latin America","categoryurl":"http:\/\/www.ebay.com\/sch\/Latin-America\/181417\/i.html"},{"categoryname":"Middle East","categoryurl":"http:\/\/www.ebay.com\/sch\/Middle-East\/181422\/i.html"},{"categoryname":"Other Stamps","categoryurl":"http:\/\/www.ebay.com\/sch\/Other-Stamps\/170137\/i.html"},{"categoryname":"Specialty Philately","categoryurl":"http:\/\/www.ebay.com\/sch\/Specialty-Philately\/7898\/i.html"},{"categoryname":"Topical Stamps","categoryurl":"http:\/\/www.ebay.com\/sch\/Topical-Stamps\/4752\/i.html"},{"categoryname":"United States","categoryurl":"http:\/\/www.ebay.com\/sch\/United-States\/261\/i.html"},{"categoryname":"Worldwide","categoryurl":"http:\/\/www.ebay.com\/sch\/Worldwide\/181420\/i.html"},{"categoryname":"Concert Tickets","categoryurl":"http:\/\/www.ebay.com\/sch\/Concert-Tickets\/173634\/i.html"},{"categoryname":"Other Tickets & Experiences","categoryurl":"http:\/\/www.ebay.com\/sch\/Other-Tickets-Experiences\/1306\/i.html"},{"categoryname":"Parking Passes","categoryurl":"http:\/\/www.ebay.com\/sch\/Parking-Passes\/178892\/i.html"},{"categoryname":"Special Experiences","categoryurl":"http:\/\/www.ebay.com\/sch\/Special-Experiences\/170591\/i.html"},{"categoryname":"Sports Tickets","categoryurl":"http:\/\/www.ebay.com\/sch\/Sports-Tickets\/173633\/i.html"},{"categoryname":"Theater Tickets","categoryurl":"http:\/\/www.ebay.com\/sch\/Theater-Tickets\/173635\/i.html"},{"categoryname":"Theme Park & Club Passes","categoryurl":"http:\/\/www.ebay.com\/sch\/Theme-Park-Club-Passes\/170594\/i.html"},{"categoryname":"Action Figures","categoryurl":"http:\/\/www.ebay.com\/sch\/Action-Figures\/246\/i.html"},{"categoryname":"Beanbag Plush","categoryurl":"http:\/\/www.ebay.com\/sch\/Beanbag-Plush\/49019\/i.html"},{"categoryname":"Building Toys","categoryurl":"http:\/\/www.ebay.com\/sch\/Building-Toys\/183446\/i.html"},{"categoryname":"Classic Toys","categoryurl":"http:\/\/www.ebay.com\/sch\/Classic-Toys\/19016\/i.html"},{"categoryname":"Collectible Card Games","categoryurl":"http:\/\/www.ebay.com\/sch\/Collectible-Card-Games\/2536\/i.html"},{"categoryname":"Diecast & Toy Vehicles","categoryurl":"http:\/\/www.ebay.com\/sch\/Diecast-Toy-Vehicles\/222\/i.html"},{"categoryname":"Educational","categoryurl":"http:\/\/www.ebay.com\/sch\/Educational\/11731\/i.html"},{"categoryname":"Electronic, Battery & Wind-Up","categoryurl":"http:\/\/www.ebay.com\/sch\/Electronic-Battery-Wind-Up\/19071\/i.html"},{"categoryname":"Fast Food & Cereal Premiums","categoryurl":"http:\/\/www.ebay.com\/sch\/Fast-Food-Cereal-Premiums\/19077\/i.html"},{"categoryname":"Games","categoryurl":"http:\/\/www.ebay.com\/sch\/Games\/233\/i.html"},{"categoryname":"Marbles","categoryurl":"http:\/\/www.ebay.com\/sch\/Marbles\/58799\/i.html"},{"categoryname":"Model Railroads & Trains","categoryurl":"http:\/\/www.ebay.com\/sch\/Model-Railroads-Trains\/180250\/i.html"},{"categoryname":"Models & Kits","categoryurl":"http:\/\/www.ebay.com\/sch\/Models-Kits\/1188\/i.html"},{"categoryname":"Outdoor Toys & Structures","categoryurl":"http:\/\/www.ebay.com\/sch\/Outdoor-Toys-Structures\/11743\/i.html"},{"categoryname":"Preschool Toys & Pretend Play","categoryurl":"http:\/\/www.ebay.com\/sch\/Preschool-Toys-Pretend-Play\/19169\/i.html"},{"categoryname":"Puzzles","categoryurl":"http:\/\/www.ebay.com\/sch\/Puzzles\/2613\/i.html"},{"categoryname":"Radio Control & Control Line","categoryurl":"http:\/\/www.ebay.com\/sch\/Radio-Control-Control-Line\/2562\/i.html"},{"categoryname":"Robots, Monsters & Space Toys","categoryurl":"http:\/\/www.ebay.com\/sch\/Robots-Monsters-Space-Toys\/19192\/i.html"},{"categoryname":"Slot Cars","categoryurl":"http:\/\/www.ebay.com\/sch\/Slot-Cars\/2616\/i.html"},{"categoryname":"Stuffed Animals","categoryurl":"http:\/\/www.ebay.com\/sch\/Stuffed-Animals\/436\/i.html"},{"categoryname":"Toy Soldiers","categoryurl":"http:\/\/www.ebay.com\/sch\/Toy-Soldiers\/2631\/i.html"},{"categoryname":"TV, Movie & Character Toys","categoryurl":"http:\/\/www.ebay.com\/sch\/TV-Movie-Character-Toys\/2624\/i.html"},{"categoryname":"Vintage & Antique Toys","categoryurl":"http:\/\/www.ebay.com\/sch\/Vintage-Antique-Toys\/717\/i.html"},{"categoryname":"Airline","categoryurl":"http:\/\/www.ebay.com\/sch\/Airline\/3253\/i.html"},{"categoryname":"Campground & RV Parks","categoryurl":"http:\/\/www.ebay.com\/sch\/Campground-RV-Parks\/164802\/i.html"},{"categoryname":"Car Rental","categoryurl":"http:\/\/www.ebay.com\/sch\/Car-Rental\/147399\/i.html"},{"categoryname":"Cruises","categoryurl":"http:\/\/www.ebay.com\/sch\/Cruises\/16078\/i.html"},{"categoryname":"Lodging","categoryurl":"http:\/\/www.ebay.com\/sch\/Lodging\/16123\/i.html"},{"categoryname":"Luggage","categoryurl":"http:\/\/www.ebay.com\/sch\/Luggage\/16080\/i.html"},{"categoryname":"Luggage Accessories","categoryurl":"http:\/\/www.ebay.com\/sch\/Luggage-Accessories\/173520\/i.html"},{"categoryname":"Maps","categoryurl":"http:\/\/www.ebay.com\/sch\/Maps\/164803\/i.html"},{"categoryname":"Other Travel","categoryurl":"http:\/\/www.ebay.com\/sch\/Other-Travel\/1310\/i.html"},{"categoryname":"Rail","categoryurl":"http:\/\/www.ebay.com\/sch\/Rail\/98982\/i.html"},{"categoryname":"Travel Accessories","categoryurl":"http:\/\/www.ebay.com\/sch\/Travel-Accessories\/93838\/i.html"},{"categoryname":"Vacation Packages","categoryurl":"http:\/\/www.ebay.com\/sch\/Vacation-Packages\/29578\/i.html"},{"categoryname":"Vintage Luggage & Travel Accs","categoryurl":"http:\/\/www.ebay.com\/sch\/Vintage-Luggage-Travel-Accs\/183477\/i.html"},{"categoryname":"Manuals, Inserts & Box Art","categoryurl":"http:\/\/www.ebay.com\/sch\/Manuals-Inserts-Box-Art\/182174\/i.html"},{"categoryname":"Original Game Cases & Boxes","categoryurl":"http:\/\/www.ebay.com\/sch\/Original-Game-Cases-Boxes\/182175\/i.html"},{"categoryname":"Other Video Games & Consoles","categoryurl":"http:\/\/www.ebay.com\/sch\/Other-Video-Games-Consoles\/187\/i.html"},{"categoryname":"Prepaid Gaming Cards","categoryurl":"http:\/\/www.ebay.com\/sch\/Prepaid-Gaming-Cards\/156597\/i.html"},{"categoryname":"Strategy Guides & Cheats","categoryurl":"http:\/\/www.ebay.com\/sch\/Strategy-Guides-Cheats\/156595\/i.html"},{"categoryname":"Video Game Accessories","categoryurl":"http:\/\/www.ebay.com\/sch\/Video-Game-Accessories\/54968\/i.html"},{"categoryname":"Video Game Consoles","categoryurl":"http:\/\/www.ebay.com\/sch\/Video-Game-Consoles\/139971\/i.html"},{"categoryname":"Video Game Merchandise","categoryurl":"http:\/\/www.ebay.com\/sch\/Video-Game-Merchandise\/38583\/i.html"},{"categoryname":"Video Games","categoryurl":"http:\/\/www.ebay.com\/sch\/Video-Games\/139973\/i.html"},{"categoryname":"Adult Only","categoryurl":"http:\/\/www.ebay.com\/sch\/Adult-Only\/319\/i.html"},{"categoryname":"Career Development & Education","categoryurl":"http:\/\/www.ebay.com\/sch\/Career-Development-Education\/3143\/i.html"},{"categoryname":"eBay Special Offers","categoryurl":"http:\/\/www.ebay.com\/sch\/eBay-Special-Offers\/177600\/i.html"},{"categoryname":"eBay User Tools","categoryurl":"http:\/\/www.ebay.com\/sch\/eBay-User-Tools\/20924\/i.html"},{"categoryname":"Every Other Thing","categoryurl":"http:\/\/www.ebay.com\/sch\/Every-Other-Thing\/88433\/i.html"},{"categoryname":"Funeral & Cemetery","categoryurl":"http:\/\/www.ebay.com\/sch\/Funeral-Cemetery\/88739\/i.html"},{"categoryname":"Genealogy","categoryurl":"http:\/\/www.ebay.com\/sch\/Genealogy\/20925\/i.html"},{"categoryname":"Information Products","categoryurl":"http:\/\/www.ebay.com\/sch\/Information-Products\/102480\/i.html"},{"categoryname":"Metaphysical","categoryurl":"http:\/\/www.ebay.com\/sch\/Metaphysical\/19266\/i.html"},{"categoryname":"Personal Development","categoryurl":"http:\/\/www.ebay.com\/sch\/Personal-Development\/102329\/i.html"},{"categoryname":"Personal Security","categoryurl":"http:\/\/www.ebay.com\/sch\/Personal-Security\/102535\/i.html"},{"categoryname":"Religious Products & Supplies","categoryurl":"http:\/\/www.ebay.com\/sch\/Religious-Products-Supplies\/102545\/i.html"},{"categoryname":"Reward Points & Incentives","categoryurl":"http:\/\/www.ebay.com\/sch\/Reward-Points-Incentives\/102553\/i.html"},{"categoryname":"Test Auctions","categoryurl":"http:\/\/www.ebay.com\/sch\/Test-Auctions\/14112\/i.html"},{"categoryname":"Weird Stuff","categoryurl":"http:\/\/www.ebay.com\/sch\/Weird-Stuff\/1466\/i.html"}]
//         $catsurl = "http://www.ebay.com/sch/Antiquities/37903/i.html";
//         $res = $esp->fetchLeafCategorys($catsurl);
//         echo json_encode($res);
        
//         $ecm = new EbayCategoryEsModel();
//         $res = $ecm->create_index_mapping();
//         echo $res;
    }
}

// $esp = new EbaySpider();
// echo $esp->convertCurrency("&xxx;&pound;");

// $catsurl = "http://www.ebay.com/sch/Antiquities/37903/i.html";
// $catsurl = "http://www.ebay.com/sch/Parts-Accessories/6028/i.html";
// $catsurl = "http://www.ebay.com/sch/ATVs for Parts/43981/bn_584106/i.html";
//  $catsurl = "http://www.ebay.com/sch/Sculpting-Molding-Ceramics/183302/i.html";
// $catsurl = "http://www.ebay.com/sch/Adult-Only/319/i.html";
// $res = $esp->fetchLeafCategorys($catsurl);
// echo json_encode($res);

// $cgurl="http://www.ebay.com/sch/AR-IA-KS-LA-MO-NE/163062/i.html";
// $cgurl = "http://www.ebay.co.uk/sch/Antique-Chinese-Baskets/37920/bn_16566826/i.html";
// $cgurl = "http://www.ebay.com/sch/United-States-1900-Now/163059/i.html";
// $cgurl = "http://www.ebay.com/sch/Baskets/37920/i.html";
// $res = $esp->crawlProductsByCategory("UK",37920, $cgurl, 2, 12);
// echo json_encode($res);

// $purl = "http://www.ebay.com/itm/TROXEL-LOW-PROFILE-CHENYENNE-BROWN-WESTERN-RIDING-HELMET-W-LEATHER-FINISH-/231978020769?var=&hash=item3602f7afa1:m:moxbPiLc-97tdpsXCdsF60Q";
// $purl = "http://www.ebay.com/itm/Hook-Stroll-Pram-pushchair-Stroller-Buggy-Clips-Hooks-2-PACK-large-strong-bags-/112063891372?hash=item1a178747ac:g:upUAAOSwsTxXkH1w";
// $purl = "http://www.ebay.com/itm/Hitachi-NT65M2S-2-1-2-Finish-Nailer-with-Integrated-Air-Duster-16-Gauge-/122022830975?hash=item1c6920a37f:g:d-QAAOSwbYZXac0T";
// $purl = "http://www.ebay.com/itm/Men-Women-sweethearts-Low-shallow-mouth-Canvas-shoes-Students-single-shoes-S-012-/371310690592";
// $res = $esp->crawlProduct("info", "us", $purl);
// echo "\r\n". json_encode($res);

//$res = $esp->getShippingRates("322215834489", "United States");
// $res = $esp->getShippingRates("322215834489", "Vietnam");
// echo "\n$res";

// $res = $esp->crawlPurchaseHistory("232009537211");//("322215834489");
// echo "\r\n". json_encode($res);

// $res = $esp->crawlStoreFeedbackInfo("antiquemapsprints");
// echo "\r\n". json_encode($res);
// $year = date("Y",time());
// $btime = time();
// $res = null;
// for($i=1;$i<800;$i++){
// $res = $esp->fetchProductByStore("antiquemapsprints",$i, $year, $btime);
// echo "\r\n".$res["page"]."---".$res["year"]."----".date("Ymd H:i:s",$res["beforetime"]);//. json_encode($res);
// //if(empty($res))break;
// //if($res["year"]==2016)break;
// $year = $res["year"];
// $btime = $res["beforetime"];
// }


?>