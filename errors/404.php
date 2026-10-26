<?php
// Served for unknown URLs (and for 403s, so private paths look the same as missing ones).
require_once __DIR__ . '/../backend/security.php';
ait_bootstrap_security();
require_once __DIR__ . '/../backend/error-page.php';
ait_error_page(404);
