<?php

function upAffiliateManagerSettingsLink($links)
{
    $url = esc_url(add_query_arg(
        'page',
        UP_AFFILIATE_MANAGER_PROJECT,
        get_admin_url() . 'admin.php'
    ));
    $link[] = "<a href='$url'>" . esc_html('Settings') . '</a>';

    return array_merge($link, $links);
}
add_filter(
    'plugin_action_links_' . plugin_basename(__DIR__) . '/' . plugin_basename(__DIR__ . '.php'),
    'upAffiliateManagerSettingsLink'
);

function upAffiliateManagerPaymentLink($links, $file)
{
    $base = plugin_basename(__DIR__) . '/' . plugin_basename(__DIR__ . '.php');
    if ($file == $base) {
        $url = esc_url(get_admin_url() . 'admin.php?page=wc-settings&tab=checkout&section=' . UP_AFFILIATE_MANAGER_BANKWIRE_PAYMENT);
        $links[] = "<a href='$url'>" . __('BankWire') . '</a>';
        $url = esc_url(get_admin_url() . 'admin.php?page=wc-settings&tab=checkout&section=' . UP_AFFILIATE_MANAGER_PAYPAL_PAYMENT);
        $links[] = "<a href='$url'>" . __('Paypal') . '</a>';
        $url = esc_url(get_admin_url() . 'admin.php?page=wc-settings&tab=checkout&section=' . UP_AFFILIATE_MANAGER_CREDITCARD_PAYMENT);
        $links[] = "<a href='$url'>" . __('CreditCard') . '</a>';
    }
    return $links;
}

function UpAffiliateManagerRegister()
{
    add_menu_page(
        esc_html__(UP_AFFILIATE_MANAGER_TITLE, UP_AFFILIATE_MANAGER_PROJECT),
        esc_html__(UP_AFFILIATE_MANAGER_MENU, UP_AFFILIATE_MANAGER_PROJECT),
        'manage_options',
        UP_AFFILIATE_MANAGER_PROJECT,
        'UpAffiliateManagerSettingsRender',
        'dashicons-money-alt',
        26
    );
}

add_action('admin_menu', 'UpAffiliateManagerRegister');

function UpAffiliateManagerSettingsRender()
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $response = upAffiliateManagerAction();
        if ($response) {
            show_message('
                <div class="notice notice-success is-dismissible">
                    <p>' . $response . '</p>
                </div>'
            );
        } else {
            show_message('
                <div class="notice notice-error is-dismissible">
                    <p>' . esc_html__('Error while processing.', UP_AFFILIATE_MANAGER_PROJECT) . '</p>
                </div>'
            );
        }
    } else {
        settings_errors();
    }

    echo '<h2>' . esc_html__(UP_AFFILIATE_MANAGER_TITLE . ' Settings', UP_AFFILIATE_MANAGER_PROJECT) . '</h2>';
    echo '<form action="options.php" method="post">';
    settings_fields(UP_AFFILIATE_MANAGER_OPTIONS);
    do_settings_sections(UP_AFFILIATE_MANAGER_PAGE);
    submit_button(esc_html('Save'), 'primary', 'submit', false);
    echo '</form>';
    echo '<h2>' . esc_html__('Import / Update Products', UP_AFFILIATE_MANAGER_PROJECT) . '</h2>';
    echo '<form action="admin.php?page=' . UP_AFFILIATE_MANAGER_PROJECT . '" method="post">';
    submit_button(esc_html('Import'), 'secondary', 'import', false);
    echo '<h2>' . esc_html__('Delete objects', UP_AFFILIATE_MANAGER_PROJECT) . '</h2>';
    submit_button(esc_html__('Delete Categories', UP_AFFILIATE_MANAGER_PROJECT), 'secondary', 'delete_categories', false);
    echo '&nbsp;';
    submit_button(esc_html__('Delete Attributes', UP_AFFILIATE_MANAGER_PROJECT), 'secondary', 'delete_attributes', false);
    echo '</form>';
}

function upAffiliateManagerSettingsRegister()
{
    register_setting(
        UP_AFFILIATE_MANAGER_OPTIONS,
        UP_AFFILIATE_MANAGER_OPTIONS,
        'upAffiliateManagerSettingsValidate'
    );
    // Aff
    add_settings_section(
        'aff_settings',
        esc_attr__('Affiliate Settings', UP_AFFILIATE_MANAGER_PROJECT),
        'upAffiliateManagerAffSectionText',
        UP_AFFILIATE_MANAGER_PAGE
    );

    add_settings_field(
        'wc_up_affiliate_manager_setting_aff_id',
        esc_attr__('Affiliate ID', UP_AFFILIATE_MANAGER_PROJECT),
        'upAffiliateManagerSettingsRegisterAffId',
        UP_AFFILIATE_MANAGER_PAGE,
        'aff_settings'
    );

    add_settings_field(
        'wc_up_affiliate_manager_setting_expired_at',
        esc_attr__('Expired', UP_AFFILIATE_MANAGER_PROJECT),
        'upAffiliateManagerSettingsRegisterExpiredAt',
        UP_AFFILIATE_MANAGER_PAGE,
        'aff_settings'
    );

    add_settings_field(
        'wc_up_affiliate_manager_setting_ref_id',
        esc_attr__('Referrer ID', UP_AFFILIATE_MANAGER_PROJECT),
        'upAffiliateManagerSettingsRegisterRefId',
        UP_AFFILIATE_MANAGER_PAGE,
        'aff_settings'
    );
    // Checkout
    add_settings_section(
        'checkout_settings',
        esc_attr__('Checkout Settings', UP_AFFILIATE_MANAGER_PROJECT),
        'upAffiliateManagerCheckoutSectionText',
        UP_AFFILIATE_MANAGER_PAGE
    );
    add_settings_field(
        'wc_up_affiliate_manager_setting_checkout_type',
        esc_attr__('Checkout Type', UP_AFFILIATE_MANAGER_PROJECT),
        'upAffiliateManagerSettingsRegisterCheckoutType',
        UP_AFFILIATE_MANAGER_PAGE,
        'checkout_settings'
    );

    // API
    add_settings_section(
        'api_settings',
        esc_attr__('API Settings', UP_AFFILIATE_MANAGER_PROJECT),
        'upAffiliateManagerApiSectionText',
        UP_AFFILIATE_MANAGER_PAGE
    );
    add_settings_field(
        'wc_up_affiliate_manager_setting_token',
        esc_attr__('Token', UP_AFFILIATE_MANAGER_PROJECT),
        'upAffiliateManagerSettingsRegisterToken',
        UP_AFFILIATE_MANAGER_PAGE,
        'api_settings'
    );
    add_settings_field(
        'wc_up_affiliate_manager_setting_language',
        esc_attr__('Language', UP_AFFILIATE_MANAGER_PROJECT),
        'upAffiliateManagerSettingsRegisterLanguage',
        UP_AFFILIATE_MANAGER_PAGE,
        'api_settings'
    );
    add_settings_field(
        'wc_up_affiliate_manager_setting_include_groups',
        esc_attr__('Include SKU', UP_AFFILIATE_MANAGER_PROJECT),
        'upAffiliateManagerSettingsRegisterIncludeGroups',
        UP_AFFILIATE_MANAGER_PAGE,
        'api_settings'
    );
    add_settings_field(
        'wc_up_affiliate_manager_setting_exclude_groups',
        esc_attr__('Exclude SKU', UP_AFFILIATE_MANAGER_PROJECT),
        'upAffiliateManagerSettingsRegisterExcludeGroups',
        UP_AFFILIATE_MANAGER_PAGE,
        'api_settings'
    );
}

add_action('admin_init', 'upAffiliateManagerSettingsRegister');

function upAffiliateManagerSettingsValidate($input)
{
    $newinput['aff_id'] = trim($input['aff_id']);
    if (!preg_match('/^[0-9]+$/i', $newinput['aff_id'])) {
        $newinput['aff_id'] = '';
    }
    $newinput['expired_at'] = trim($input['expired_at']);
    if (!preg_match('/^[0-9]+$/i', $newinput['expired_at'])) {
        $newinput['expired_at'] = '';
    }
    $newinput['token'] = trim($input['token']);
    if (!preg_match('/^[a-z0-9]{40}$/i', $newinput['token'])) {
        $newinput['token'] = '';
    }
    $newinput['include_groups'] = trim($input['include_groups']);
    if (!preg_match('/^[0-9\,\ ]+$/i', $newinput['include_groups'])) {
        $newinput['include_groups'] = '';
    }
    $newinput['exclude_groups'] = trim($input['exclude_groups']);
    if (!preg_match('/^[0-9\,\ ]+$/i', $newinput['exclude_groups'])) {
        $newinput['exclude_groups'] = '';
    }
    $newinput['language'] = trim($input['language']);
    if (!preg_match('/^[a-z]{2}$/i', $newinput['language'])) {
        $newinput['language'] = '';
    }
    $newinput['checkout_type'] = trim($input['checkout_type']);
    if (!preg_match('/^[0-9]{1}$/i', $newinput['checkout_type'])) {
        $newinput['checkout_type'] = '';
    }
    return $newinput;
}


function upAffiliateManagerAffSectionText()
{
    echo '<p>' . __('Here you can set the options for using the Affiliate ID', UP_AFFILIATE_MANAGER_PROJECT) . '</p>';
}

function upAffiliateManagerSettingsRegisterAffId()
{
    $options = get_option(UP_AFFILIATE_MANAGER_OPTIONS);
    echo "<input id='wc_up_affiliate_manager_setting_aff_id' name='wc_up_affiliate_manager_options[aff_id]' type='text' value='" . esc_attr($options['aff_id'] ?? '') . "' />";
}

function upAffiliateManagerSettingsRegisterExpiredAt()
{
    $options = get_option(UP_AFFILIATE_MANAGER_OPTIONS);
    echo "<input id='wc_up_affiliate_manager_setting_expired_at' name='wc_up_affiliate_manager_options[expired_at]' type='number' class='small-text'  value='" . esc_attr($options['expired_at'] ?? '30') . "' />
        <span>" . esc_html__('day(s)', UP_AFFILIATE_MANAGER_PROJECT) . "</span>";
}

function upAffiliateManagerSettingsRegisterRefId()
{
    $options = get_option(UP_AFFILIATE_MANAGER_OPTIONS);
    echo 'https://' . $_SERVER['HTTP_HOST'] . '/?aid=' . esc_attr($options['aff_id'] ?? '?????');
}

function upAffiliateManagerCheckoutSectionText()
{
    echo '<p>' . __('Here you can set the options for using the Checkout', UP_AFFILIATE_MANAGER_PROJECT) . '</p>';
}

function upAffiliateManagerSettingsRegisterCheckoutType()
{
    $options = get_option(UP_AFFILIATE_MANAGER_OPTIONS);
    echo '
        <fieldset>
	        <p>
	            <label>
	            <input name="wc_up_affiliate_manager_options[checkout_type]" type="radio" value="0" ' . ((esc_attr($options['checkout_type'] == '0') || empty($options['checkout_type']) ) ? "checked = 'checked'" : '') . ' > 
	            <b>Redirect</b> - external checkout https://secure-safepay.com
	            </label>
                <br>
		        <label>
		        <input name="wc_up_affiliate_manager_options[checkout_type]" type="radio" value="1"'  . ((esc_attr($options['checkout_type'] == '1')) ? "checked = 'checked'" : '') . '> 
		        <b>API</b> - internal checkout WooCommerce
		        </label>
	        </p>
        </fieldset>
    ';
}

function upAffiliateManagerApiSectionText()
{
    echo '<p>' . esc_html__('Here you can set the options for using the API', UP_AFFILIATE_MANAGER_PROJECT) . '</p>';
}

function upAffiliateManagerSettingsRegisterToken()
{
    $options = get_option(UP_AFFILIATE_MANAGER_OPTIONS);
    echo "<input id = 'wc_up_affiliate_manager_setting_token' name = 'wc_up_affiliate_manager_options[token]' type = 'text' class='regular-text' value = '" . esc_attr($options['token']) . "' />";
}

function upAffiliateManagerSettingsRegisterLanguage()
{
    $options = get_option(UP_AFFILIATE_MANAGER_OPTIONS);
    echo "
        <select id = 'wc_up_affiliate_manager_setting_language' name = 'wc_up_affiliate_manager_options[language]' >
            <option value = 'en' " . ((esc_attr($options['language'] == 'en')) ? "selected = 'selected'" : '') . " > en</option >
            <option value = 'de' " . ((esc_attr($options['language'] == 'de')) ? "selected = 'selected'" : '') . " > de</option >
            <option value = 'fr' " . ((esc_attr($options['language'] == 'fr')) ? "selected = 'selected'" : '') . " > fr</option >
            <option value = 'es' " . ((esc_attr($options['language'] == 'es')) ? "selected = 'selected'" : '') . " > es</option >
            <option value = 'it' " . ((esc_attr($options['language'] == 'it')) ? "selected = 'selected'" : '') . " > it</option >
            <option value = 'ar' " . ((esc_attr($options['language'] == 'ar')) ? "selected = 'selected'" : '') . " > ar</option >
            <option value = 'cs' " . ((esc_attr($options['language'] == 'cs')) ? "selected = 'selected'" : '') . " > cs</option >
            <option value = 'sv' " . ((esc_attr($options['language'] == 'sv')) ? "selected = 'selected'" : '') . " > sv</option >
        </select >
";
}

function upAffiliateManagerSettingsRegisterIncludeGroups()
{
    $options = get_option(UP_AFFILIATE_MANAGER_OPTIONS);
    echo "<input id = 'wc_up_affiliate_manager_setting_include_groups' name = 'wc_up_affiliate_manager_options[include_groups]' type = 'text' class='regular-text' value = '" . esc_attr($options['include_groups']) . "' />";
    echo "<p class='description' > Comma separated SKU(1, 2, 3) </p > ";
}

function upAffiliateManagerSettingsRegisterExcludeGroups()
{
    $options = get_option(UP_AFFILIATE_MANAGER_OPTIONS);
    echo "<input id = 'wc_up_affiliate_manager_setting_exclude_groups' name = 'wc_up_affiliate_manager_options[exclude_groups]' type = 'text' class='regular-text' value = '" . esc_attr($options['exclude_groups']) . "' />";
    echo "<p class='description' > Comma separated SKU(1, 2, 3) </p > ";
}
