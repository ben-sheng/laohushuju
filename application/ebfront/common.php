<?php
 /**
     * 根据指定国家，获得EB国家域名
     * @param unknown $country US UK
     */
    function ebf_getUrlByContry( $country, $url ){
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
    
    function ebf_getCategoryname($cid, $cgmaps){
        $cname = "unknow";
        if(!empty($cid) && !empty($cgmaps) && isset($cgmaps[$cid])){
            $cname = $cgmaps[$cid]["categoryname"];
        }
        return $cname;
    }
    
    function ebf_getCategoryurl($cid, $cgmaps){
        $cgurl = "#";
        if(!empty($cid) && !empty($cgmaps) && isset($cgmaps[$cid])){
            $cgurl = $cgmaps[$cid]["categoryurl"];
        }
        return $cgurl;
    }