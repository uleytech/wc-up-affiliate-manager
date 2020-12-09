<?php

if (!class_exists('WC_Payment_Gateway')) {
    return;
}
if (class_exists('BankWirePaymentGateway')) {
    return;
}

/**
 * Class BankWirePaymentGateway
 */
class BankWirePaymentGateway extends WC_Payment_Gateway
{
    /**
     * BankWirePaymentGateway constructor.
     */
    public function __construct()
    {
        $this->id = UP_AFFILIATE_MANAGER_BANKWIRE_PAYMENT;
        $this->icon = plugin_dir_url(__FILE__) . '../assets/img/bankwire.svg';
        $this->method_title = esc_html__('BankWire Payment', UP_AFFILIATE_MANAGER_PROJECT);
        $this->method_description = esc_html__('Internet acquiring and payment processing.', UP_AFFILIATE_MANAGER_PROJECT);
        $this->has_fields = false;
        $this->supports = [
            'products'
        ];
        $this->init_form_fields();
        $this->init_settings();
        $this->enabled = $this->get_option('enabled');
        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        // Save options
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [
            $this, 'process_admin_options'
        ]);
    }

    /**
     * Initialise Gateway Settings Form Fields
     */
    function init_form_fields()
    {
        $this->form_fields = array(
            'enabled' => array(
                'title' => esc_attr__('Enable/Disable', 'woocommerce'),
                'type' => 'checkbox',
                'label' => __('Enable BankWire Payment', UP_AFFILIATE_MANAGER_PROJECT),
                'default' => 'yes'
            ),
            'title' => array(
                'title' => esc_attr__('Title', 'woocommerce'),
                'type' => 'text',
                'description' => esc_attr__(
                    'The title that the user sees during the checkout process.',
                    UP_AFFILIATE_MANAGER_PROJECT
                ),
                'default' => esc_attr__(
                    UP_AFFILIATE_MANAGER_BANKWIRE_PAYMENT_TITLE,
                    UP_AFFILIATE_MANAGER_PROJECT
                ),
                'desc_tip' => true,
            ),
            'description' => array(
                'title' => __('Description', 'woocommerce'),
                'type' => 'textarea',
                'description' => esc_attr__(
                    'Description of the payment method that the client will see on your website.',
                    UP_AFFILIATE_MANAGER_PROJECT
                ),
                'default' => esc_html__(
                    'You can pay with your bank account',
                    UP_AFFILIATE_MANAGER_PROJECT
                ),
            ),
        );
    }

    /**
     * @param int $order_id
     * @return array
     */
    function process_payment($order_id): array
    {
        global $woocommerce;
        $order = wc_get_order($order_id);
        $newOrder = apply_filters('filter_sca_create_sale_order', $order);
        if ($newOrder) {
            $newOrder->update_status('on-hold', __('Awaiting offline payment', 'woocommerce'));
        } else {
            $order->update_status('failed', __('Failed payment', 'woocommerce'));
        }

        $woocommerce->cart->empty_cart();

        // Return thankyou redirect
        return array(
            'result' => 'success',
            'redirect' => $this->get_return_url($newOrder ?? $order)
        );
    }
}
