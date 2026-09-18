<?php
$f = '/Users/josipmikec/Documents/2rich/2rich.capital/app/market-data/index.php';
$c = file_get_contents($f);

// 1. Toolstrip padding-top: 8px -> 4px
$search1 = '<div class="md-chart-toolstrip" style="width:40px; flex-shrink:0; border-left:1px solid #1e1e1e; display:flex; flex-direction:column; align-items:center; padding-top:8px; gap:12px;">';
$replace1 = '<div class="md-chart-toolstrip" style="width:40px; flex-shrink:0; border-left:1px solid #1e1e1e; display:flex; flex-direction:column; align-items:center; padding-top:4px; gap:12px;">';
$c = str_replace($search1, $replace1, $c);

// 2. Watchlist Heading padding: 12px 16px -> 10px 16px
$search2 = '<div class="md-watchlist-heading" style="padding:12px 16px; border-bottom:1px solid #1e1e1e; display:flex; justify-content:space-between; align-items:center; min-width:250px;">';
$replace2 = '<div class="md-watchlist-heading" style="padding:10px 16px; border-bottom:1px solid #1e1e1e; display:flex; justify-content:space-between; align-items:center; min-width:250px; height:38px; box-sizing:border-box;">';
$c = str_replace($search2, $replace2, $c);

// 3. Add script for initial state and update toggle function
$search3 = '            <script>
            function toggleSidebarWatchlist() {
                const sb = document.getElementById(\'mdWatchlistSidebar\');
                if (sb.style.width === \'0px\') {
                    sb.style.width = \'250px\';
                } else {
                    sb.style.width = \'0px\';
                }
            }
            </script>';

$replace3 = '            <script>
            if (localStorage.getItem(\'md_watchlist_closed\') === \'1\') {
                document.getElementById(\'mdWatchlistSidebar\').style.display = \'none\';
                // force immediate layout change without transition by briefly turning off transition
                const sb = document.getElementById(\'mdWatchlistSidebar\');
                sb.style.transition = \'none\';
                sb.style.width = \'0px\';
                sb.style.display = \'flex\';
                setTimeout(() => sb.style.transition = \'width 0.3s ease\', 50);
            }
            
            function toggleSidebarWatchlist() {
                const sb = document.getElementById(\'mdWatchlistSidebar\');
                if (sb.style.width === \'0px\') {
                    sb.style.width = \'250px\';
                    localStorage.setItem(\'md_watchlist_closed\', \'0\');
                } else {
                    sb.style.width = \'0px\';
                    localStorage.setItem(\'md_watchlist_closed\', \'1\');
                }
            }
            </script>';

$c = str_replace($search3, $replace3, $c);

file_put_contents($f, $c);
echo "Patched\n";
?>
