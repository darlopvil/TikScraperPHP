<?php
namespace TikScraper;
use TikScraper\Helpers\Request;
use TikScraper\Helpers\Tokens;
use TikScraper\Models\Response;
use TikScraper\Wrappers\Guzzle;
use TikScraper\Wrappers\Signer;

/**
 * Clase central de peticiones a TikTok.
 * Reescrita: firma vía sidecar carcabot (/signature) + fetch propio con Guzzle.
 */
class Sender {
    private const WEB_URL = "https://www.tiktok.com";
    private const API_URL = self::WEB_URL . "/api";
    private const HTML_UA = "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36";

    private Tokens $tokens;
    private Signer $signer;
    private Guzzle $guzzle;

    function __construct(array $config) {
        $this->tokens = new Tokens($config);
        $this->signer = new Signer($config);
        $this->guzzle = new Guzzle($config);
    }

    public function sendApi(string $endpoint, array $query = [], string $referrer = "/"): Response {
        $url = self::API_URL . $endpoint . Request::buildQuery(
            $query,
            $this->tokens->getVerifyFp(),
            $this->tokens->getDeviceId()
        );

        $data = ["type" => "json", "code" => -1, "success" => false, "data" => null, "headers" => []];

        $signed = $this->signer->sign($url);
        if ($signed === null || !isset($signed->signed_url)) {
            $data["code"] = 503;
            return new Response($data);
        }

        try {
            $res = $this->guzzle->getClient()->get($signed->signed_url, [
                'headers' => [
                    'User-Agent' => $signed->navigator->user_agent ?? self::HTML_UA,
                    'Cookie' => $signed->cookies ?? '',
                    'Accept' => 'application/json',
                    'Referer' => self::WEB_URL . '/'
                ]
            ]);
            $code = $res->getStatusCode();
            $body = (string) $res->getBody();
            $data["code"] = $code;
            $data["success"] = $code >= 200 && $code < 400;
            $data["data"] = $body === '' ? null : json_decode($body);
        } catch (\Throwable $e) {
            $data["code"] = 503;
        }

        return new Response($data);
    }

    public function sendHTML(string $endpoint, string $subdomain): Response {
        $url = "https://" . $subdomain . ".tiktok.com" . $endpoint;
        $data = ["type" => "html", "code" => -1, "success" => false, "data" => null];

        try {
            $res = $this->guzzle->getClient()->get($url, [
                'headers' => [
                    'User-Agent' => self::HTML_UA,
                    'Accept' => 'text/html,application/xhtml+xml',
                    'Referer' => self::WEB_URL . '/'
                ]
            ]);
            $code = $res->getStatusCode();
            $data["code"] = $code;
            $data["success"] = $code >= 200 && $code < 400;
            $data["data"] = (string) $res->getBody();
        } catch (\Throwable $e) {
            $data["code"] = 503;
        }

        return new Response($data);
    }
}