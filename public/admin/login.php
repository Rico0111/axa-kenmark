<?php

declare(strict_types=1);

if (!isset($_GET['r'])) {
    $_GET['r'] = 'login';
}

require __DIR__ . '/index.php';
