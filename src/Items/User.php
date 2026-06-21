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
                $query = [
                    "count" => 35,
                    "coverFormat" => 2,
                    "cursor" => $cursor,
                    "from_page" => "user",
                    "needPinnedItemIds" => "true",
                    "post_item_list_request_type" => 0,
                    "secUid" => $this->info->detail->secUid,
                    "userId" => $this->info->detail->id
                ];

                $req = $this->sender->sendApi('/post/item_list/', $query, "/@" . $this->term);
                $this->feed = Feed::fromReq($req);
            }
        }
        return $this;
    }
}
