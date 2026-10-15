<?php
$f = 'C:/xampp/htdocs/mardin-highschool/student/quiz-take.php';
$c = file_get_contents($f);
echo "Size: " . strlen($c) . "\n";
echo "CRLF: " . substr_count($c, "\r\n") . "\n";
echo "LF: " . substr_count($c, "\n") . "\n";
echo "CR only: " . substr_count($c, "\r") . "\n";
echo "Null bytes: " . substr_count($c, "\0") . "\n";
// Check for BOM
$bytes = unpack('C3bom', $c);
echo "First bytes: " . $bytes['bom1'] . " " . $bytes['bom2'] . " " . $bytes['bom3'] . "\n";
