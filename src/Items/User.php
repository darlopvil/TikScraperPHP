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
        // ANTES: /user/detail/ (muerto, da 200 vacío).
        // AHORA: /search/user/full/?keyword=<uniqueId> → esquema search/móvil (snake_case).
        $req = $this->sender->sendApi("/search/user/full/", [
            "keyword" => urldecode($this->term)
        ], "/@" . $this->term);

        $info = Info::fromReq($req);
        if ($info->meta->success && isset($req->jsonBody->user_list) && is_array($req->jsonBody->user_list)) {
            $target = strtolower(urldecode($this->term));
            $ui = null;
            $first = null;

            foreach ($req->jsonBody->user_list as $entry) {
                if (!isset($entry->user_info)) continue;
                if ($first === null) $first = $entry->user_info;
                $uid = strtolower($entry->user_info->unique_id ?? $entry->user_info->uniqueId ?? '');
                if ($uid === $target) { $ui = $entry->user_info; break; }
            }
            // Coincidencia exacta; si no hay pero solo vino 1 resultado, lo damos por bueno.
            if ($ui === null && count($req->jsonBody->user_list) === 1) $ui = $first;

            if ($ui !== null) {
                $info->setDetail($this->mapDetail($ui));
                $info->setStats($this->mapStats($ui));
            }
        }
        $this->info = $info;

        return $this;
    }

    /** Mapea user_info (search/full, snake_case) al esquema camelCase que esperan plantilla y feed(). */
    private function mapDetail(object $ui): object {
        $d = new \stdClass;

        // Identidad / lo que necesita feed()
        $d->id       = (string) ($ui->uid ?? $ui->id ?? '');
        $d->secUid   = $ui->sec_uid ?? $ui->secUid ?? '';
        $d->uniqueId = $ui->unique_id ?? $ui->uniqueId ?? '';
        $d->nickname = $ui->nickname ?? $d->uniqueId;

        // Avatar: en search viene como objeto {url_list:[...]}; la plantilla espera STRING.
        $d->avatarLarger = $this->pickAvatar($ui);

        // La plantilla lee verified y privateAccount SIN isset → tienen que existir SIEMPRE.
        $d->signature      = $ui->signature ?? '';
        $d->verified       = !empty($ui->custom_verify) || !empty($ui->enterprise_verify_reason) || !empty($ui->verified);
        $d->privateAccount = (bool) ($ui->secret ?? $ui->private_account ?? $ui->privateAccount ?? false);

        // commerceUserInfo: la plantilla accede a ->commerceUser SIN isset del padre → default obligatorio o peta.
        $d->commerceUserInfo = (object) [
            "commerceUser" => (bool) ($ui->commerce_user_info->commerce_user ?? false),
            "category"     => $ui->commerce_user_info->category ?? ''
        ];

        // Opcionales (la plantilla los protege con isset)
        $bio = $ui->bio_url ?? $ui->bio_secure_url ?? ($ui->bio_link->link ?? '');
        if ($bio !== '') $d->bioLink = (object) ["link" => $bio];
        if (isset($ui->region)) $d->region = $ui->region;

        return $d;
    }

    /** stats camelCase. La plantilla lee los 4 counts SIN isset → default 0. */
    private function mapStats(object $ui): object {
        return (object) [
            "followerCount"  => (int) ($ui->follower_count  ?? 0),
            "followingCount" => (int) ($ui->following_count ?? 0),
            "heartCount"     => (int) ($ui->total_favorited ?? $ui->heart_count ?? 0),
            "videoCount"     => (int) ($ui->aweme_count     ?? $ui->video_count ?? 0)
        ];
    }

    /** URL de avatar usable. Soporta objeto {url_list:[...]} (search) o string plano (web). */
    private function pickAvatar(object $ui): string {
        foreach (["avatar_larger", "avatar_medium", "avatar_thumb", "avatarLarger", "avatarMedium", "avatarThumb"] as $k) {
            if (!isset($ui->$k)) continue;
            $v = $ui->$k;
            if (is_string($v) && $v !== '') return $v;
            if (is_object($v) && isset($v->url_list[0])) return $v->url_list[0];
        }
        return '';
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
