<?php
$f = '/Users/josipmikec/Documents/2rich/2rich.capital/app/assets/css/market-data.css';
$c = file_get_contents($f);

$search = '.md-watchlist-item { display:flex; align-items:center; gap:8px; width:100%; padding:8px; color:#aaa; border-radius:6px; text-align:left; font:600 10px/1.2 "Montserrat",sans-serif; }
.md-watchlist-item:hover { background:rgba(242,202,80,.08); color:#F2CA50; }
.md-watchlist-remove { margin-left:auto; color:#555; font-size:14px; }
.md-watchlist-empty { display:block; padding:12px 8px; color:#555; font:400 10px/1.4 "Montserrat",sans-serif; }';

$replace = '.md-watchlist-item { display:flex; align-items:center; gap:8px; width:100%; padding:10px 16px; color:#d1d4dc; background:transparent; border:none; border-bottom:1px solid #2a2e39; border-radius:0; text-align:left; font:400 13px/1.2 -apple-system, BlinkMacSystemFont, "Trebuchet MS", Roboto, Ubuntu, sans-serif; cursor:pointer; }
.md-watchlist-item:hover { background:#2a2e39; color:#fff; }
.md-watchlist-remove { margin-left:auto; color:#787b86; font-size:16px; display:none; }
.md-watchlist-item:hover .md-watchlist-remove { display:block; }
.md-watchlist-remove:hover { color:#d1d4dc; }
.md-watchlist-empty { display:block; padding:16px; color:#787b86; font:400 13px/1.4 -apple-system, BlinkMacSystemFont, "Trebuchet MS", Roboto, Ubuntu, sans-serif; }';

if (strpos($c, $search) !== false) {
    $c = str_replace($search, $replace, $c);
    file_put_contents($f, $c);
    echo "Patched CSS\n";
} else {
    echo "Could not find target CSS block\n";
}
?>
