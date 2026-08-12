<?php
if (file_exists(__DIR__ . '/storage/config.php')) {
    return require __DIR__ . '/storage/config.php';
}

return require __DIR__ . '/config.sample.php';
