<?php
namespace TikScraper\Wrappers;
use GuzzleHttp\Client;

/**
 * Wrapper de GuzzleHTTP autónomo (ya no depende de Selenium).
 * UA por defecto para Stream/Downloader; Sender lo sobreescribe por request
 * con el user_agent que devuelve el firmador.
 */
class Guzzle {
    private Client $client;
    const DEFAULT_UA = "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36";

    function __construct(array $config) {
        $httpConfig = [
            'timeout' => 15.0,
            'http_errors' => false,
            'allow_redirects' => true,
            'headers' => [
                'User-Agent' => (isset($config['user_agent']) && $config['user_agent'] !== '')
                    ? $config['user_agent']
                    : self::DEFAULT_UA
            ]
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