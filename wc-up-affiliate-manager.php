<?php
/**
 * Plugin Name: WooCommerce UP Affiliate Manager
 * Plugin URI: https://gitlab.com/wp-dmd/wc-up-affiliate-manager
 * Description: Provides Affiliate functionality for WooCommerce.
 * Version: 1.0.5
 * Author: Oleksandr Krokhin
 * Author URI: https://www.krohin.com
 * Requires at least: 5.5
 * Requires PHP: 7.2
 * Text Domain: wc-up-affiliate-manager
 * Domain Path: /languages/
 * License: MIT
 */

defined('ABSPATH') || exit;

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/include.php';
require_once __DIR__ . '/options.php';
require_once __DIR__ . '/product.php';
require_once __DIR__ . '/affiliate.php';
require_once __DIR__ . '/payment/index.php';
require_once __DIR__ . '/checkout.php';
require_once __DIR__ . '/repository/index.php';
