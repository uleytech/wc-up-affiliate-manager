<?php

require_once __DIR__ . '/UpAffiliateManagerGitLabUpdater.php';

function initUpAffiliateManager()
{
    new UpAffiliateManagerGitLabUpdater(
        __FILE__,
        PMA_USERNAME,
        PMA_PROJECT,
        PMA_TOKEN
    );
}

add_action('admin_init', 'initUpAffiliateManager');