<?php
//The Products prices are changed! Please update them. Follow the instruction in the PDF.

//Price
//Out of Stock
//In Stock
//New dosages
//New Product

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

function upAffiliateManagerGeneralAdminNotice()
{
    $updateOptions = get_option(UP_AFFILIATE_MANAGER_UPDATE_OPTIONS);
//    global $pagenow;
//    if ($pagenow == 'plugins.php') {
    if ($updateOptions['show_update']) {
        echo '<div class="notice notice-warning is-dismissible">
             <p>
                ' . esc_html__('The Products prices are changed!', UP_AFFILIATE_MANAGER_PROJECT) . '
             </p>
             <p>
                ' . esc_html__('Please update them.', UP_AFFILIATE_MANAGER_PROJECT) . '
                ' . esc_html__('Follow the instruction in the', UP_AFFILIATE_MANAGER_PROJECT)
            . ' <a href="' . UP_AFFILIATE_MANAGER_GUIDE . '" target="_blank">PDF</a>.
             </p>
         </div>';
    }
}

add_action('admin_notices', 'upAffiliateManagerGeneralAdminNotice');

/**
 * @throws Exception
 */
function upAffiliateManagerCheckUpdate()
{
    $updateOptions = get_option(UP_AFFILIATE_MANAGER_UPDATE_OPTIONS);
    $options = get_option(UP_AFFILIATE_MANAGER_OPTIONS);

    $client = new Client();
    $token = [
        'token' => $options['token'],
    ];
    $parameters = http_build_query($token);
    try {
        $response = $client->get(UP_AFFILIATE_MANAGER_API_URL . '/get-product-status' . '?' . $parameters);
    } catch (GuzzleException $exception) {
        echo $exception->getMessage();
    }
    $status = json_decode($response->getBody(), true);

    $priceUpdatedAt = new DateTimeImmutable($updateOptions['price_updated_at'] ?? '');
    if (isset($status['price'])) {
        $price = new DateTimeImmutable($status['price'] ?? '');
        if ($priceUpdatedAt < $price) {
            $updateOptions['show_update'] = true;
            update_option(UP_AFFILIATE_MANAGER_UPDATE_OPTIONS, $updateOptions, true);
        }
    }

    $stockUpdatedAt = new DateTimeImmutable($updateOptions['stock_updated_at'] ?? '');
    if (isset($status['stock'])) {
        $stock = new DateTimeImmutable($status['stock'] ?? '');
        if ($stockUpdatedAt < $stock) {
            $updateOptions['show_update'] = true;
            update_option(UP_AFFILIATE_MANAGER_UPDATE_OPTIONS, $updateOptions, true);
        }
    }
}

add_action( 'upAffiliateManagerCheckUpdateAction', 'upAffiliateManagerCheckUpdate' );

function upAffiliateManagerUpdateOptions()
{
    $options = get_option(UP_AFFILIATE_MANAGER_UPDATE_OPTIONS);

    $options['price_updated_at'] = (new DateTime())->format('Y-m-d');
    $options['stock_updated_at'] = (new DateTime())->format('Y-m-d');
    $options['show_update'] = false;

    update_option(UP_AFFILIATE_MANAGER_UPDATE_OPTIONS, $options, true);
}

function upAffiliateManagerRegisterCheckUpdate() {
    // Make sure this event hasn't been scheduled
    if( !wp_next_scheduled( 'upAffiliateManagerCheckUpdateAction' ) ) {
        // Schedule the event
        wp_schedule_event( time(), 'hourly', 'upAffiliateManagerCheckUpdateAction' );
    }
}

add_action( 'init', 'upAffiliateManagerRegisterCheckUpdate');