<?php
// Check PHP syntax using php -l output
$f = escapeshellarg('C:\xampp\htdocs\mardin-highschool\student\quiz-take.php');
$output = shell_exec('C:\xampp\php\php.exe -l ' . $f . ' 2>&1');
echo $output;
