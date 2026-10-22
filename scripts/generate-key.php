<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This command must be run from the command line.\n");
    exit(1);
}

$key = bin2hex(random_bytes(32));

fwrite(STDOUT, "========================================================\n");
fwrite(STDOUT, "AXA-Kenneth — Production APP_KEY Generator\n");
fwrite(STDOUT, "========================================================\n\n");
fwrite(STDOUT, "Generated 64-character cryptographically secure key:\n\n");
fwrite(STDOUT, "APP_KEY={$key}\n\n");
fwrite(STDOUT, "Add or replace this line in your production .env file:\n");
fwrite(STDOUT, "public_html/axa-kenmark/.env\n\n");
