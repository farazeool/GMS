<?php
/**
 * BrightBlaze – Online/Offline Status Checker
 * Automatically detects connectivity and updates sync state.
 */

class OnlineChecker
{
    private PDO $pdo;
    private SyncState $state;
    private RemoteApiClient $apiClient;

    private const CHECK_INTERVAL = 60; // seconds between checks
    private const PING_TIMEOUT = 5; // seconds

    public function __construct(PDO $pdo, SyncState $state, RemoteApiClient $apiClient)
    {
        $this->pdo = $pdo;
        $this->state = $state;
        $this->apiClient = $apiClient;
    }

    /**
     * Check current connection status.
     * Updates the stored state and returns the current status.
     */
    public function check(): array
    {
        $lastCheck = (int) $this->state->get('last_connectivity_check');
        $now = time();

        // Throttle checks to avoid hammering the network
        if (($now - $lastCheck) < self::CHECK_INTERVAL) {
            return $this->getCachedStatus();
        }

        $isOnline = $this->performCheck();
        $this->state->set('is_online', $isOnline ? '1' : '0');
        $this->state->set('last_connectivity_check', (string) $now);
        $this->state->set('check_method', $isOnline ? 'ping_ok' : 'ping_failed');

        if ($isOnline) {
            $this->state->set('last_online_at', date('Y-m-d H:i:s'));
        } else {
            $this->state->set('last_offline_at', date('Y-m-d H:i:s'));
        }

        return [
            'online' => $isOnline,
            'check_time' => date('c'),
            'method' => $isOnline ? 'ping_ok' : 'ping_failed',
        ];
    }

    /**
     * Perform the actual connectivity check.
     */
    private function performCheck(): bool
    {
        // Method 1: Ping the remote API if configured
        if ($this->apiClient->isConfigured()) {
            return $this->apiClient->ping();
        }

        // Method 2: Try to reach a reliable external host
        return $this->checkInternetConnectivity();
    }

    /**
     * Check basic internet connectivity via DNS or HTTP.
     */
    private function checkInternetConnectivity(): bool
    {
        // Try DNS resolution first (fastest check)
        $dnsOk = $this->checkDNS();
        if ($dnsOk) {
            return true;
        }

        // Fallback: try to connect to a known reliable host
        return $this->checkHost();
    }

    /**
     * DNS-based connectivity check.
     */
    private function checkDNS(): bool
    {
        $hosts = [
            'google.com',
            'cloudflare.com',
            'github.com',
        ];

        foreach ($hosts as $host) {
            $records = @dns_get_record($host, DNS_A, $authns, $addtl);
            if (!empty($records)) {
                return true;
            }
            // Fallback for systems without DNS functions
            $ip = @gethostbyname($host);
            if ($ip !== $host) {
                return true;
            }
        }

        return false;
    }

    /**
     * Direct host connectivity check.
     */
    private function checkHost(): bool
    {
        $hosts = [
            ['host' => '8.8.8.8', 'port' => 53],
            ['host' => '1.1.1.1', 'port' => 80],
            ['host' => '208.67.222.222', 'port' => 53],
        ];

        foreach ($hosts as $target) {
            $fp = @fsockopen($target['host'], $target['port'], $errno, $errstr, self::PING_TIMEOUT);
            if ($fp) {
                fclose($fp);
                return true;
            }
        }

        return false;
    }

    /**
     * Wait for connectivity and return once available.
     * Used for auto-resume after outage.
     */
    public function waitForConnectivity(int $timeoutSeconds = 300): bool
    {
        $start = time();
        while ((time() - $start) < $timeoutSeconds) {
            if ($this->performCheck()) {
                $this->state->set('is_online', '1');
                $this->state->set('last_online_at', date('Y-m-d H:i:s'));
                return true;
            }
            sleep(10); // Check every 10 seconds
        }

        return false;
    }

    /**
     * Get cached status without performing a new check.
     */
    public function getCachedStatus(): array
    {
        return [
            'online' => $this->state->get('is_online') === '1',
            'check_time' => $this->state->get('last_connectivity_check'),
            'method' => $this->state->get('check_method'),
            'cached' => true,
        ];
    }

    /**
     * Register connectivity change callbacks.
     */
    public function onConnectivityChanged(callable $callback): void
    {
        $this->connectivityCallbacks[] = $callback;
    }

    private array $connectivityCallbacks = [];

    /**
     * Notify registered callbacks of connectivity changes.
     */
    private function notifyConnectivityChange(bool $isOnline): void
    {
        foreach ($this->connectivityCallbacks as $callback) {
            try {
                $callback($isOnline);
            } catch (Exception $e) {
                // ...
            }
        }
    }
}