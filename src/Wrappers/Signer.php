<?php
namespace TikScraper\Wrappers;
use GuzzleHttp\Client;

/**
 * Cliente para el firmador externo (carcabot/tiktok-signature).
 * Sustituye al viejo Selenium/SignTok: delega X-Bogus + X-Gnarly al sidecar.
 */
class Signer {
    private Client $client;
    private string $base;

    function __construct(array $config) {
        // Reutilizamos browser.url (env API_CHROMEDRIVER) como base del firmador
        $this->base = rtrim($config['browser']['url'] ?? 'http://tiktok-signer_app:8080', '/');
        $this->client = new Client(['timeout' => 25.0, 'http_errors' => false]);
    }

    /**
     * Llama a POST /signature y devuelve el objeto `data` (signed_url, cookies, navigator) o null.
     */
    public function sign(string $url): ?object {
        try {
            $res = $this->client->post($this->base . '/signature', [
                'headers' => ['Content-Type' => 'application/json'],
                'json' => ['url' => $url]
            ]);
            if ($res->getStatusCode() !== 200) {
                return null;
            }
            $body = json_decode((string) $res->getBody());
            if ($body !== null && isset($body->status, $body->data) && $body->status === 'ok') {
                return $body->data;
            }
        } catch (\Throwable $e) {
            // silencioso: null => Sender devuelve 503
        }
        return null;
    }
}
