<?php
$f = 'C:\xampp\htdocs\mardin-highschool\student\quiz-take.php';
$c = file_get_contents($f);
// Strip HTML to find PHP issues
$php = preg_replace('/<\?php(.*?)\?>/s', "$1", $c);
file_put_contents('C:\xampp\tmp\quiz_php_only.php', "<?php\n" . $php . "\n?>");
echo "Done\n";
