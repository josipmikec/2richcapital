<?php
$f = '/Users/josipmikec/Documents/2rich/2rich.capital/app/market-data/index.php';
$c = file_get_contents($f);

// 1. Toolstrip padding-top 4px -> 2px and change SVG to bookmark
$search1 = '<div class="md-chart-toolstrip" style="width:40px; flex-shrink:0; border-left:1px solid #1e1e1e; display:flex; flex-direction:column; align-items:center; padding-top:4px; gap:12px;">
                    <button type="button" onclick="toggleSidebarWatchlist()" title="Watchlist" style="border:none; background:none; color:#b2b5be; cursor:pointer; padding:6px; border-radius:4px;" onmouseover="this.style.color=\'#d1d4dc\'; this.style.background=\'#1e1e1e\';" onmouseout="this.style.color=\'#b2b5be\'; this.style.background=\'none\';">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                    </button>';
$replace1 = '<div class="md-chart-toolstrip" style="width:40px; flex-shrink:0; border-left:1px solid #1e1e1e; display:flex; flex-direction:column; align-items:center; padding-top:2px; gap:12px;">
                    <button type="button" onclick="toggleSidebarWatchlist()" title="Watchlist" style="border:none; background:none; color:#b2b5be; cursor:pointer; padding:6px; border-radius:4px;" onmouseover="this.style.color=\'#d1d4dc\'; this.style.background=\'#1e1e1e\';" onmouseout="this.style.color=\'#b2b5be\'; this.style.background=\'none\';">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"></path></svg>
                    </button>';
$c = str_replace($search1, $replace1, $c);

// 2. Watchlist heading height 38px -> 39px
$search2 = '<div class="md-watchlist-heading" style="padding:10px 16px; border-bottom:1px solid #1e1e1e; display:flex; justify-content:space-between; align-items:center; min-width:250px; height:38px; box-sizing:border-box;">';
$replace2 = '<div class="md-watchlist-heading" style="padding:10px 16px; border-bottom:1px solid #1e1e1e; display:flex; justify-content:space-between; align-items:center; min-width:250px; height:39px; box-sizing:border-box;">';
$c = str_replace($search2, $replace2, $c);

file_put_contents($f, $c);
echo "Patched\n";
?>
