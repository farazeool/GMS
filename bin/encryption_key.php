<?php
/**
 * BrightBlaze – Encryption Key Generator
 *
 * Generate a random base64-encoded key for the ENCRYPTION_KEY environment variable.
 *
 * Usage:
 *   php bin/encryption_key.php
 */

$key = base64_encode(random_bytes(32));
echo "ENCRYPTION_KEY={$key}" . PHP_EOL;
