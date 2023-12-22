<?php
/*
 * Plugin Name: Injective Pay - Woocommerce Payment Gateway
 * Description: Receive Injective token payments on your store.
 * Author: therealbryanho
 * Author URI: https://github.com/therealbryanho
 * Version: 1.0.0
 */

/*
 * This action hook registers our PHP class as a WooCommerce payment gateway
 */
add_filter('woocommerce_payment_gateways', 'injectivepay_add_gateway_class');
function injectivepay_add_gateway_class($gateways)
{
    $gateways[] = 'WC_Injectivepay_Gateway'; // your class name is here
    return $gateways;
}

/*
 * The class itself, please note that it is inside plugins_loaded action hook
 */
add_action('plugins_loaded', 'injectivepay_init_gateway_class');
function injectivepay_init_gateway_class()
{
    class WC_Injectivepay_Gateway extends WC_Payment_Gateway
    {

        /**
         * Class constructor, more about it in Step 3
         */
        public function __construct()
        {

            $this->id = 'injectivepay'; // payment gateway plugin ID
            $this->icon = ''; // URL of the icon that will be displayed on the checkout page near your gateway name
            $this->has_fields = true; // in case you need a custom credit card form
            $this->method_title = 'InjectivePay Gateway';
            $this->method_description = 'Receive Injective token payments on your store. Note that this only works for inEVM devnet now.'; // will be displayed on the options page

            // gateways can support subscriptions, refunds, saved payment methods,
            // but in this tutorial, we begin with simple payments
            $this->supports = array(
                'products'
            );

            // Method with all the options fields
            $this->init_form_fields();

            // Load the settings.
            $this->init_settings();
            $this->title = $this->get_option('title');
            $this->description = $this->get_option('description');
            $this->enabled = $this->get_option('enabled');
            $this->merchant_name = $this->get_option('merchant_name');
            $this->merchant_wallet = $this->get_option('merchant_wallet');

            // This action hook saves the settings
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

            // We need custom JavaScript to obtain a token
            add_action('wp_enqueue_scripts', array($this, 'payment_scripts'));
        }


        /**
         * Plugin options, we deal with it in Step 3 too
         */
        public function init_form_fields()
        {

            $this->form_fields = array(
                'enabled' => array(
                    'title' => 'Enable/Disable',
                    'label' => 'Enable InjectivePay Gateway',
                    'type' => 'checkbox',
                    'description' => '',
                    'default' => 'no'
                ),
                'title' => array(
                    'title' => 'Title',
                    'type' => 'text',
                    'description' => 'This controls the title which the user sees during checkout.',
                    'default' => 'Injective Pay',
                    'desc_tip' => true,
                ),
                'description' => array(
                    'title' => 'Description',
                    'type' => 'textarea',
                    'description' => 'This controls the description which the user sees during checkout.',
                    'default' => 'Pay with INJ tokens using Metamask.',
                ),
                'merchant_name' => array(
                    'title' => 'Your Shop Name',
                    'type' => 'text'
                ),
                'merchant_wallet' => array(
                    'title' => 'Your Injective EVM Wallet',
                    'type' => 'password'
                )
            );
        }

        public function payment_scripts() {
            // Add your script enqueue logic here if needed
        }    

        /*
         * We're processing the payments here, everything about it is in Step 5
         */
        public function process_payment($order_id)
        {
            $merchanturl = home_url(add_query_arg(array(), $wp->request));
            $order = wc_get_order($order_id);

            // Get the total amount
            $total_amount = $order->get_total();

            $merchant_name = $this->merchant_name;
            $merchant_wallet = $this->merchant_wallet;

            $payment_gateway_url = 'https://injectivepay.netlify.app/';
            // Make sure to include '&' to separate parameters in the URL
            $redirect_url = $payment_gateway_url . '?valueToSend=' . $total_amount . '&toAddress=' . $merchant_wallet . '&order_id=' . $order_id . '&memo=' . $merchant_name . '&merchanturl=' . $merchanturl;

            return array(
                'result' => 'success',
                'redirect' => $redirect_url,
            );
        }

    }
}

// Move the callback function outside the class definition
add_action('template_redirect', 'handle_payment_gateway_callback');

function handle_payment_gateway_callback()
{
    // Check if this is a callback request
    if (isset($_GET['payment_gateway_callback']) && $_GET['payment_gateway_callback'] === '1') {
        // Process the payment response
        $status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';

        if ($status === 'success') {
            // Payment was successful, update the order status
            $order_id = isset($_GET['order_id']) ? absint($_GET['order_id']) : 0;
            $order = wc_get_order($order_id);

            if ($order) {
                $order->payment_complete();
            }
        } else {
            // Payment failed, handle accordingly
        }

        // Redirect to the order received/thank you page
        $redirect_url = wc_get_checkout_url();
        wp_redirect($redirect_url);
        exit;
    }
}
