<?php
/**
 * BrightBlaze – Date and Timezone Helpers
 *
 * Timezone policy:
 *   - Database connections are configured to use UTC where supported.
 *   - PHP date_default_timezone_set is set to APP_TIMEZONE (default Asia/Kuwait).
 *   - Database UTC datetime strings must be parsed explicitly with DateTimeZone('UTC')
 *     before converting to APP_TIMEZONE for display.
 *   - Date-only fields (YYYY-MM-DD) must not shift days due to timezone conversion.
 *   - Historical timestamps stored before this policy are not reinterpreted.
 *     They remain in whatever timezone the MySQL session default provided at insert time.
 *
 * Monetary values are preserved with three-decimal KWD precision.
 */

/**
 * Create a DateTimeImmutable from an arbitrary value.
 * Returns null for empty or invalid input.
 */
function datetime_create(mixed $value): ?DateTimeImmutable
{
    if ($value === null || $value === '' || $value === '—') {
        return null;
    }
    if ($value instanceof DateTimeImmutable) {
        return $value;
    }
    if ($value instanceof DateTime) {
        return DateTimeImmutable::createFromMutable($value);
    }
    try {
        $dt = new DateTimeImmutable((string) $value);
        return $dt;
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Parse a database UTC datetime string explicitly as UTC.
 * Database timestamps are stored in UTC; this ensures correct conversion.
 *
 * @param  string|null $value  A datetime string from the database (e.g. "2026-07-20 09:00:00")
 * @return DateTimeImmutable|null
 */
function datetime_from_db(?string $value): ?DateTimeImmutable
{
    if ($value === null || $value === '' || $value === '—') {
        return null;
    }
    try {
        $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $value, new DateTimeZone('UTC'));
        if ($dt === false) {
            // Fallback: try generic parser
            $dt = new DateTimeImmutable($value, new DateTimeZone('UTC'));
        }
        return $dt;
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Format a database UTC datetime string for user display in APP_TIMEZONE.
 * This is the primary helper for displaying stored timestamps.
 *
 * @param  string|null $value  A UTC datetime string from the database
 * @param  string      $format PHP date format (default: 'd M Y h:i A')
 * @return string              Formatted string, or '—' for invalid/null input
 */
function format_db_datetime(?string $value, string $format = 'd M Y h:i A'): string
{
    $dt = datetime_from_db($value);
    if ($dt === null) {
        return '—';
    }
    try {
        $tz = new DateTimeZone(env('APP_TIMEZONE', 'Asia/Kuwait'));
        return $dt->setTimezone($tz)->format($format);
    } catch (Exception $e) {
        return '—';
    }
}

/**
 * Format a date-only value for user display.
 * Date-only values (YYYY-MM-DD) are displayed as-is without timezone shifting.
 *
 * @param  mixed  $value  A date string, DateTime, or DateTimeImmutable
 * @param  string $format PHP date format (default: 'd M Y')
 * @return string         Formatted string, or '—' for invalid/null input
 */
function format_date(mixed $value, string $format = 'd M Y'): string
{
    $dt = datetime_create($value);
    if ($dt === null) {
        return '—';
    }
    try {
        // For date-only values, use the date parts directly without timezone shifting
        return $dt->format($format);
    } catch (Exception $e) {
        return '—';
    }
}

/**
 * Format a date/time value for user display in the configured timezone.
 * Generic helper for DateTimeImmutable objects or non-database strings.
 *
 * @param  mixed       $value  A date string, DateTime, or DateTimeImmutable
 * @param  string      $format PHP date format (default: 'd M Y h:i A')
 * @return string              Formatted string, or '—' for invalid/null input
 */
function format_datetime(mixed $value, string $format = 'd M Y h:i A'): string
{
    $dt = datetime_create($value);
    if ($dt === null) {
        return '—';
    }
    try {
        $tz = new DateTimeZone(env('APP_TIMEZONE', 'Asia/Kuwait'));
        return $dt->setTimezone($tz)->format($format);
    } catch (Exception $e) {
        return '—';
    }
}

/**
 * Get the current UTC DateTimeImmutable.
 */
function now_utc(): DateTimeImmutable
{
    return new DateTimeImmutable('now', new DateTimeZone('UTC'));
}

/**
 * Get the current local (APP_TIMEZONE) DateTimeImmutable.
 */
function now_local(): DateTimeImmutable
{
    return new DateTimeImmutable('now', new DateTimeZone(env('APP_TIMEZONE', 'Asia/Kuwait')));
}

/**
 * Convert a UTC DateTimeImmutable to the local timezone for display.
 */
function utc_to_local(DateTimeImmutable $dt): DateTimeImmutable
{
    return $dt->setTimezone(new DateTimeZone(env('APP_TIMEZONE', 'Asia/Kuwait')));
}

/**
 * Convert a local DateTimeImmutable to UTC for storage.
 */
function local_to_utc(DateTimeImmutable $dt): DateTimeImmutable
{
    return $dt->setTimezone(new DateTimeZone('UTC'));
}

/**
 * Format a monetary value as KWD with three decimal places.
 */
function format_kwd(mixed $value): string
{
    if ($value === null || $value === '') {
        return '0.000 KWD';
    }
    return number_format((float) $value, 3, '.', '') . ' KWD';
}