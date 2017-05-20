<?php
if(count($argv)<5){
    echo "need country tasktotal tasksn substasktotal params!";
    exit;
}
$country = $argv[1];
$tasktotal = $argv[2];
$tasksn = $argv[3];
$subtasktotal = $argv[4];

for($i=0;$i<$subtasktotal;$i++){
    $shellcmd = "cd /data/panda;nohup /usr/local/php/bin/php think ebay --cmd listingsold --country $country -t $tasktotal -s $tasksn ";
    $shellcmd .= " --subtasktotal $subtasktotal --subtasksn $i >>/data/panda/runtime/log/panda/listing_sold.log 2>&1 &";
//    echo "\n$shellcmd";
    $results = shell_exec($shellcmd);
    echo "\n".json_encode($results);
}
//nohup /usr/local/php/bin/php think ebay --cmd listing -t 6 -s 5 --subtasktotal 8 --subtasksn 0 >>/data/panda/runtime/log/panda/listing_category.log 2>&1 &

?>