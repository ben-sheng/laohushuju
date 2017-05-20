<?php
namespace app\ebay;

class EbayRedis extends \Redis
{

    public function __construct()
    {
        parent::__construct();
        $this->connect(config("redis.host"), config("redis.port"));
        $this->auth(config("redis.passwd"));
    }

    function __destruct()
    {}
    
    /**
     * 锁定，必须要有lockkey，并且unlock时，需要使用这个key来解锁
     * 参考http://blog.csdn.net/ugg/article/details/41894947
     * @param $lockkey 必须要有，锁定key
     * @return unknown
     */
    function lock($lockkey){
        if(empty($lockkey)){
            return 0;
        }
        $lockvalue = 0;//0失败，1成功
        while(true){
            $now = time();
            $res = $this->setnx($lockkey,time());
            if($res){//锁定成功,返回
                $lockvalue = 1;
                break;
            }else{
                $tt = $this->get($lockkey);
                echo "\n wait time ".($now-$tt);
                if($now-$tt>600){//10分钟没有解锁，意味着被锁堵塞了
                    $nt = $this->getSet($lockkey,time());
                    if($nt==$tt){//说明锁定成功
                        $lockvalue=1;
                        break;
                    }else{
                        echo "\n lock be blocked,wait 5s continue...";
                        sleep(5);//继续等待
                    }
                }else{//未堵塞，等待2s再去取锁
                    echo "\n locker be locked, wait(5s) try again...";
                    sleep(5);
                }
            }
        }
        return $lockvalue;
    }
    
    /**
     * 必须要有lockkey，并且要跟lock的key一模一样才行
     * @param unknown $lockkey
     * @return boolean
     */
    function unlock( $lockkey ){
        if(empty($lockkey)){
            return false;
        }
        return $this->delete($lockkey);
    }
    
    /**
     * 获得es中get数据的from，在分布式系统上，这儿存放了当前在处理的商品数。
     * 获取下一批处理数据时，需要把当前这批在处理的数据偏移出来。
     * @param unknown $lockkey
     * @param unknown $fromkey
     * @param unknown $querysize 每次要处理多少数据
     * @param number $waittime 超过600s没有获取到数据，退出
     * @return Ambigous <boolean, number>
     */
    function getQueryFrom($lockkey, $fromkey, $querysize, $waittime=600){
        if(empty($lockkey) || empty($fromkey) || empty($querysize)) return false;
        $from = false;
        $t = time();
        $loop = 0;
        while(true){
            $locked = $this->lock($lockkey);//循环执行，直到获得锁
            //如果没有获得锁，直接重新获取
            if(!$locked){
                echo "\nLock failed,continue....";
                sleep(5);
                if(time()-$t>=$waittime)break;
                continue;
            }
            $from = $this->get($fromkey);
            if(empty($from)){
                $from=0;
            }
            //超过了可以偏移的最大量。默认只有1万。通过设置max_result_window的值，增加到20W。
            if($from<0 || $from>200000){
                $this->unlock($lockkey);
                sleep(5);
                continue;
            }
            $this->setex($fromkey,3600,$from+$querysize);
            $this->unlock($lockkey);
            break;
        }
        return $from;
    }
    
    /**
     * 处理完一批数据，即减去当前的偏移量，
     * 获取下一批抓取数据时，只按照当前未完成的数据，进行偏移
     * @param unknown $lockkey
     * @param unknown $fromkey
     * @param unknown $decsize
     */
    function decQyeryFromSize($lockkey, $fromkey, $decsize){
        if(empty($lockkey) || empty($fromkey) || empty($decsize)) return;
        while(true){
            $locked = $this->lock($lockkey);
            if($locked!==1){
                sleep(2);
                continue;
            }
            $this->decrBy($fromkey, $decsize);//减去下一次抓取时的偏移量
            $this->unlock($lockkey);
            break;
        }
    }
}

?>