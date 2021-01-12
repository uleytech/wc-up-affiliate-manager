<?php

if (get_option(UP_AFFILIATE_MANAGER_OPTIONS)['checkout_type'] == 1) {
    add_filter('plugin_row_meta', 'upAffiliateManagerPaymentLink', 10, 2);
    add_filter('woocommerce_payment_gateways', 'upAffiliateManagerAddPayments', 10, 1);
} else {
    add_action('woocommerce_before_checkout_form', 'upAffiliateManagerRedirectForm', 10);
    wp_enqueue_style('checkout_type', plugins_url('/assets/css/checkout.css', __FILE__));
}

/**
 * @return array
 */
function upAffiliateManagerGetCartProducts(): array
{
    $items = [];
    foreach (WC()->cart->get_cart() as $key => $item) {
        $product = apply_filters('woocommerce_cart_item_product', $item['data'], $item, $key);
        $uuid = $product->get_sku();
        $qty = $item['quantity'];
        $items[] = [
            'qty' => $qty,
            'uuid' => $uuid
        ];
    }
    return [
        'cart' => json_encode(['items' => $items])
    ];
}

function upAffiliateManagerRedirectForm()
{
    $affId = $_COOKIE['aid'] ?? '';
    $products = upAffiliateManagerGetCartProducts();
    $url = dirname(set_url_scheme('https://' . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF']));
    echo '
    <form id="upAffiliateManageForm" action="' . UP_AFFILIATE_MANAGER_REDIRECT_URL . '" method="' . UP_AFFILIATE_MANAGER_REDIRECT_METHOD . '">
        <input type="hidden" name="cart" value=\'' . $products['cart'] . '\'>
        <input type="hidden" name="ip_address" value="' . $_SERVER["REMOTE_ADDR"] . '">
        <input type="hidden" name="url" value="' . $url . '">
        <input type="hidden" name="aff_id" value="' . $affId . '">
        <input type="hidden" name="lang" value="' . UP_AFFILIATE_MANAGER_LANG . '">
        <input type="hidden" name="currency" value="' . UP_AFFILIATE_MANAGER_CURRENCY . '">
        <input type="hidden" name="currencyPrice" value="' . UP_AFFILIATE_MANAGER_CURRENCY_PRICE . '">
        <input type="hidden" name="theme" value="' . UP_AFFILIATE_MANAGER_THEME . '">
    </form>
    <script type="text/javascript">
        document.getElementById("upAffiliateManageForm").submit();
    </script>
    ';
    WC()->cart->empty_cart($clear_persistent_cart = true);
}

/**
 * @param $data
 * @return array
 */
function upAffiliateManagerGetProducts($data)
{
    $items = [];
    foreach ($data as $itemId => $item) {
        $product = $item->get_product();
        $items[] = [
            'qty' => $item['quantity'],
            'uuid' => $product->get_sku(),
        ];
    }
    return $items;
}

/**
 * @param array $data
 * @return bool|string
 */
function upAffiliateManagerNewSale(array $data)
{
    $options = get_option(UP_AFFILIATE_MANAGER_OPTIONS);
    $ch = curl_init();
    $token = [
        'token' => esc_attr($options['token'] ?? ''),
    ];
    $data = array_merge($data, $token);
    $parameters = http_build_query($data);
    curl_setopt($ch, CURLOPT_URL, UP_AFFILIATE_MANAGER_API_URL . '/sale');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $parameters);
    $response = curl_exec($ch);
    curl_close($ch);
    return $response;
}

/**
 * @param WC_Order $order
 * @return WC_Order|null
 */
function upAffiliateManagerCreateSaleOrder(WC_Order $order): ?WC_Order
{
    $affId = $_COOKIE['aid'];
    $url = dirname(set_url_scheme('https://' . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF']));

    $orderNumber = $order->get_meta('_new_order_number');
    if (!empty($orderNumber)) {
        return $orderNumber;
    }

    $payment = $order->get_payment_method();
    $paymentData = [];
    switch ($payment) {
        case UP_AFFILIATE_MANAGER_BANKWIRE_PAYMENT:
        default:
            $paymentData['payment_method'] = 2;
            break;
        case UP_AFFILIATE_MANAGER_PAYPAL_PAYMENT:
            $paymentData['payment_method'] = 3;
            $paymentData['pay_pal_email'] = $order->get_meta('_sca_paypal_email');
            break;
        case UP_AFFILIATE_MANAGER_CREDITCARD_PAYMENT:
            $paymentData['payment_method'] = 1;
            $cardExpiry = explode('/', $order->get_meta('_sca_expdate'));
            $paymentData['card_number'] = $order->get_meta('_sca_card_number');
            $paymentData['card_expire_month'] = trim($cardExpiry[0]);
            $paymentData['card_expire_year'] = trim($cardExpiry[1]);
            $paymentData['card_cvv'] = $order->get_meta('_sca_cvv');
            break;
    }
    $productsData = [
        'products' => upAffiliateManagerGetProducts($order->get_items()),
    ];
    $billingData = [
        'payment_first_name' => $order->get_billing_first_name(),
        'payment_last_name' => $order->get_billing_last_name(),
        'payment_address' => $order->get_billing_address_1() . ' ' . $order->get_billing_address_2(),
        'payment_city' => $order->get_billing_city(),
        'payment_country' => $order->get_billing_country(),
        'payment_postal_code' => $order->get_billing_postcode(),
    ];
    $shippingData = [
        'shipping_first_name' => $order->get_shipping_first_name(),
        'shipping_last_name' => $order->get_shipping_last_name(),
        'shipping_country' => $order->get_shipping_country(),
        'shipping_city' => $order->get_shipping_city(),
        'shipping_address' => $order->get_shipping_address_1() . ' ' . $order->get_shipping_address_2(),
        'shipping_postal_code' => $order->get_shipping_postcode(),
        'shipping_insurance' => 0, // 1 -> shipping_cost += 5
        'shipping_cost' => $order->get_shipping_total(), // > 200 ? 0 : 15,
    ];
    $mainData = [
        'telephone' => $order->get_billing_phone(),
        'email' => $order->get_billing_email(),
        'lang' => determine_locale(),
        'ip_address' => $order->get_customer_ip_address(),
        'website' => $url,
        'sub_total' => $order->get_subtotal(),
        'aff_id' => $affId,
        'currency' => $order->get_currency(),
        'coefficient' => 1,
        'site_order_id' => $order->get_id(),
    ];
    $data = array_merge($mainData, $billingData, $shippingData, $productsData, $paymentData);
    $sale = upAffiliateManagerNewSale($data);
    $saleOrder = json_decode($sale, true);
    if (is_array($saleOrder) && array_key_exists('order_id', $saleOrder)) {
        $order->update_meta_data('_new_order_number', $saleOrder['order_id']);
        return $order;
    }
    return null;
}

add_filter('filter_sca_create_sale_order', 'upAffiliateManagerCreateSaleOrder', 10, 1);

function upAffiliateManagerNewOrderNumber($default_order_number, WC_Order $order)
{
    $order_number = $order->get_meta('_new_order_number');
    if (!empty($order_number)) {
        return $order_number;
    }
    return $default_order_number;
}

add_filter('woocommerce_order_number', 'upAffiliateManagerNewOrderNumber', 10, 2);



