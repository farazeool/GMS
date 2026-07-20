<?php

/**
 * Tests for secret redaction in error_handler.php.
 */
class RedactTest extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->reloadEnv();
    }

    public function test_redact_db_pass(): void
    {
        $message = 'Connection failed with DB_PASS=supersecret';
        $redacted = redact_secrets($message);
        $this->assertStringNotContainsString('supersecret', $redacted);
        $this->assertStringContainsString('DB_PASS=******', $redacted);
    }

    public function test_redact_app_key(): void
    {
        $message = 'APP_KEY=base64:abc123secretkey';
        $redacted = redact_secrets($message);
        $this->assertStringNotContainsString('abc123secretkey', $redacted);
        $this->assertStringContainsString('APP_KEY=******', $redacted);
    }

    public function test_redact_dsn_password(): void
    {
        $message = 'DSN: mysql:host=localhost;port=3306;dbname=test;password=mysecretpass';
        $redacted = redact_secrets($message);
        $this->assertStringNotContainsString('mysecretpass', $redacted);
        $this->assertStringContainsString('password=******', $redacted);
    }

    public function test_redact_connection_string(): void
    {
        $message = 'mysql:host=prod-db.example.com;port=3306;dbname=brightblaze_prod';
        $redacted = redact_secrets($message);
        $this->assertStringNotContainsString('prod-db.example.com', $redacted);
        $this->assertStringNotContainsString('brightblaze_prod', $redacted);
    }

    public function test_redact_bearer_token(): void
    {
        $message = 'Authorization: Bearer eyJhbGciOiJIUzI1NiJ9.token';
        $redacted = redact_secrets($message);
        $this->assertStringNotContainsString('eyJhbGciOiJIUzI1NiJ9.token', $redacted);
        $this->assertStringContainsString('Authorization: Bearer ******', $redacted);
    }

    public function test_redact_session_id(): void
    {
        $message = 'Session started PHPSESSID=abcdef123456';
        $redacted = redact_secrets($message);
        $this->assertStringNotContainsString('abcdef123456', $redacted);
        $this->assertStringContainsString('PHPSESSID=******', $redacted);
    }

    public function test_redact_mixed_secrets(): void
    {
        $message = 'DB_PASS=secret123 APP_KEY=appkey456 token=abc';
        $redacted = redact_secrets($message);
        $this->assertStringNotContainsString('secret123', $redacted);
        $this->assertStringNotContainsString('appkey456', $redacted);
    }

    public function test_redact_no_secrets_changes_nothing(): void
    {
        $message = 'This is a normal log message without secrets';
        $this->assertEquals($message, redact_secrets($message));
    }

    public function test_redact_empty_string(): void
    {
        $this->assertEquals('', redact_secrets(''));
    }
}