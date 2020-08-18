<?php
$array = explode(',', $_GET['array']);

// 修正はここから
for ($i = 0; $i < count($array); $i++) {
        if($array[$i-1]>$array[$i]){
            $baseValue=$array[$i-1];
            $array[$i-1]=$array[$i];
            $array[$i]=$baseValue;            
        }    
}
// 修正はここまで

echo "<pre>";
print_r($array);
echo "</pre>";

?>