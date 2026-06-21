# TikScraperPHP (fork)

Un wrapper en PHP ≥ 8.1 para la API web de TikTok. **Fork de [pablouser1/TikScraperPHP](https://github.com/pablouser1/TikScraperPHP)** con la capa de peticiones/firma reescrita y varios endpoints rerouteados para que vuelva a funcionar en 2026.

## Qué cambia en este fork

El upstream firma las peticiones con un montaje de Selenium/ChromeDriver en proceso que se quedó obsoleto cuando TikTok cambió su esquema anti-bot (ahora exige `X-Gnarly` además de `X-Bogus`). Además, varios endpoints que antes devolvían datos hoy responden vacíos. Este fork lo arregla:

### Firma

- **Firma vía sidecar [carcabot/tiktok-signature](https://github.com/carcabot/tiktok-signature).** `Sender::sendApi()` construye la URL de la API, la envía por POST al endpoint del firmador y descarga el resultado con Guzzle usando la `signed_url` + cookies + UA que devuelve. Produce `X-Bogus` + `X-Gnarly` actuales.
- **Selenium eliminado por completo.** `Stream.php` y `Downloaders/BaseDownloader.php` ya no dependen de él; `php-webdriver/webdriver` fuera del `composer.json`.
- **Fingerprint Mac/Safari** corregido en `Helpers/Request.php` para que coincida con el navigator del firmador, más un `device_id` configurable.
- **Preferencia de CDN:** las respuestas se post-procesan (`Sender::preferCdn`) para que el `playAddr`/`downloadAddr` de cada item prefiera una URL `*.tiktokcdn*.com` cuando exista (las URLs `webapp-prime` están protegidas). Cubre tanto `itemList` como `itemInfo->itemStruct`.

### Endpoints rerouteados

Algunos endpoints siguen funcionando con la firma (FYP `recommend/item_list`, tags `challenge/item_list`); otros están muertos o WAF-capados y se resuelven por otra vía:

- **`User::info()` → SSR `webapp.user-detail`.** `/api/user/detail/` da 200 vacío y `/api/search/user/full/` está capado. En su lugar se hace `sendHTML('/@usuario')` y se extrae `userInfo.user` + `userInfo.stats` del rehidratado (esquema canónico camelCase → la plantilla y `feed()` lo consumen sin remapeo).
- **`Meta`** acepta tanto `webapp.video-detail` como `webapp.user-detail` en su rama HTML (si no, marcaba `STATE_DECODE_ERROR` en perfiles).
- **`Video::info()` → API `/item/detail/`.** Para IDs numéricos se pide el detalle por API firmada en vez de raspar el HTML (esto mata el `STATE_DECODE_ERROR` por reto WAF). Los short-links `/t/<code>` conservan el fallback de rehidratado HTML.
- **`User::feed()` → sidecar ttdlp.** `/api/post/item_list/` devuelve 200 vacío (WAF-capado, no es cuestión de `count`/params/secUid/msToken). El feed de un perfil se obtiene de [ttdlp](https://github.com/darlopvil/ttdlp) con `yt-dlp --flat-playlist` y cada entry se mapea a la forma `itemStruct` que esperan las plantillas. El autor de cada item se reutiliza del `info()` (SSR). Paginación vía `--playlist-start` con cursor numérico.
- **`Sender::sidecar()`** — método nuevo que hace un GET al sidecar ttdlp (URL base por config `ttdlp_url`, por defecto `http://ttdlp_app:8080`) y devuelve el JSON decodificado.

## Configuración

```php
$api = new \TikScraper\Api([
    'browser' => [ 'url' => 'http://tiktok-signer_app:8080' ], // URL base del firmador carcabot
    'device_id' => '7520531026079925774',                      // device id de 19 dígitos
    'verify_fp' => '',                                          // opcional
    'ttdlp_url' => 'http://ttdlp_app:8080',                    // sidecar yt-dlp (feed de usuario)
], $cacheEngine);
```

`browser.url` se reutiliza como la URL base del firmador (ya no hay ChromeDriver de por medio).

## Proyectos relacionados

- **[ProxiTok (fork)](https://github.com/darlopvil/ProxiTok)** — el frontend que consume esta librería.
- **[carcabot/tiktok-signature](https://github.com/carcabot/tiktok-signature)** — el sidecar de firma (se ejecuta aparte).
- **[ttdlp](https://github.com/darlopvil/ttdlp)** — sidecar yt-dlp para reproducción, descarga, audio y listado de perfiles.

## Créditos

Todo el trabajo original es de [Pablo Ferreiro](https://github.com/pablouser1) y los créditos del upstream (TikTok-API-PHP, TikTok-Api, tiktok-signature, tiktok-scraper, puppeteer-extra stealth). Este fork cambia la capa de transporte/firma y el rerouteo de info/feed/vídeo.

## Licencia

La misma que el upstream (MIT).