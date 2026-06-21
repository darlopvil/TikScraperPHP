<?php
namespace TikScraper\Items;

use TikScraper\Cache;
use TikScraper\Models\Feed;
use TikScraper\Models\Info;
use TikScraper\Sender;

class User extends Base {
    function __construct(string $term, Sender $sender, Cache $cache) {
        parent::__construct($term, 'user', $sender, $cache);
        if (!isset($this->info)) {
            $this->info();
        }
    }

    public function info(): self {
        // /user/detail/ está muerto y /search/user/full/ da 200 vacío (search va muy capado).
        // La página SSR /@user SÍ rehidrata webapp.user-detail con el esquema canónico (camelCase),
        // que es justo lo que esperan plantilla y feed() → cero remapeo.
        $req = $this->sender->sendHTML('/@' . $this->term, 'www');

        $info = Info::fromReq($req);
        if ($info->meta->success
            && $req->hasRehidrate()
            && isset($req->rehidrateState->__DEFAULT_SCOPE__->{'webapp.user-detail'}->userInfo->user)) {
            $ui = $req->rehidrateState->__DEFAULT_SCOPE__->{'webapp.user-detail'}->userInfo;
            $info->setDetail($ui->user);
            $info->setStats($ui->stats ?? $ui->statsV2 ?? new \stdClass);
        }
        $this->info = $info;

        return $this;
    }

    public function feed(int $cursor = 0): self {
        $this->cursor = $cursor;

        if ($this->infoOk()) {
            $preloaded = $this->handleFeedCache();
            if (!$preloaded) {
                // /post/item_list/ devuelve 200 vacío (WAF-capado). Listamos el perfil con yt-dlp vía ttdlp.
                $count = 30;
                $res = $this->sender->sidecar('/user', [
                    "user"  => urldecode($this->term),
                    "start" => $cursor + 1,
                    "count" => $count
                ]);

                $items = [];
                $entries = ($res->entries ?? []);
                foreach ($entries as $e) {
                    if (isset($e->id)) $items[] = $this->mapEntry($e);
                }

                $got = count($items);
                $this->feed = Feed::fromObj((object) [
                    "items"   => $items,
                    "hasMore" => $got >= $count,
                    "cursor"  => $cursor + $got
                ]);
            }
        }
        return $this;
    }

    /** Mapea una entry de yt-dlp (flat-playlist) a la forma itemStruct que esperan las plantillas. */
    private function mapEntry(object $e): object {
        // Covers: yt-dlp da thumbnails con id 'cover'/'dynamicCover'/'originCover'
        $covers = [];
        foreach (($e->thumbnails ?? []) as $t) {
            if (isset($t->id, $t->url)) $covers[$t->id] = $t->url;
        }
        $origin = $covers['originCover'] ?? ($covers['cover'] ?? '');
        $dyn    = $covers['dynamicCover'] ?? $origin;

        // El autor es siempre el del perfil (lo tenemos ya del SSR en info->detail)
        $author = (object) [
            "uniqueId"    => $this->info->detail->uniqueId ?? urldecode($this->term),
            "nickname"    => $this->info->detail->nickname ?? '',
            "avatarThumb" => $this->info->detail->avatarThumb ?? ($this->info->detail->avatarLarger ?? ''),
            "verified"    => $this->info->detail->verified ?? false
        ];

        return (object) [
            "id"         => (string) $e->id,
            "desc"       => $e->description ?? ($e->title ?? ''),
            "createTime" => (int) ($e->timestamp ?? 0),
            "author"     => $author,
            "music"      => (object) [
                "title"   => $e->track ?? '',
                "playUrl" => ''
            ],
            "stats"      => (object) [
                "playCount"    => (int) ($e->view_count ?? 0),
                "diggCount"    => (int) ($e->like_count ?? 0),
                "commentCount" => (int) ($e->comment_count ?? 0),
                "shareCount"   => (int) ($e->repost_count ?? 0)
            ],
            "video"      => (object) [
                "originCover"  => $origin,
                "dynamicCover" => $dyn,
                // Sentinela no-vacío: activa el bloque de descarga. download.latte usa id+uniqueId (ttdlp), NO este valor.
                "playAddr"     => "ttdlp",
                "downloadAddr" => "ttdlp"
            ],
            "challenges" => [],
            "textExtra"  => []
            // imagePost NO se setea → content.latte usa la rama <video> (stream por ttdlp)
        ];
    }
}
