<?php
$f = '/Users/josipmikec/Documents/2rich/2rich.capital/app/market-data/index.php';
$c = file_get_contents($f);

$search = '        <div class="md-pane active" id="tab-feeds">

            <div style="display:flex; gap:16px;">
                <!-- Chart container -->
                <div class="md-chart-wrap" style="flex:1; min-width:0; border-radius:12px;">
                    <div id="tv_chart_container"></div>
                </div>

                <!-- Watchlist Sidebar -->
                <div class="md-watchlist-sidebar" style="width:250px; flex-shrink:0; background:#111; border:1px solid #1e1e1e; border-radius:12px; display:flex; flex-direction:column; box-shadow: 0 8px 32px rgba(0,0,0,0.3);">
                    <div class="md-watchlist-heading" style="padding:16px; border-bottom:1px solid #1e1e1e; display:flex; justify-content:space-between; align-items:center;">
                        <span style="color:#ccc; font:700 11px/1 \'Montserrat\',sans-serif; text-transform:uppercase; letter-spacing:0.08em;"><span aria-hidden="true" style="color:#F2CA50; margin-right:6px;">★</span> Watchlist</span>
                        <button type="button" class="md-watchlist-add" onclick="addCurrentToWatchlist()" title="Add current symbol to watchlist" aria-label="Add current symbol to watchlist" style="border:none; background:none; color:#F2CA50; font-size:18px; cursor:pointer; padding:0; line-height:1;">＋</button>
                    </div>
                    <div id="watchlistItems" class="md-watchlist-items" style="flex:1; overflow-y:auto; padding:12px; display:flex; flex-direction:column; gap:6px;">
                        <span class="md-watchlist-empty">No favourites yet</span>
                    </div>
                </div>
            </div>

        </div>';

$replace = '        <div class="md-pane active" id="tab-feeds">

            <div class="md-chart-wrap" style="display:flex; flex-direction:row; background:#131722; padding:0;">
                
                <!-- Chart container -->
                <div style="flex:1; min-width:0; display:flex; flex-direction:column;">
                    <div id="tv_chart_container" style="flex:1;"></div>
                </div>

                <!-- Watchlist Sidebar (collapsible) -->
                <div class="md-watchlist-sidebar" id="mdWatchlistSidebar" style="width:250px; flex-shrink:0; border-left:1px solid #2a2e39; display:flex; flex-direction:column; transition: width 0.3s ease; overflow:hidden;">
                    <div class="md-watchlist-heading" style="padding:16px; border-bottom:1px solid #2a2e39; display:flex; justify-content:space-between; align-items:center; min-width:250px;">
                        <span style="color:#d1d4dc; font:600 12px/1 -apple-system, BlinkMacSystemFont, \'Trebuchet MS\', Roboto, Ubuntu, sans-serif; text-transform:uppercase; letter-spacing:0.04em;"><span aria-hidden="true" style="color:#F2CA50; margin-right:6px;">★</span> Watchlist</span>
                        <div style="display:flex; gap:12px; align-items:center;">
                            <button type="button" class="md-watchlist-add" onclick="addCurrentToWatchlist()" title="Add current symbol to watchlist" aria-label="Add current symbol to watchlist" style="border:none; background:none; color:#b2b5be; font-size:16px; cursor:pointer; padding:0; line-height:1;">＋</button>
                            <button type="button" onclick="toggleSidebarWatchlist()" title="Close watchlist" style="border:none; background:none; color:#b2b5be; font-size:18px; cursor:pointer; padding:0; line-height:1;">×</button>
                        </div>
                    </div>
                    <div id="watchlistItems" class="md-watchlist-items" style="flex:1; overflow-y:auto; overflow-x:hidden; padding:0; display:flex; flex-direction:column; min-width:250px;">
                        <span class="md-watchlist-empty" style="color:#787b86; padding:16px;">No favourites yet</span>
                    </div>
                </div>

                <!-- Toolstrip -->
                <div class="md-chart-toolstrip" style="width:48px; flex-shrink:0; border-left:1px solid #2a2e39; display:flex; flex-direction:column; align-items:center; padding-top:12px; gap:12px;">
                    <button type="button" onclick="toggleSidebarWatchlist()" title="Watchlist" style="border:none; background:none; color:#b2b5be; cursor:pointer; padding:8px; border-radius:4px;" onmouseover="this.style.color=\'#d1d4dc\'; this.style.background=\'#2a2e39\';" onmouseout="this.style.color=\'#b2b5be\'; this.style.background=\'none\';">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                    </button>
                </div>
            </div>
            
            <script>
            function toggleSidebarWatchlist() {
                const sb = document.getElementById(\'mdWatchlistSidebar\');
                if (sb.style.width === \'0px\') {
                    sb.style.width = \'250px\';
                } else {
                    sb.style.width = \'0px\';
                }
            }
            </script>

        </div>';

if (strpos($c, '<div class="md-pane active" id="tab-feeds">') !== false) {
    $c = str_replace($search, $replace, $c);
    file_put_contents($f, $c);
    echo "Patched layout\n";
} else {
    echo "Could not find target block\n";
}
?>
