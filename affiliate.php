<?php

function upAffiliateManagerInitAffCookie()
{
    $refId = $_GET['aid'] ?? null;
    $options = get_option(UP_AFFILIATE_MANAGER_OPTIONS);
    $expiredAt = esc_attr($options['expired_at'] ?? 30);
    $affId = esc_attr($options['aff_id'] ?? null);
    if (!isset($_COOKIE['aid'])) {
        $cid = $refId ?? $affId;
        setcookie('aid', $cid, time() + 60 * 60 * 24 * $expiredAt, COOKIEPATH, COOKIE_DOMAIN, false);
    }
}

add_action('init', 'upAffiliateManagerInitAffCookie');

function upAffiliateManagerAddPapTracker()
{
    wp_enqueue_script('pap_x2s6df8d', '//transferto.zx.megadevs.xyz/scripts/trackjs.js', [], false, true);
    wp_enqueue_script("jquery");
    wp_enqueue_script('tracker', plugins_url('/assets/js/tracker.js', __FILE__), ['jquery', 'pap_x2s6df8d'], false, true);
}

add_action('wp_enqueue_scripts', 'upAffiliateManagerAddPapTracker');
