<?php
$f = '/Users/josipmikec/Documents/2rich/2rich.capital/app/market-data/index.php';
$c = file_get_contents($f);

$search = '                <!-- Chart container -->
                <div style="flex:1; min-width:0; display:flex; flex-direction:column;">
                    <div id="tv_chart_container" style="flex:1;"></div>
                </div>';

$replace = '                <!-- Chart container -->
                <div style="flex:1; min-width:0; display:block;">
                    <div id="tv_chart_container" style="width:100%;"></div>
                </div>';

if (strpos($c, $search) !== false) {
    $c = str_replace($search, $replace, $c);
    file_put_contents($f, $c);
    echo "Fixed chart height\n";
} else {
    echo "Could not find target block\n";
}
?>
