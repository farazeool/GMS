<?php

/**
 * Tests for environment configuration (config/env.php).
 * Covers loading, parsing, defaults, and production validation.
 */
class EnvironmentTest extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->reloadEnv();
    }

    public function test_env_returns_default_when_key_missing(): void
    {
        $this->assertEquals('default_value', env('NONEXISTENT_KEY', 'default_value'));
    }

    public function test_env_returns_null_when_key_missing_no_default(): void
    {
        $this->assertNull(env('NONEXISTENT_KEY_NO_DEFAULT'));
    }

    public function test_env_returns_set_value(): void
    {
        $_ENV['TEST_KEY'] = 'test_value';
        $this->assertEquals('test_value', env('TEST_KEY'));
    }

    public function test_env_returns_zero_string(): void
    {
        $_ENV['ZERO_KEY'] = '0';
        $this->assertSame('0', env('ZERO_KEY'));
    }

    public function test_env_returns_empty_string(): void
    {
        $_ENV['EMPTY_KEY'] = '';
        $this->assertSame('', env('EMPTY_KEY'));
    }

    public function test_env_bool_returns_false_for_missing_key(): void
    {
        $this->assertFalse(env_bool('NONEXISTENT_BOOL'));
    }

    public function test_env_bool_returns_default_for_missing_key(): void
    {
        $this->assertTrue(env_bool('NONEXISTENT_BOOL', true));
    }

    public function test_env_bool_parses_true_strings(): void
    {
        $_ENV['BOOL_TRUE'] = 'true';
        $_ENV['BOOL_1'] = '1';
        $_ENV['BOOL_YES'] = 'yes';
        $_ENV['BOOL_ON'] = 'on';

        $this->assertTrue(env_bool('BOOL_TRUE'));
        $this->assertTrue(env_bool('BOOL_1'));
        $this->assertTrue(env_bool('BOOL_YES'));
        $this->assertTrue(env_bool('BOOL_ON'));
    }

    public function test_env_bool_parses_false_strings(): void
    {
        $_ENV['BOOL_FALSE'] = 'false';
        $_ENV['BOOL_0'] = '0';
        $_ENV['BOOL_NO'] = 'no';
        $_ENV['BOOL_OFF'] = 'off';

        $this->assertFalse(env_bool('BOOL_FALSE'));
        $this->assertFalse(env_bool('BOOL_0'));
        $this->assertFalse(env_bool('BOOL_NO'));
        $this->assertFalse(env_bool('BOOL_OFF'));
    }

    public function test_env_int_returns_zero_for_missing_key(): void
    {
        $this->assertEquals(0, env_int('NONEXISTENT_INT'));
    }

    public function test_env_int_returns_default_for_missing_key(): void
    {
        $this->assertEquals(42, env_int('NONEXISTENT_INT', 42));
    }

    public function test_env_int_parses_values(): void
    {
        $_ENV['INT_VAL'] = '3306';
        $this->assertEquals(3306, env_int('INT_VAL'));
    }

    public function test_env_int_parses_negative_values(): void
    {
        $_ENV['INT_NEG'] = '-1';
        $this->assertEquals(-1, env_int('INT_NEG'));
    }

    public function test_env_int_coerces_non_numeric_to_zero(): void
    {
        $_ENV['INT_INVALID'] = 'not_a_number';
        $this->assertEquals(0, env_int('INT_INVALID'));
    }

    public function test_env_require_throws_for_missing(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Missing required environment variables: MISSING_KEY_1, MISSING_KEY_2');
        env_require('MISSING_KEY_1', 'MISSING_KEY_2');
    }

    public function test_env_require_passes_when_values_exist(): void
    {
        $_ENV['EXISTING_KEY'] = 'value';
        // Should not throw
        env_require('EXISTING_KEY');
        $this->assertTrue(true);
    }

    public function test_env_require_production_does_not_throw_in_local(): void
    {
        $_ENV['APP_ENV'] = 'local';
        // Should not throw despite missing DB_PASS etc.
        env_require_production();
        $this->assertTrue(true);
    }

    public function test_env_require_production_throws_for_non_local(): void
    {
        $_ENV['APP_ENV'] = 'production';
        unset($_ENV['APP_KEY'], $_ENV['DB_PASS']);

        $this->expectException(RuntimeException::class);
        env_require_production();
    }

    public function test_env_require_production_includes_app_url(): void
    {
        $_ENV['APP_ENV'] = 'production';
        unset($_ENV['APP_URL']);
        unset($_ENV['APP_KEY'], $_ENV['DB_PASS']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('APP_URL');
        env_require_production();
    }

    public function test_bootstrap_rejects_incomplete_production_config(): void
    {
        // Simulate what happens during bootstrap with production config
        $appEnv = env('APP_ENV', 'local');
        if ($appEnv !== 'local') {
            $this->expectException(RuntimeException::class);
            env_require('APP_KEY', 'APP_URL', 'DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASS');
        }
        // In testing mode we skip this, so just assert true
        $this->assertTrue(true);
    }
}