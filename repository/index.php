<?php

require_once __DIR__ . '/UpAffiliateManagerGitLabUpdater.php';

function initUpAffiliateManager()
{
    new UpAffiliateManagerGitLabUpdater(
        __FILE__,
        UP_AFFILIATE_MANAGER_USERNAME,
        UP_AFFILIATE_MANAGER_PROJECT,
        UP_AFFILIATE_MANAGER_TOKEN
    );
}

add_action('admin_init', 'initUpAffiliateManager');