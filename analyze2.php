<?php
$f = 'C:\xampp\htdocs\mardin-highschool\student\quiz-take.php';
$c = file_get_contents($f);
echo "endif: " . substr_count($c, "endif") . "\n";
echo "endwhile: " . substr_count($c, "endwhile") . "\n";
echo "endfor: " . substr_count($c, "endfor") . "\n";
echo "if (line: " . substr_count($c, "if (") . "\n";
echo "if :" . substr_count($c, "if :") . "\n";
echo "{ : " . substr_count($c, "{") . "\n";
echo "} : " . substr_count($c, "}") . "\n";
echo "} else: " . substr_count($c, "} else") . "\n";
echo "} elseif: " . substr_count($c, "} elseif") . "\n";
echo "elseif: " . substr_count($c, "elseif") . "\n";
echo "Last 200 chars:\n" . substr($c, -200);
