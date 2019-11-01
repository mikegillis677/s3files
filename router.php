<?php
// router.php
if (preg_match('/\/images\//', $_SERVER["REQUEST_URI"])) {
    return false;    // serve the requested resource as-is.
} else {
    require __DIR__ . '/public/index.php';
}
