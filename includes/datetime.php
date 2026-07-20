<?php
/**
 * BrightBlaze – Date and Timezone Helpers
 *
 * Timezone policy:
 *   - Database connections are configured to use UTC where supported.
 *   - PHP date_default_timezone_set is set to APP_TIMEZONE (default Asia/Kuwait).
 *   - User-facing dates and times are displayed in Asia/Kuwait.
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
 * Format a date/time value for user display in the configured timezone.
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
 * Format a date-only value for user display.
 *
 * @param  mixed  $value  A date string, DateTime, or DateTimeImmutable
 * @param  string $format PHP date format (default: 'd M Y')
 * @return string         Formatted string, or '—' for invalid/null input
 */
function format_date(mixed $value, string $format = 'd M Y'): string
{
    return format_datetime($value, $format);
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