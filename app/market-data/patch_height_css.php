<?php
$f = '/Users/josipmikec/Documents/2rich/2rich.capital/app/assets/css/market-data.css';
$c = file_get_contents($f);

$search = '#tv_chart_container { width: 100%; height: 580px; }';
$replace = '#tv_chart_container { width: 100%; height: calc(100vh - 280px); min-height: 580px; }';

if (strpos($c, $search) !== false) {
    $c = str_replace($search, $replace, $c);
    file_put_contents($f, $c);
    echo "Fixed chart css height\n";
} else {
    echo "Could not find target block\n";
}
?>
