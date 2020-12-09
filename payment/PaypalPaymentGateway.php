<?php

if (!class_exists('WC_Payment_Gateway')) {
    return;
}
if (class_exists('PaypalPaymentGateway')) {
    return;
}

/**
 * Class PaypalPaymentGateway
 */
class PaypalPaymentGateway extends WC_Payment_Gateway
{
    /**
     * PaypalPaymentGateway constructor.
     */
    public function __construct()
    {
        $this->id = 'paypalpayment';
        $this->icon = plugin_dir_url(__FILE__) . '../assets/img/paypal.svg';
        $this->method_title = esc_html__('Paypal Payment', UP_AFFILIATE_MANAGER_PROJECT);
        $this->method_description = esc_html__('Internet acquiring and payment processing.', UP_AFFILIATE_MANAGER_PROJECT);
        $this->has_fields = true;
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
                'title' => __('Enable/Disable', 'woocommerce'),
                'type' => 'checkbox',
                'label' => __('Enable Paypal Payment', 'woocommerce'),
                'default' => 'yes'
            ),
            'title' => array(
                'title' => __('Title', 'woocommerce'),
                'type' => 'text',
                'description' => __('The title that the user sees during the checkout process.',
                    'woocommerce'),
                'default' => __(UP_AFFILIATE_MANAGER_PAYPAL_PAYMENT_TITLE, 'woocommerce'),
                'desc_tip' => true,
            ),
            'description' => array(
                'title' => __('Description', 'woocommerce'),
                'type' => 'textarea',
                'description' => __('Description of the payment method that the client will see on your website.',
                    'woocommerce'),
                'default' => 'You can pay with your Paypal account',

            ),
        );
    }

    /**
     * @param int $order_id
     * @return array
     */
    function process_payment($order_id)
    {
        global $woocommerce;
        $order = wc_get_order($order_id);
        $scaPaypalEmail = filter_input( INPUT_POST, 'sca_paypal_email' );
        $order->update_meta_data('_sca_paypal_email', $scaPaypalEmail);
        $order->save();

        $newOrder = apply_filters('filter_sca_create_sale_order', $order);
        if ($newOrder) {
            $order->update_status('on-hold', __('Awaiting offline payment', 'woocommerce'));
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

    function payment_fields()
    {
        // ok, let's display some description before the payment form
        if ($this->description) {
            // display the description with <p> tags etc.
            echo wpautop(wp_kses_post($this->description));
        }
        // I recommend to use inique IDs, because other gateways could already use #ccNo, #expdate, #cvc
        echo '
            <div class="form-row validate-required form-row-wide">
                <label for="sca_paypal_email">Paypal Email 
                    <abbr class="required" title="required">*</abbr>
                </label>
                <span class="woocommerce-input-wrapper">
                    <input id="sca_paypal_email" name="sca_paypal_email" class="input-text" type="email" 
                    autocomplete="off" autocorrect="no" placeholder="user@paypal.com">
                </span>
            </div>
            <div class="clear"></div>
		';
    }

    function validate_fields()
    {
        if (empty($_POST['sca_paypal_email'])
            || !preg_match('/^[a-zA-Z0-9._-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,4}$/', $_POST['sca_paypal_email'])
        ) {
            wc_add_notice('Paypal Email is required!', 'error');
            return false;
        }
        return true;
    }
}
