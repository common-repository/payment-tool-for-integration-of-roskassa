<?php
/*
  Plugin Name: Payment tool for Integration of RosKassa
  Plugin URI: https://github.com/syntlex
  Description: The plugin for Integration of RosKassa with WooCommerce
  Tags: Syntlex Dev, WooCommerce, WordPress, Gateways, Payments, Payment, Money, RosKassa, WordPress, Plugin, Module, Store, Payment system, Website, Syntlex

  Version: 0.5
  Author: Syntlex Dev
  Author URI: https://syntlex.info
  Copyright: © 2021 Syntlex Dev.
  License: GNU General Public License v3.0
  License URI: http://www.gnu.org/licenses/gpl-3.0.html

 */

if (!defined('ABSPATH'))
{
    exit;
}

add_action('plugins_loaded', 'woocommerce_roskassa', 0);



function woocommerce_roskassa()
{
    if (!class_exists('WC_Payment_Gateway'))
    {
        return;
    }

    if (class_exists('WC_ROSKASSA'))
    {
        return;
    }

    class WC_ROSKASSA extends WC_Payment_Gateway
    {
        public function __construct()
        {
            global $woocommerce;

            $plugin_dir = plugin_dir_url(__FILE__);

            $this->id = 'roskassa';
            $this->source = 'WP 0.2.2';
            $this->icon = apply_filters('woocommerce_roskassa_icon', $plugin_dir . 'icon.png');
            $this->has_fields = false;
            $this->init_form_fields();
            $this->init_settings();
            $this->title = $this->get_option('title');
            $this->roskassa_url = $this->get_option('roskassa_url');
            $this->roskassa_shop_id = $this->get_option('roskassa_shop_id');
            $this->roskassa_secret_key = $this->get_option('roskassa_secret_key');
            $this->email_error = $this->get_option('email_error');
            $this->ip_filter = $this->get_option('ip_filter');
            $this->log_file = $this->get_option('log_file');
            $this->test_mode = $this->get_option('test_mode');

            $this->method_title = 'Internet acquiring RosKassa';
            $this->method_description = 'RosKassa internet acquiring (payment acceptance), integration with other payment systems.';

            add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));
            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
            add_action('woocommerce_api_wc_' . $this->id, array($this, 'check_ipn_response'));

            if (!$this->is_valid_for_use())
            {
                $this->enabled = false;
            }
        }

        function is_valid_for_use()
        {
            return true;
        }


        public function admin_options()
        {
            ?>
          <h3><?php _e('RosKassa', 'woocommerce'); ?></h3>
          <p><?php _e('Configuring the acceptance of electronic payments through RosKassa.', 'woocommerce'); ?></p>

            <?php if ( $this->is_valid_for_use() ) : ?>
          <table class="form-table">
              <?php $this->generate_settings_html(); ?>
          </table>

        <?php else : ?>
          <div class="inline error">
            <p>
              <strong><?php _e('Gateway disabled', 'woocommerce'); ?></strong>:
                <?php _e('RosKassa does not support your stores currencies.', 'woocommerce' ); ?>
            </p>
          </div>
        <?php
        endif;
        }

        function init_form_fields()
        {
            $this->form_fields = array(
                'enabled' => array(
                    'title' => __('Enable/Disabled', 'woocommerce'),
                    'type' => 'checkbox',
                    'label' => __('Enable', 'woocommerce'),
                    'default' => 'yes'
                ),
                'title' => array(
                    'title' => __('Name', 'woocommerce'),
                    'type' => 'text',
                    'description' => __( 'This is the name that the user sees when choosing a payment method.', 'woocommerce' ),
                    'default' => __('RosKassa', 'woocommerce')
                ),
                'roskassa_url' => array(
                    'title' => __('Merchant URL', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('URL for payment in the RosKassa system', 'woocommerce'),
                    'default' => 'https://pay.roskassa.net/'
                ),
                'roskassa_shop_id' => array(
                    'title' => __('Store Public KEY', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('Public KEY of a store registered in the system "RosKassa".<br/>You can find it out in <a href="https://my.roskassa.net/shop-settings/"> Roskassa account </a>: "Account -> Settings".', 'woocommerce'),
                    'default' => ''
                ),
                'roskassa_secret_key' => array(
                    'title' => __('Secret KEY', 'woocommerce'),
                    'type' => 'password',
                    'description' => __('The secret key for notification of the execution of the payment, <br/> which is used to verify the integrity of the information received <br/> and to uniquely identify the sender. <br/> Must match the secret key specified in <a href="https://my.roskassa.net/shop-settings/">Roskassa account </a>: "Account -> Settings".', 'woocommerce'),
                    'default' => ''
                ),
                'log_file' => array(
                    'title' => __('The path to the file for the journal of payments through Roskassa (for example, /roskassa_orders.log)', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('If the path is not specified, then the log is not written', 'woocommerce'),
                    'default' => ''
                ),
                'ip_filter' => array(
                    'title' => __('IP filter', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('List of trusted ip addresses, you can specify a mask', 'woocommerce'),
                    'default' => ''
                ),
                'email_error' => array(
                    'title' => __('Email for errors', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('Email for sending payment errors', 'woocommerce'),
                    'default' => ''
                ),
                'test_mode' => array(
                    'title' => __('Test mode', 'woocommerce'),
                    'type' => 'checkbox',
                    'label' => __('Enable', 'woocommerce'),
                    'default' => 'yes'
                ),
                'payment_system' => array(
                    'title' => __('Payment system ID', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('Switching to payment for a certain payment system (the list can be found here https://roskassa.gitbook.io/roskassa/spravochnaya-informaciya/payment-systems)', 'woocommerce'),
                    'default' => ''
                ),
            );
        }

        function payment_fields()
        {
            if ($this->description)
            {
                echo wpautop(wptexturize($this->description));
            }
        }

        public function generate_form($order_id)
        {
            global $woocommerce;

            $order = new WC_Order($order_id);

            $form_data = array(
                'shop_id'=>$this->roskassa_shop_id,
                'amount'=>round($order->order_total, 2),
                'order_id'=>$order_id,
                'currency'=>$order->order_currency == 'RUR' ? 'RUB' : $order->order_currency,
            );

            if ($this->get_option('test_mode') == 'yes') {
                $form_data['test'] = 1;
            }

            ksort($form_data);
            $str = http_build_query($form_data);
            $form_data['sign'] = md5($str . $this->roskassa_secret_key);

            $form_data['email'] = $order->get_billing_email();
            $form_data['source'] = $this->source;

            $form =  '<form method="GET" action="' . $this->roskassa_url . '">';
            foreach ($form_data as $k=>$v) {
                $form .= '<input type="hidden" name="'.$k.'" value="'.$v.'">';
            }
            $form .= '	<input type="submit" name="submit" value="Pay" /></form>';

            return $form;
        }

        function process_payment($order_id)
        {
            $order = new WC_Order($order_id);

            return array(
                'result' => 'success',
                'redirect'	=> add_query_arg('order', $order->id, add_query_arg('key', $order->order_key, get_permalink(woocommerce_get_page_id('pay'))))
            );
        }

        function receipt_page($order)
        {
            echo '<p>'.__('Thanks for your order, please click the button below to pay.', 'woocommerce').'</p>';
            echo $this->generate_form($order);
        }

        function check_ipn_response()
        {
            global $woocommerce;

            if (isset($_GET['roskassa']) && $_GET['roskassa'] == 'result')
            {
                if (isset($_POST["order_id"]) && isset($_POST["sign"]))
                {
                    $err = false;
                    $message = '';

                    // logging

                    $log_text =
                        "--------------------------------------------------------\n" .
                        "public key         " . sanitize_text_field($_POST['public_key']) . "\n" .
                        "amount             " . sanitize_text_field($_POST['amount']) . "\n" .
                        "order id           " . sanitize_text_field($_POST['order_id']) . "\n" .
                        "currency           " . sanitize_text_field($_POST['currency']) . "\n" .
                        "test               " . sanitize_text_field($_POST['test']) . "\n" .
                        "sign               " . sanitize_text_field($_POST['sign']) . "\n\n";

                    $log_file = $this->log_file;

                    if (!empty($log_file))
                    {
                        file_put_contents($_SERVER['DOCUMENT_ROOT'] . $log_file, $log_text, FILE_APPEND);
                    }

                    // verification of digital signature and ip

                    // we must use all POST request for sign, $data never user after
                    $data = $_POST;
                    unset($data['sign']);
                    ksort($data);
                    $str = http_build_query($data);
                    $sign_hash = md5($str . $this->roskassa_secret_key);

                    $valid_ip = true;
                    $sIP = str_replace(' ', '', $this->ip_filter);

                    if (!empty($sIP))
                    {
                        $arrIP = explode('.', $_SERVER['REMOTE_ADDR']);
                        if (!preg_match('/(^|,)(' . $arrIP[0] . '|\*{1})(\.)' .
                                        '(' . $arrIP[1] . '|\*{1})(\.)' .
                                        '(' . $arrIP[2] . '|\*{1})(\.)' .
                                        '(' . $arrIP[3] . '|\*{1})($|,)/', $sIP))
                        {
                            $valid_ip = false;
                        }
                    }

                    if (!$valid_ip)
                    {
                        $message .= " - server ip address is not trusted\n" .
                                    "   trusted ip: " . $sIP . "\n" .
                                    "   ip of the current server: " . $_SERVER['REMOTE_ADDR'] . "\n";
                        $err = true;
                    }

                    if ($_POST["sign"] != $sign_hash)
                    {
                        $message .= " - digital signatures do not match\n";
                        $err = true;
                    }

                    if (!$err)
                    {
                        // loading order

                        $order = new WC_Order(sanitize_text_field($_POST['order_id']));
                        $order_curr = ($order->order_currency == 'RUR') ? 'RUB' : $order->order_currency;
                        $order_amount = number_format($order->order_total, 2, '.', '');

                        // checking amount and currency

                        if (number_format($_POST['amount'], 2, '.', '') !== $order_amount)
                        {
                            $message .= " - wrong amount\n";
                            $err = true;
                        }

                        if ($_POST['currency'] !== $order_curr)
                        {
                            $message .= " - wrong currency\n";
                            $err = true;
                        }

                        // check status

                        if (!$err)
                        {
                            if ($order->post_status != 'wc-processing')
                            {
                                $order->update_status('processing', __('Payment was successfully paid', 'woocommerce'));
                                WC()->cart->empty_cart();
                            }
                        }
                    }

                    if ($err)
                    {
                        $to = $this->email_error;

                        if (!empty($to))
                        {
                            $message = "Failed to make a payment through the Roskassa system for the following reasons:\n\n" . $message . "\n" . $log_text;
                            $headers = "From: no-reply@" . $_SERVER['HTTP_HOST'] . "\r\n" .
                                       "Content-type: text/plain; charset=utf-8 \r\n";
                            mail($to, 'Payment error', $message, $headers);
                        }

                        if (!empty($log_file))
                        {
                            file_put_contents($_SERVER['DOCUMENT_ROOT'] . $log_file, "ERR: $message", FILE_APPEND);
                        }

                        die(sanitize_text_field($_POST['order_id']) . '|error');
                    }
                    else
                    {
                        die(sanitize_text_field($_POST['order_id']) . '|success');
                    }
                }
                else
                {
                    wp_die('IPN Request Failure');
                }
            }
            else if (isset($_GET['roskassa']) && ($_GET['roskassa'] == 'success' || $_GET['roskassa'] == 'fail'))
            {
                WC()->cart->empty_cart();
                $order = new WC_Order(sanitize_text_field($_GET['order_id']));
                wp_redirect($this->get_return_url($order));
            }
        }
    }

    function add_roskassa_gateway($methods)
    {
        $methods[] = 'WC_ROSKASSA';
        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'add_roskassa_gateway');

    function roskassa_settings_link( $links ) {
        $settings_link = '<a href="admin.php?page=wc-settings&tab=checkout&section=roskassa">' . __( 'Settings' ) . '</a>';


        array_push( $links, $settings_link );
        return $links;
    }
    $plugin = plugin_basename( __FILE__ );
    add_filter( "plugin_action_links_$plugin", 'roskassa_settings_link' );
}
?>