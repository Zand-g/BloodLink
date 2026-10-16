<?php
// Database settings. XAMPP defaults: user root, empty password.
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'bloodlink');

// Philippines has no daylight saving, so one fixed zone keeps device and server in agreement.
date_default_timezone_set('Asia/Manila');
