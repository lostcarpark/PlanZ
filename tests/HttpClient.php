<?php
// Minimal curl-based HTTP client for Functional tests. Deliberately dependency-free
// (no Guzzle) so the root composer.json only needs PHPUnit.

final class HttpClient
{
    private string $baseUrl;
    private string $cookieJar;

    public function __construct(?string $baseUrl = null)
    {
        $this->baseUrl = rtrim($baseUrl ?? getenv('PLANZ_TEST_BASE_URL') ?: 'https://planz.ddev.site', '/');
        $this->cookieJar = tempnam(sys_get_temp_dir(), 'planz-test-cookies-');
    }

    public function __destruct()
    {
        if (is_file($this->cookieJar)) {
            unlink($this->cookieJar);
        }
    }

    /** @param array<string,string> $fields */
    public function post(string $path, array $fields): array
    {
        return $this->request('POST', $path, $fields);
    }

    public function get(string $path): array
    {
        return $this->request('GET', $path);
    }

    /** @param array<string,string> $fields */
    private function request(string $method, string $path, array $fields = []): array
    {
        $ch = curl_init($this->baseUrl . $path);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_COOKIEJAR => $this->cookieJar,
            CURLOPT_COOKIEFILE => $this->cookieJar,
            // The test target is either a local DDEV site (self-signed cert) or a
            // plain http:// PHP built-in server in CI — never a public endpoint.
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_TIMEOUT => 15,
        ]);
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields));
        }
        $body = curl_exec($ch);
        if ($body === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException("HTTP $method $path failed: $error");
        }
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ['status' => $status, 'body' => $body];
    }

    public function login(string $badgeidOrEmail, string $password): array
    {
        return $this->post('/doLogin.php', [
            'badgeid' => $badgeidOrEmail,
            'passwd' => $password,
        ]);
    }
}
