<?php

/**
 * Tests for date/timezone helpers (includes/datetime.php).
 * Covers formatting, timezone conversion, and edge cases.
 */
class DateTimeTest extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->reloadEnv();
    }

    public function test_datetime_create_from_string(): void
    {
        $dt = datetime_create('2024-01-15 10:30:00');
        $this->assertInstanceOf(DateTimeImmutable::class, $dt);
        $this->assertEquals('2024-01-15 10:30:00', $dt->format('Y-m-d H:i:s'));
    }

    public function test_datetime_create_from_null(): void
    {
        $this->assertNull(datetime_create(null));
    }

    public function test_datetime_create_from_empty_string(): void
    {
        $this->assertNull(datetime_create(''));
    }

    public function test_datetime_create_from_em_dash(): void
    {
        $this->assertNull(datetime_create('—'));
    }

    public function test_datetime_create_from_mutable(): void
    {
        $mutable = new DateTime('2024-06-15 14:30:00');
        $immutable = datetime_create($mutable);
        $this->assertInstanceOf(DateTimeImmutable::class, $immutable);
    }

    public function test_datetime_create_from_immutable(): void
    {
        $original = new DateTimeImmutable('2024-06-15 14:30:00');
        $result = datetime_create($original);
        $this->assertSame($original, $result);
    }

    public function test_datetime_create_invalid_string(): void
    {
        $this->assertNull(datetime_create('not-a-date'));
    }

    public function test_format_datetime_default_format(): void
    {
        $result = format_datetime('2024-01-15 10:30:00');
        $this->assertStringContainsString('Jan', $result);
        $this->assertStringContainsString('2024', $result);
    }

    public function test_format_datetime_null_returns_em_dash(): void
    {
        $this->assertEquals('—', format_datetime(null));
    }

    public function test_format_datetime_empty_string_returns_em_dash(): void
    {
        $this->assertEquals('—', format_datetime(''));
    }

    public function test_format_datetime_em_dash_returns_em_dash(): void
    {
        $this->assertEquals('—', format_datetime('—'));
    }

    public function test_format_date_default_format(): void
    {
        $result = format_date('2024-01-15');
        $this->assertEquals('15 Jan 2024', $result);
    }

    public function test_format_date_custom_format(): void
    {
        $result = format_date('2024-01-15', 'Y-m-d');
        $this->assertEquals('2024-01-15', $result);
    }

    public function test_format_date_null_returns_em_dash(): void
    {
        $this->assertEquals('—', format_date(null));
    }

    public function test_now_utc_returns_immutable_in_utc(): void
    {
        $now = now_utc();
        $this->assertInstanceOf(DateTimeImmutable::class, $now);
        $this->assertEquals('UTC', $now->getTimezone()->getName());
    }

    public function test_now_local_returns_immutable_in_kuwait(): void
    {
        $now = now_local();
        $this->assertInstanceOf(DateTimeImmutable::class, $now);
        $this->assertEquals('Asia/Kuwait', $now->getTimezone()->getName());
    }

    public function test_utc_to_local_converts_timezone(): void
    {
        $utc = new DateTimeImmutable('2024-01-15 12:00:00', new DateTimeZone('UTC'));
        $local = utc_to_local($utc);
        $this->assertEquals('Asia/Kuwait', $local->getTimezone()->getName());
        // Kuwait is UTC+3, so 12:00 UTC becomes 15:00 AST
        $this->assertEquals('15:00', $local->format('H:i'));
    }

    public function test_local_to_utc_converts_timezone(): void
    {
        $local = new DateTimeImmutable('2024-01-15 15:00:00', new DateTimeZone('Asia/Kuwait'));
        $utc = local_to_utc($local);
        $this->assertEquals('UTC', $utc->getTimezone()->getName());
        // 15:00 AST = 12:00 UTC
        $this->assertEquals('12:00', $utc->format('H:i'));
    }

    public function test_format_kwd_three_decimals(): void
    {
        $this->assertEquals('15.500 KWD', format_kwd(15.5));
    }

    public function test_format_kwd_null(): void
    {
        $this->assertEquals('0.000 KWD', format_kwd(null));
    }

    public function test_format_kwd_empty_string(): void
    {
        $this->assertEquals('0.000 KWD', format_kwd(''));
    }

    public function test_format_kwd_zero(): void
    {
        $this->assertEquals('0.000 KWD', format_kwd(0));
    }

    public function test_format_kwd_string(): void
    {
        $this->assertEquals('42.750 KWD', format_kwd('42.75'));
    }
}