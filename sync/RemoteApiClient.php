<?php
/**
 * BrightBlaze – Remote API Client
 * Handles communication with the remote synchronization server.
 * All endpoints are configurable - never hard-coded.
 */

class RemoteApiClient
{
    private PDO $pdo;
    private string $baseUrl;
    private string $apiKey;
    private int $timeout;
    private int $maxRetries;
    private int $retryDelay;

    private const HTTP_OK = 200;
    private const HTTP_CREATED = 201;
    private const HTTP_ACCEPTED = 202;
    private const HTTP_UNAUTHORIZED = 401;
    private const HTTP_CONFLICT = 409;
    private const HTTP_INTERNAL_ERROR = 500;

    /**
     * @param PDO $pdo Database connection
     * @param string $baseUrl Remote API base URL (from config)
     * @param string $apiKey API authentication key (from config)
     */
    public function __construct(PDO $pdo, string $baseUrl = '', string $apiKey = '')
    {
        $this->pdo = $pdo;
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->apiKey = $apiKey;
        $this->timeout = 30;
        $this->maxRetries = 3;
        $this->retryDelay = 2;
    }

    /**
     * Create an instance using configured settings.
     */
    public static function fromSettings(PDO $pdo): self
    {
        $settings = self::getSettings($pdo);
        return new self(
            $pdo,
            $settings['cloud_api_url'] ?? '',
            $settings['sync_api_key'] ?? ''
        );
    }

    /**
     * Check if remote server is configured.
     */
    public function isConfigured(): bool
    {
        return !empty($this->baseUrl) && !empty($this->apiKey);
    }

    /**
     * Check if remote server is reachable.
     */
    public function ping(): bool
    {
        if (!$this->isConfigured()) {
            return false;
        }

        $response = $this->makeRequest('GET', '/api/health');
        return $response['status'] === self::HTTP_OK;
    }

    /**
     * Push a batch of sync changes to the remote server.
     *
     * @param array $changes Array of change records
     * @return array Response with success flag and any errors
     */
    public function pushChanges(array $changes): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'error' => 'API not configured'];
        }

        $payload = [
            'changes' => $changes,
            'source_device' => $this->getDeviceId(),
            'timestamp' => date('c'),
        ];

        $response = $this->makeRequest('POST', '/api/sync/push', $payload);

        if ($response['status'] >= 200 && $response['status'] < 300) {
            $body = json_decode($response['body'], true);
            return [
                'success' => true,
                'accepted' => $body['accepted'] ?? [],
                'rejected' => $body['rejected'] ?? [],
            ];
        }

        return [
            'success' => false,
            'error' => $response['error'] ?? 'Unknown error',
            'status' => $response['status'],
        ];
    }

    /**
     * Pull changes from the remote server since the last sync.
     *
     * @param string|null $since Timestamp of last sync
     * @param int $limit Maximum records per request
     * @return array Response with changes and pagination info
     */
    public function pullChanges(?string $since = null, int $limit = 100): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'error' => 'API not configured'];
        }

        $params = [
            'since' => $since ?? date('Y-m-d H:i:s', strtotime('-7 days')),
            'limit' => $limit,
            'source_device' => $this->getDeviceId(),
        ];

        $response = $this->makeRequest('GET', '/api/sync/pull', $params);

        if ($response['status'] >= 200 && $response['status'] < 300) {
            $body = json_decode($response['body'], true);
            return [
                'success' => true,
                'changes' => $body['changes'] ?? [],
                'has_more' => $body['has_more'] ?? false,
                'next_cursor' => $body['next_cursor'] ?? null,
            ];
        }

        return [
            'success' => false,
            'error' => $response['error'] ?? 'Unknown error',
            'status' => $response['status'],
        ];
    }

    /**
     * Get sync status from remote server.
     */
    public function getRemoteSyncStatus(): array
    {
        if (!$this->isConfigured()) {
            return ['available' => false, 'reason' => 'not_configured'];
        }

        $response = $this->makeRequest('GET', '/api/sync/status');

        if ($response['status'] >= 200 && $response['status'] < 300) {
            $body = json_decode($response['body'], true);
            return ['available' => true] + ($body ?? []);
        }

        return [
            'available' => false,
            'reason' => $response['error'] ?? 'unknown',
        ];
    }

    /**
     * Resolve a conflict by sending resolution to remote.
     */
    public function resolveConflict(array $resolution): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'error' => 'API not configured'];
        }

        $payload = [
            'resolution' => $resolution,
            'source_device' => $this->getDeviceId(),
        ];

        $response = $this->makeRequest('POST', '/api/sync/conflict-resolve', $payload);

        return [
            'success' => $response['status'] >= 200 && $response['status'] < 300,
            'status' => $response['status'],
        ];
    }

    /**
     * Execute an HTTP request with retry logic.
     */
    private function makeRequest(string $method, string $endpoint, array $data = [], int $attempt = 1): array
    {
        $url = $this->baseUrl . $endpoint;

        $headers = [
            'Content-Type: application/json',
            'X-API-Key: ' . $this->apiKey,
            'X-Device-ID: ' . $this->getDeviceId(),
            'X-Timestamp: ' . date('c'),
        ];

        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        } elseif ($method === 'PUT') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($error) {
            if ($attempt < $this->maxRetries) {
                sleep($this->retryDelay * $attempt);
                return $this->makeRequest($method, $endpoint, $data, $attempt + 1);
            }
            return ['status' => 0, 'body' => '', 'error' => $error];
        }

        // Retry on server errors
        if ($httpCode >= 500 && $attempt < $this->maxRetries) {
            sleep($this->retryDelay * $attempt);
            return $this->makeRequest($method, $endpoint, $data, $attempt + 1);
        }

        return [
            'status' => $httpCode,
            'body' => $response ?? '',
            'error' => null,
        ];
    }

    /**
     * Get the unique device identifier for this installation.
     */
    private function getDeviceId(): string
    {
        $deviceId = $this->getSettingsValue('device_id');
        if (empty($deviceId)) {
            $deviceId = uuid_generate();
            $this->saveSettingsValue('device_id', $deviceId);
        }
        return $deviceId;
    }

    /**
     * Get all settings as array.
     */
    private static function getSettings(PDO $pdo): array
    {
        try {
            $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
            $settings = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
            return $settings;
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Get a single settings value.
     */
    private function getSettingsValue(string $key): string
    {
        try {
            $stmt = $this->pdo->prepare("SELECT `setting_value` FROM `settings` WHERE `setting_key` = ?");
            $stmt->execute([$key]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ? (string) $row['setting_value'] : '';
        } catch (PDOException $e) {
            return '';
        }
    }

    /**
     * Save a settings value.
     */
    private function saveSettingsValue(string $key, string $value): void
    {
        try {
            $stmt = $this->pdo->prepare(
                "INSERT INTO `settings` (`setting_key`, `setting_value`)
                 VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`)"
            );
            $stmt->execute([$key, $value]);
        } catch (PDOException $e) {
            // Non-fatal
        }
    }
}