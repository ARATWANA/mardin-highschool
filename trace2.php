<?php
// Find unclosed if/else/endif in PHP files
function checkFile($f) {
    $c = file_get_contents($f);
    $tokens = token_get_all($c);
    
    $ifDepth = 0;
    $braceDepth = 0;
    $line = 1;
    $issues = [];
    
    for ($i = 0; $i < count($tokens); $i++) {
        $t = $tokens[$i];
        if (is_array($t)) {
            $line = $t[2];
            if ($t[0] === T_IF) { $ifDepth++; }
            if ($t[0] === T_ELSEIF) { /* stays same depth */ }
            if ($t[0] === T_ELSE) { /* stays same depth */ }
            if ($t[0] === T_ENDIF) { $ifDepth--; }
            if ($t[0] === T_OPEN_TAG) { /* PHP opens */ }
            if ($t[0] === T_CLOSE_TAG) { /* PHP closes */ }
        } else {
            if ($t === '{') { $braceDepth++; }
            if ($t === '}') { $braceDepth--; }
        }
    }
    
    echo "$f:\n";
    echo "  ifDepth: $ifDepth\n";
    echo "  braceDepth: $braceDepth\n";
    if ($ifDepth > 0) echo "  ISSUE: $ifDepth unclosed if(s)\n";
    if ($braceDepth > 0) echo "  ISSUE: $braceDepth unclosed brace(s)\n";
    if ($ifDepth === 0 && $braceDepth === 0) echo "  OK\n";
}

checkFile('C:/xampp/htdocs/mardin-highschool/teacher/homework.php');
checkFile('C:/xampp/htdocs/mardin-highschool/teacher/quiz.php');
checkFile('C:/xampp/htdocs/mardin-highschool/teacher/quiz-results.php');
