<?php

/**
 * Tests for secret redaction in error_handler.php.
 * Also tests nested context redaction.
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

    public function test_redact_api_key(): void
    {
        $message = 'api_key=sk-live-abcdef123456';
        $redacted = redact_secrets($message);
        $this->assertStringNotContainsString('sk-live-abcdef123456', $redacted);
        $this->assertStringContainsString('api_key=******', $redacted);
    }

    public function test_redact_sync_api_key(): void
    {
        $message = 'sync_api_key=my-sync-secret-key';
        $redacted = redact_secrets($message);
        $this->assertStringNotContainsString('my-sync-secret-key', $redacted);
        $this->assertStringContainsString('sync_api_key=******', $redacted);
    }

    public function test_redact_secret_value(): void
    {
        $message = 'secret=my-secret-value';
        $redacted = redact_secrets($message);
        $this->assertStringNotContainsString('my-secret-value', $redacted);
        $this->assertStringContainsString('secret=******', $redacted);
    }

    public function test_redact_token_value(): void
    {
        $message = 'token=abc123def456';
        $redacted = redact_secrets($message);
        $this->assertStringNotContainsString('abc123def456', $redacted);
        $this->assertStringContainsString('token=******', $redacted);
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

    // --- Nested context redaction tests ---

    public function test_redact_nested_password_in_context(): void
    {
        $context = [
            'credentials' => [
                'username' => 'admin',
                'password' => 'super-secret-123',
            ],
        ];
        $redacted = redact_sensitive_value($context);
        $this->assertEquals('******', $redacted['credentials']['password']);
        $this->assertEquals('admin', $redacted['credentials']['username']);
    }

    public function test_redact_nested_api_key_in_context(): void
    {
        $context = [
            'config' => [
                'api_key' => 'sk-live-abc123',
                'endpoint' => 'https://api.example.com',
            ],
        ];
        $redacted = redact_sensitive_value($context);
        $this->assertEquals('******', $redacted['config']['api_key']);
        $this->assertEquals('https://api.example.com', $redacted['config']['endpoint']);
    }

    public function test_redact_nested_db_pass_in_context(): void
    {
        $context = [
            'database' => [
                'host' => 'db.example.com',
                'db_pass' => 'my-db-password',
                'name' => 'brightblaze_prod',
            ],
        ];
        $redacted = redact_sensitive_value($context);
        $this->assertEquals('******', $redacted['database']['db_pass']);
    }

    public function test_redact_deeply_nested_token(): void
    {
        $context = [
            'auth' => [
                'session' => [
                    'token' => 'eyJhbGciOiJIUzI1NiJ9.abc',
                    'expires' => '2027-01-01',
                ],
            ],
        ];
        $redacted = redact_sensitive_value($context);
        // 'session' is a sensitive key, so the entire 'session' value is redacted
        $this->assertEquals('******', $redacted['auth']['session']);
    }

    public function test_redact_log_error_with_nested_context(): void
    {
        $context = [
            'user' => [
                'name' => 'test_user',
                'token' => 'secret-token-abc',
            ],
        ];

        // Capture log output by using log_error and checking the file
        $dir = log_directory();
        $file = $dir . '/brightblaze-' . date('Y-m-d') . '.log';
        @unlink($file);

        log_error('Test error message', $context);

        $log = file_get_contents($file);
        $this->assertStringNotContainsString('secret-token-abc', $log);
        $this->assertStringContainsString('******', $log);
        $this->assertStringContainsString('test_user', $log);

        @unlink($file);
    }

    public function test_redact_log_error_context_with_password(): void
    {
        $context = [
            'connection' => [
                'password' => 'my-password-123',
                'host' => 'localhost',
            ],
        ];

        $dir = log_directory();
        $file = $dir . '/brightblaze-' . date('Y-m-d') . '.log';
        @unlink($file);

        log_error('Connection error', $context);

        $log = file_get_contents($file);
        $this->assertStringNotContainsString('my-password-123', $log);
        $this->assertStringContainsString('******', $log);

        @unlink($file);
    }
}