<?php
namespace TikScraper\Helpers;

class Request {
    /**
     * Construye la query para la API de TikTok.
     * Fingerprint fijado a Mac/Safari para cuadrar con el navigator de carcabot.
     */
    static public function buildQuery(array $query, string $verifyFp, string $device_id): string {
        $query_merged = array_merge($query, [
            "WebIdLastTime" => time(),
            "aid" => 1988,
            "app_language" => "en",
            "app_name" => "tiktok_web",
            "browser_language" => "en-US",
            "browser_name" => "Mozilla",
            "browser_online" => "true",
            "browser_platform" => "MacIntel",
            "browser_version" => "5.0",
            "channel" => "tiktok_web",
            "cookie_enabled" => "true",
            "device_id" => $device_id,
            "device_platform" => "web_pc",
            "focus_state" => "true",
            "history_len" => rand(1, 10),
            "is_fullscreen" => "false",
            "is_page_visible" => "true",
            "language" => "en",
            "os" => "mac",
            "priority_region" => "US",
            "region" => "US",
            "screen_height" => 1080,
            "screen_width" => 1920,
            "tz_name" => "America/New_York",
            "webcast_language" => "en",
            "verifyFp" => $verifyFp
        ]);

        return '?' . http_build_query($query_merged);
    }
}