<?php
// Find missing endif in quiz-take.php by analyzing template blocks
$f = 'C:\xampp\htdocs\mardin-highschool\student\quiz-take.php';
$c = file_get_contents($f);

// Find all template if/else/elseif/endif/foreach/endforeach
preg_match_all('/<\?php\s*(if|elseif|else|endif|foreach|endforeach|for|endfor|while|endwhile)\b([^:]*):/S', $c, $m);

$ops = $m[1];
$counts = array_count_values($ops);
echo "=== Template blocks ===\n";
foreach ($counts as $k => $v) {
    echo "$k: $v\n";
}

// Also find all if/else/endif patterns without colon syntax
preg_match_all('/<\?php\s*(if|endif|else|elseif)\b/', $c, $m2);
echo "\n=== All PHP control ===\n";
$counts2 = array_count_values($m2[1]);
foreach ($counts2 as $k => $v) {
    echo "$k: $v\n";
}

// Find all endif; patterns (with semicolon)
preg_match_all('/<\?php\s+endif;\s*\?>/', $c, $m3);
echo "\nendif; count: " . count($m3[0]) . "\n";

// Find all if(...): patterns
preg_match_all('/<\?php\s+if\s*\(/', $c, $m4);
echo "if( count: " . count($m4[0]) . "\n";

