<?php
$f = '/Users/josipmikec/Documents/2rich/2rich.capital/app/market-data/index.php';
$c = file_get_contents($f);

// 1. Toolstrip width 48px -> 40px, padding-top 12px -> 8px, stroke-width 2 -> 1.5
$search1 = '<div class="md-chart-toolstrip" style="width:48px; flex-shrink:0; border-left:1px solid #1e1e1e; display:flex; flex-direction:column; align-items:center; padding-top:12px; gap:12px;">
                    <button type="button" onclick="toggleSidebarWatchlist()" title="Watchlist" style="border:none; background:none; color:#b2b5be; cursor:pointer; padding:8px; border-radius:4px;" onmouseover="this.style.color=\'#d1d4dc\'; this.style.background=\'#1e1e1e\';" onmouseout="this.style.color=\'#b2b5be\'; this.style.background=\'none\';">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                    </button>
                </div>';
$replace1 = '<div class="md-chart-toolstrip" style="width:40px; flex-shrink:0; border-left:1px solid #1e1e1e; display:flex; flex-direction:column; align-items:center; padding-top:8px; gap:12px;">
                    <button type="button" onclick="toggleSidebarWatchlist()" title="Watchlist" style="border:none; background:none; color:#b2b5be; cursor:pointer; padding:6px; border-radius:4px;" onmouseover="this.style.color=\'#d1d4dc\'; this.style.background=\'#1e1e1e\';" onmouseout="this.style.color=\'#b2b5be\'; this.style.background=\'none\';">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                    </button>
                </div>';

if (strpos($c, $search1) !== false) {
    $c = str_replace($search1, $replace1, $c);
}

// 2. Watchlist Heading
$search2 = '<div class="md-watchlist-heading" style="padding:16px; border-bottom:1px solid #1e1e1e; display:flex; justify-content:space-between; align-items:center; min-width:250px;">
                        <span style="color:#d1d4dc; font:600 12px/1 -apple-system, BlinkMacSystemFont, \'Trebuchet MS\', Roboto, Ubuntu, sans-serif; text-transform:uppercase; letter-spacing:0.04em;"><span aria-hidden="true" style="color:#F2CA50; margin-right:6px;">★</span> Watchlist</span>
                        <div style="display:flex; gap:12px; align-items:center;">
                            <button type="button" class="md-watchlist-add" onclick="addCurrentToWatchlist()" title="Add current symbol to watchlist" aria-label="Add current symbol to watchlist" style="border:none; background:none; color:#b2b5be; font-size:16px; cursor:pointer; padding:0; line-height:1;">＋</button>
                            <button type="button" onclick="toggleSidebarWatchlist()" title="Close watchlist" style="border:none; background:none; color:#b2b5be; font-size:18px; cursor:pointer; padding:0; line-height:1;">×</button>
                        </div>
                    </div>';
$replace2 = '<div class="md-watchlist-heading" style="padding:12px 16px; border-bottom:1px solid #1e1e1e; display:flex; justify-content:space-between; align-items:center; min-width:250px;">
                        <span style="color:#d1d4dc; font:600 12px/1 -apple-system, BlinkMacSystemFont, \'Trebuchet MS\', Roboto, Ubuntu, sans-serif; text-transform:uppercase; letter-spacing:0.04em; display:flex; align-items:center;"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#F2CA50" stroke-width="2" style="margin-right:8px;"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"></path></svg> Watchlist</span>
                        <div style="display:flex; gap:8px; align-items:center;">
                            <button type="button" class="md-watchlist-add" onclick="addCurrentToWatchlist()" title="Add current symbol to watchlist" aria-label="Add current symbol to watchlist" style="border:none; background:none; color:#b2b5be; cursor:pointer; padding:4px; display:flex; align-items:center;">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                            </button>
                            <button type="button" onclick="toggleSidebarWatchlist()" title="Close watchlist" style="border:none; background:none; color:#b2b5be; cursor:pointer; padding:4px; display:flex; align-items:center;">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                            </button>
                        </div>
                    </div>';

if (strpos($c, $search2) !== false) {
    $c = str_replace($search2, $replace2, $c);
}

// 3. Render function in JS needs to use the bookmark SVG instead of star
$search3 = 'target.innerHTML = list.length ? list.map(symbol => `<button type="button" class="md-watchlist-item" onclick="changeSymbol(\'${escapeHtml(symbol)}\'); toggleWatchlist();"><span aria-hidden="true">★</span>${escapeHtml(symbol)}<span class="md-watchlist-remove" onclick="event.stopPropagation(); removeFromWatchlist(\'${escapeHtml(symbol)}\')">×</span></button>`).join(\'\') : \'<span class="md-watchlist-empty">No favourites yet</span>\';';
$replace3 = 'target.innerHTML = list.length ? list.map(symbol => `<button type="button" class="md-watchlist-item" onclick="changeSymbol(\'${escapeHtml(symbol)}\'); toggleWatchlist();"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:4px;"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"></path></svg>${escapeHtml(symbol)}<span class="md-watchlist-remove" onclick="event.stopPropagation(); removeFromWatchlist(\'${escapeHtml(symbol)}\')"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg></span></button>`).join(\'\') : \'<span class="md-watchlist-empty" style="color:#787b86; padding:16px;">No favourites yet</span>\';';

if (strpos($c, $search3) !== false) {
    $c = str_replace($search3, $replace3, $c);
}

file_put_contents($f, $c);
echo "Patched design\n";
?>
