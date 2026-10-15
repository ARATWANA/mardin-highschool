<?php
$f = 'C:\xampp\htdocs\mardin-highschool\student\quiz-take.php';
$c = file_get_contents($f);
$tokens = token_get_all($c);
$depth = 0;
$line = 0;
$stack = [];
foreach ($tokens as $t) {
    if (is_array($t)) {
        if ($t[0] === T_OPEN_TAG) { $depth++; array_push($stack, $t[2]); }
        if ($t[0] === T_CLOSE_TAG) { $depth--; array_pop($stack); }
        if (in_array($t[0], [T_IF, T_FOREACH, T_FOR, T_WHILE, T_SWITCH])) {
            array_push($stack, token_name($t[0]) . ' @ line ' . $t[2]);
        }
        if ($t[0] === T_ENDIF) { array_pop($stack); }
        if ($t[0] === T_ENDFOREACH) { array_pop($stack); }
        if ($t[0] === T_ENDFOR) { array_pop($stack); }
        if ($t[0] === T_ENDWHILE) { array_pop($stack); }
        if (defined('T_ENDSWITCH') && $t[0] === T_ENDSWITCH) { array_pop($stack); }
    } else {
        if ($t === '{') { array_push($stack, '{ @ line ' . (is_array($t) ? $t[2] : '?')); }
        if ($t === '}') { array_pop($stack); }
    }
}
echo "Depth: $depth\n";
echo "Stack remaining: " . count($stack) . "\n";
foreach ($stack as $s) { echo "  Unclosed: $s\n"; }
