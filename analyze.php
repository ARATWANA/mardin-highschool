<?php
$f = 'C:\xampp\htdocs\mardin-highschool\teacher\homework.php';
$c = file_get_contents($f);
echo "Lines: " . substr_count($c, "\n") . "\n";
echo "endif: " . substr_count($c, "endif") . "\n";
echo "if(: " . substr_count($c, "if (") . "\n";
echo "} else: " . substr_count($c, "} else") . "\n";
echo "} : " . substr_count($c, "}") . "\n";
echo "if :" . substr_count($c, "if :") . "\n";
echo "End:\n" . substr($c, -50);
