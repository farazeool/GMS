<?php
/**
 * Shared view helpers.
 */

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function status_badge(string $status): string
{
    $slug = strtolower(str_replace(' ', '-', $status));
    return '<span class="badge bb-status-' . e($slug) . '">' . e($status) . '</span>';
}

function priority_badge(string $priority): string
{
    $slug = strtolower($priority);
    return '<span class="badge bb-priority-' . e($slug) . '">' . e($priority) . '</span>';
}

function format_date(?string $value, string $format = 'd M Y'): string
{
    if (!$value) {
        return '—';
    }
    return date($format, strtotime($value));
}
