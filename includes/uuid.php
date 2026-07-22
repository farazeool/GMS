<?php
/**
 * BrightBlaze – UUID/Binary Helper Functions
 * Provides efficient UUID storage using BINARY(16) while keeping CHAR(36) for display.
 * Place in includes/uuid.php and require where needed.
 */

if (!function_exists('uuid_generate')) {
    /**
     * Generate a standard RFC 4122 version 4 UUID.
     * @return string UUID in standard 36-char format (xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx)
     */
    function uuid_generate(): string
    {
        // PHP 7+ has random_bytes, fallback for older
        if (function_exists('random_bytes')) {
            $bytes = random_bytes(16);
        } else {
            $bytes = openssl_random_pseudo_bytes(16);
        }

        // Set version to 4 (random)
        $bytes[6] = chr(ord($bytes[6]) & 0x0f | 0x40);
        // Set variant to RFC 4122 (10xx)
        $bytes[8] = chr(ord($bytes[8]) & 0x3f | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}

if (!function_exists('uuid_is_valid')) {
    /**
     * Validate a UUID string format.
     * @param string $uuid
     * @return bool
     */
    function uuid_is_valid(string $uuid): bool
    {
        return (bool) preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $uuid
        );
    }
}

if (!function_exists('uuid_to_bin')) {
    /**
     * Convert 36-char UUID to 16-byte binary string.
     * @param string $uuid 36-char UUID
     * @return string 16-byte binary string
     */
    function uuid_to_bin(string $uuid): string
    {
        $hex = str_replace('-', '', $uuid);
        return hex2bin($hex);
    }
}

if (!function_exists('bin_to_uuid')) {
    /**
     * Convert 16-byte binary string to 36-char UUID.
     * @param string $bin 16-byte binary string
     * @return string 36-char UUID
     */
    function bin_to_uuid(string $bin): string
    {
        $hex = bin2hex($bin);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split($hex, 4));
    }
}

if (!function_exists('uuid_short')) {
    /**
     * Generate a short unique ID (base64-encoded random bytes).
     * Useful for queue IDs, correlation IDs, etc.
     * @param int $bytes Number of random bytes (default 16 = 22 chars base64)
     * @return string URL-safe base64 string
     */
    function uuid_short(int $bytes = 16): string
    {
        $bytes = random_bytes($bytes);
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }
}