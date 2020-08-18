<?php
$array = explode(',', $_GET['array']);

// 修正はここから
$number = count($array);

for ($i = 0; $i < $number; $i++) {
    for($j = 0; $j < $number-$i; $j++){
        if($array[$j-1]>$array[$j]){
            $baseValue=$array[$j-1];
            $array[$j-1]=$array[$j];
            $array[$j]=$baseValue;            
        }
    }
}
// 修正はここまで

echo "<pre>";
print_r($array);
echo "</pre>";

