# TikScraperPHP (fork)

Un wrapper en PHP ≥ 8.1 para la API web de TikTok. **Fork de [pablouser1/TikScraperPHP](https://github.com/pablouser1/TikScraperPHP)** con la capa de peticiones/firma reescrita para que vuelva a funcionar en 2026.

## Qué cambia en este fork

El upstream firma las peticiones con un montaje de Selenium/ChromeDriver en proceso que se quedó obsoleto cuando TikTok cambió su esquema anti-bot (ahora exige `X-Gnarly` además de `X-Bogus`). Este fork reemplaza esa capa:

- **Firma vía sidecar [carcabot/tiktok-signature](https://github.com/carcabot/tiktok-signature).** `Sender::sendApi()` construye la URL de la API, la envía por POST al endpoint `/signature` del firmador y descarga el resultado con Guzzle usando la `signed_url` + cookies + UA que devuelve. Produce `X-Bogus` + `X-Gnarly` actuales.
- **Selenium eliminado por completo.** `Stream.php` y `Downloaders/BaseDownloader.php` ya no dependen de él; `php-webdriver/webdriver` fuera del `composer.json`.
- **Fingerprint Mac/Safari** corregido en `Helpers/Request.php` para que coincida con el navigator del firmador, más un `device_id` configurable.
- **Preferencia de CDN:** las respuestas se post-procesan para que el `playAddr`/`downloadAddr` de cada item prefiera una URL `*.tiktokcdn*.com` del `bitrateInfo` cuando exista (las URLs `webapp-prime` están protegidas). El vídeo que no tenga variante CDN se gestiona aguas abajo (ver el sidecar yt-dlp del fork de ProxiTok).

## Configuración

```php
$api = new \TikScraper\Api([
    'browser' => [ 'url' => 'http://tiktok-signer_app:8080' ], // URL base del firmador carcabot
    'device_id' => '7520531026079925774',                      // device id de 19 dígitos
    'verify_fp' => '',                                          // opcional
], $cacheEngine);
```

`browser.url` se reutiliza como la URL base del firmador (ya no hay ChromeDriver de por medio).

## Proyectos relacionados

- **[ProxiTok (fork)](https://github.com/darlopvil/ProxiTok)** — el frontend que consume esta librería.
- **[carcabot/tiktok-signature](https://github.com/carcabot/tiktok-signature)** — el sidecar de firma (se ejecuta aparte).
- **[ttdlp](https://github.com/darlopvil/ttdlp)** — sidecar yt-dlp para la reproducción real del vídeo.

## Créditos

Todo el trabajo original es de [Pablo Ferreiro](https://github.com/pablouser1) y los créditos del upstream (TikTok-API-PHP, TikTok-Api, tiktok-signature, tiktok-scraper, puppeteer-extra stealth). Este fork solo cambia la capa de transporte/firma.

## Licencia

La misma que el upstream (MIT).