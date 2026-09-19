<?php
$file = __DIR__ . '/core/leajlak_debug.html';
if (file_exists($file)) {
    echo file_get_contents($file);
} else {
    echo "No debug file found.";
}
