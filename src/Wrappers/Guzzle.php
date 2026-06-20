<?php
namespace TikScraper\Wrappers;
use GuzzleHttp\Client;

/**
 * Wrapper de GuzzleHTTP autónomo (ya no depende de Selenium).
 * Hace las peticiones reales a TikTok con la signed_url + cookies + UA del firmador.
 */
class Guzzle {
    private Client $client;

    function __construct(array $config) {
        $httpConfig = [
            'timeout' => 10.0,
            'http_errors' => false,
            'allow_redirects' => true
        ];
        if (isset($config['proxy']) && $config['proxy'] !== '') {
            $httpConfig['proxy'] = $config['proxy'];
        }
        $this->client = new Client($httpConfig);
    }

    public function getClient(): Client {
        return $this->client;
    }
}