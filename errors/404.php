<?php
require_once __DIR__ . '/../backend/security.php';
ait_bootstrap_security();
require_once __DIR__ . '/../backend/error-page.php';
ait_error_page(404);
