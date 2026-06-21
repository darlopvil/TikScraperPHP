<?php
namespace TikScraper\Items;

use TikScraper\Cache;
use TikScraper\Models\Feed;
use TikScraper\Models\Info;
use TikScraper\Sender;

class Video extends Base {
    private ?object $item = null;

    function __construct(string $term, Sender $sender, Cache $cache) {
        parent::__construct($term, 'video', $sender, $cache);
        if (!isset($this->info)) {
            $this->info();
        }
    }

    public function info(): self {
        if (is_numeric($this->term)) {
            // ID numérico: detalle por API firmada, SIN raspar HTML → mata el reto WAF.
            // preferCdn() en Sender ya reescribe itemInfo->itemStruct al CDN automáticamente.
            $req = $this->sender->sendApi('/item/detail/', [
                "itemId" => $this->term
            ], "/");

            $info = Info::fromReq($req);
            if ($info->meta->success && isset($req->jsonBody->itemInfo->itemStruct)) {
                $this->item = $req->jsonBody->itemInfo->itemStruct;
                $info->setDetail($this->item->author);
                $info->setStats($this->item->stats);
            }
        } else {
            // Short link /t/<code>: sin itemId → se queda el rehydrate HTML como fallback.
            $req = $this->sender->sendHTML('/t/' . $this->term, 'www');

            $info = Info::fromReq($req);
            if ($info->meta->success
                && $req->hasRehidrate()
                && isset($req->rehidrateState->__DEFAULT_SCOPE__->{'webapp.video-detail'})) {
                $root = $req->rehidrateState->__DEFAULT_SCOPE__->{'webapp.video-detail'};
                $this->item = $root->itemInfo->itemStruct;
                $info->setDetail($this->item->author);
                $info->setStats($this->item->stats);
            }
        }
        $this->info = $info;

        return $this;
    }

    public function feed(): self {
        $this->cursor = 0;
        if ($this->infoOk()) {
            $preloaded = $this->handleFeedCache();
            if (!$preloaded && $this->item !== null) {
                $this->feed = Feed::fromObj((object) [
                    "items" => [$this->item],
                    "hasMore" => false,
                    "cursor" => 0
                ]);
            }
        }
        return $this;
    }
}
