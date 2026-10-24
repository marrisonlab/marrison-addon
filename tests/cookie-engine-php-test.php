<?php
/** Current engine suite: local orchestration replaces the retired remote protocol. */
foreach (array('cookie-local-engine-php-test.php', 'cookie-catalog-storage-php-test.php') as $test) {
    passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/' . $test), $status);
    if ($status !== 0) { exit($status); }
}
