<?php

/**
 * @class       PayPal_Advanced_Gateway_For_WooCommerce_Admin_Display
 * @version	1.0.0
 * @package	paypal_advanced_gateway_for_woocommerce
 * @category	Class
 * @author      johnny manziel <phpwebcreators@gmail.com>
 */

class PayPal_Advanced_Gateway_For_WooCommerce_Admin_Display extends WC_Payment_Gateway {

    /**
     * 
     * @global type $woocommerce
     */
    public function __construct() {

        global $woocommerce;

        $this->id = 'paypal_payments_advanced';
        $this->icon = apply_filters('woocommerce_paypal_advanced_icon', '');
        $this->has_fields = true;
        $this->home_url = is_ssl() ? home_url('/', 'https') : home_url('/'); //set the urls (cancel or return) based on SSL
        $this->testurl = 'https://pilot-payflowpro.paypal.com';
        $this->liveurl = 'https://payflowpro.paypal.com';
        $this->relay_response_url = add_query_arg('wc-api', 'PayPal_Advanced_Gateway_For_WooCommerce_Admin_Display', $this->home_url);
        $this->method_title = __('PayPal Payments Advanced', 'paypal_advanced_gateway_for_woocommerce');
        $this->secure_token_id = '';
        $this->securetoken = '';

        $this->init_form_fields();
        $this->init_settings();

        $this->title = $this->settings['title'];
        $this->description = $this->settings['description'];
        $this->testmode = $this->settings['testmode'];
        $this->loginid = $this->settings['loginid'];
        $this->resellerid = $this->settings['resellerid'];
        $this->transtype = $this->settings['transtype'];
        $this->password = $this->settings['password'];
        $this->debug = $this->settings['debug'];
        $this->invoice_prefix = rtrim($this->settings['invoice_prefix'], '-') . '-';
        $this->page_collapse_bgcolor = $this->settings['page_collapse_bgcolor'];
        $this->page_collapse_textcolor = $this->settings['page_collapse_textcolor'];
        $this->page_button_bgcolor = $this->settings['page_button_bgcolor'];
        $this->page_button_textcolor = $this->settings['page_button_textcolor'];
        $this->label_textcolor = $this->settings['label_textcolor'];

        switch ($this->settings['layout']) {
            case 'A': $this->layout = 'TEMPLATEA';
                break;
            case 'B': $this->layout = 'TEMPLATEB';
                break;
            case 'C': $this->layout = 'MINLAYOUT';
                break;
        }


        $this->user = $this->settings['user'] == '' ? $this->settings['loginid'] : $this->settings['user'];
        $this->hostaddr = $this->testmode == 'yes' ? $this->testurl : $this->liveurl;

        if ($this->debug == 'yes')
            $this->log = PayPal_Advanced_Gateway_For_WooCommerce_Compatibility::new_wc_logger();

        add_action('admin_notices', array($this, 'checks')); //checks for availability of the plugin

        add_action('woocommerce_update_options_payment_gateways', array($this, 'process_admin_options')); // Save admin options for WC < 2.0
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options')); // Save admin options for WC >=2.0
        add_action('woocommerce_receipt_paypal_advanced', array($this, 'receipt_page')); // Payment form hook
        add_action('woocommerce_api_wc_paypal_advanced', array($this, 'relay_response')); // Payment listener/API hook

        if (!$this->is_available())
            $this->enabled = false;
    }

    /**
     * 
     * @global type $woocommerce
     * @return type
     */
    public function checks() {
        global $woocommerce;

        if ($this->enabled == 'no')
            return;

        if (!$this->loginid) {
            echo '<div class="error"><p>' . sprintf(__('PayPal Payments Advanced error: Please enter your PayPal Payments Advanced Account Merchant Login <a href="%s">here</a>', 'paypal_advanced_gateway_for_woocommerce'), PayPal_Advanced_Gateway_For_WooCommerce_Compatibility::get_configuration_url('PayPal_Advanced_Gateway_For_WooCommerce_Admin_Display')) . '</p></div>';
        } elseif (!$this->resellerid) {
            echo '<div class="error"><p>' . sprintf(__('PayPal Payments Advanced error: Please enter your PayPal Payments Advanced Account Partner <a href="%s">here</a>', 'paypal_advanced_gateway_for_woocommerce'), PayPal_Advanced_Gateway_For_WooCommerce_Compatibility::get_configuration_url('PayPal_Advanced_Gateway_For_WooCommerce_Admin_Display')) . '</p></div>';
        } elseif (!$this->password) {
            echo '<div class="error"><p>' . sprintf(__('PayPal Payments Advanced error: Please enter your PayPal Payments Advanced Account Password <a href="%s">here</a>', 'paypal_advanced_gateway_for_woocommerce'), PayPal_Advanced_Gateway_For_WooCommerce_Compatibility::get_configuration_url('PayPal_Advanced_Gateway_For_WooCommerce_Admin_Display')) . '</p></div>';
        }
        return;
    }

    /**
     * 
     * @global type $woocommerce
     * @param type $redirect_url
     */
    public function redirect_to($redirect_url) {

        global $woocommerce;

        @ob_clean();

        header('HTTP/1.1 200 OK');

        PayPal_Advanced_Gateway_For_WooCommerce_Compatibility::set_messages();

        if ($this->layout != 'MINLAYOUT') {
            wp_redirect($redirect_url);
        } else {
            echo "<script>window.parent.location.href='" . $redirect_url . "';</script>";
        }
        exit;
    }

    /**
     * 
     * @global type $woocommerce
     * @throws Exception
     */
    public function relay_response() {
        global $woocommerce;

        $not_silentreq_debug = ($this->debug == 'yes' && !isset($_REQUEST['silent'])) ? true : false;

        if (isset($_REQUEST['INVOICE'])) {
            $arr = explode('-', $_REQUEST['INVOICE']);
            if ($this->debug == 'yes') {
                $this->log->add('paypal_payment_advanced', sprintf(__('Relay Response INVOICE = %s', 'paypal_advanced_gateway_for_woocommerce'), $_REQUEST['INVOICE']));
            }
            if ($this->debug == 'yes') {
                $this->log->add('paypal_payment_advanced', sprintf(__('Relay Response SECURETOKEN = %s', 'paypal_advanced_gateway_for_woocommerce'), $_REQUEST['SECURETOKEN']));
            }
            if ($this->debug == 'yes') {
                $this->log->add('paypal_payment_advanced', sprintf(__('Relay Response Order ID = %s', 'paypal_advanced_gateway_for_woocommerce'), $arr[count($arr) - 1]));
            }
            if (get_post_meta($arr[count($arr) - 1], '_secure_token', true) == $_REQUEST['SECURETOKEN']) {
                if ($this->debug == 'yes') {
                    $this->log->add('paypal_payment_advanced', __('Relay Response Tokens Match', 'paypal_advanced_gateway_for_woocommerce'));
                }
                $_POST['ORDERID'] = $arr[count($arr) - 1];
            } else {
                if ($this->debug == 'yes') {
                    $this->log->add('paypal_payment_advanced', __('Relay Response Tokens Mismatch', 'paypal_advanced_gateway_for_woocommerce'));
                }
                wp_redirect(home_url('/'));
                exit;
            }
        } else {
            wp_redirect(home_url('/'));
            exit;
        }
        if ($this->debug == 'yes')
            if (isset($_REQUEST['silent']) && $_REQUEST['silent'] == 'true') {
                $this->log->add('paypal_payment_advanced', sprintf(__('Silent Relay Response Triggered: %s', 'paypal_advanced_gateway_for_woocommerce'), print_r($_REQUEST, true)));
            } else {
                $this->log->add('paypal_payment_advanced', sprintf(__('Relay Response Triggered: %s', 'paypal_advanced_gateway_for_woocommerce'), print_r($_REQUEST, true)));
            }
        $order = new WC_Order($_POST['ORDERID']);
        if ($order->status == 'processing' || $order->status == 'completed') {
            if ($not_silentreq_debug) {
                $this->log->add('paypal_payment_advanced', sprintf(__('Redirecting to Thank You Page for order #%s', 'paypal_advanced_gateway_for_woocommerce'), $_POST['ORDERID']));
            }
            $this->redirect_to($this->get_return_url($order));
        }
        if (isset($_REQUEST['error']) && $_REQUEST['error'] == 'true' && $_POST['RESULT'] != 0) { //handle errors and declines
            if ($_POST['RESULT'] == 12 && $order->status != 'failed') {
                $order->update_status('failed', __('Payment failed via PayPal Payments Advanced because of.', 'paypal_advanced_gateway_for_woocommerce') . '&nbsp;' . $_POST['RESPMSG']);
                if ($debug == 'yes') {
                    $this->log->add('paypal_payment_advanced', sprintf(__('Status has been changed to failed for order #%s', 'paypal_advanced_gateway_for_woocommerce'), $_POST['ORDERID']));
                }
            }

            $woocommerce->clear_messages();
            PayPal_Advanced_Gateway_For_WooCommerce_Compatibility::wc_add_notice(__('Error:', 'paypal_advanced_gateway_for_woocommerce') . ' "' . urldecode($_POST['RESPMSG']) . '"', 'error');

            if ($not_silentreq_debug) {
                $this->log->add('paypal_payment_advanced', sprintf(__('Silent Error Occurred while processing #%s : %s, status: %s', 'paypal_advanced_gateway_for_woocommerce'), $_POST['ORDERID'], urldecode($_POST['RESPMSG']), $_POST['RESULT']));
            } elseif ($debug == 'yes') {
                $this->log->add('paypal_payment_advanced', sprintf(__('Error Occurred while processing #%s : %s, status: %s', 'paypal_advanced_gateway_for_woocommerce'), $_POST['ORDERID'], urldecode($_POST['RESPMSG']), $_POST['RESULT']));
            }
            $this->redirect_to($order->get_checkout_payment_url(true));
        } elseif (isset($_REQUEST['cancel_ec_trans']) && $_REQUEST['cancel_ec_trans'] == 'true' && !isset($_REQUEST['silent'])) {//handle cancellations
            wp_redirect($order->get_cancel_order_url());
            exit;
        } elseif ($_POST['RESULT'] == 0) {//if approved		
            $order->add_order_note(sprintf(__('PayPal Payments Advanced payment completed (Order ID: %s). But needs to Inquiry transaction to have confirmation that it is actually paid.', 'paypal_advanced_gateway_for_woocommerce'), $_POST['ORDERID']));
            $paypal_args = array(
                'USER' => $this->user,
                'VENDOR' => $this->loginid,
                'PARTNER' => $this->resellerid,
                'PWD[' . strlen($this->password) . ']' => $this->password,
                'ORIGID' => $_POST['PNREF'],
                'TENDER' => 'C',
                'TRXTYPE' => 'I',
                'BUTTONSOURCE' => 'mbjtechnolabs_SP'
            );

            $postData = ''; //stores the post data string
            foreach ($paypal_args as $key => $val) {
                $postData .='&' . $key . '=' . $val;
            }

            $postData = trim($postData, '&');

            /* Using Curl post necessary information to the Paypal Site to generate the secured token */
            $response = wp_remote_post($this->hostaddr, array(
                'method' => 'POST',
                'body' => $postData,
                'timeout' => 70,
                'sslverify' => false,
                'user-agent' => 'Woocommerce ' . $woocommerce->version,
                'httpversion' => '1.1',
                'headers' => array('host' => 'www.paypal.com')
            ));
            if (is_wp_error($response)) {
                throw new Exception(__('There was a problem connecting to the payment gateway.', 'paypal_advanced_gateway_for_woocommerce'));
            }
            if (empty($response['body'])) {
                throw new Exception(__('Empty response.', 'paypal_advanced_gateway_for_woocommerce'));
            }
            $inquiry_result_arr = array(); //stores the response in array format
            parse_str($response['body'], $inquiry_result_arr);
            if ($inquiry_result_arr['RESULT'] == 0) {//if approved
                $order->add_order_note(sprintf(__('Received result of Inquiry Transaction for the  (Order ID: %s) and is successful', 'paypal_advanced_gateway_for_woocommerce'), $_POST['ORDERID']));
                $order->payment_complete();
                $woocommerce->cart->empty_cart();
                if ($not_silentreq_debug) {
                    $this->log->add('paypal_payment_advanced', sprintf(__('Redirecting to Thank You Page for order #%s', 'paypal_advanced_gateway_for_woocommerce'), $_POST['ORDERID']));
                }
                $this->redirect_to($this->get_return_url($order));
            }
        }
    }

    /**
     * 
     * @global type $woocommerce
     * @staticvar int $length_error
     * @param type $order
     * @return type
     * @throws Exception
     */
    function get_secure_token($order) {

        static $length_error = 0;

        global $woocommerce;

        if ($this->debug == 'yes') {
            $this->log->add('paypal_payment_advanced', sprintf(__('Requesting for the Secured Token for the order #%s', 'paypal_advanced_gateway_for_woocommerce'), $order->get_order_number()));
        }

        $this->secure_token_id = uniqid(substr($_SERVER['HTTP_HOST'], 0, 9), true);

        $paypal_args = array();

        $paypal_args = array(
            'VERBOSITY' => 'HIGH',
            'USER' => $this->user,
            'VENDOR' => $this->loginid,
            'PARTNER' => $this->resellerid,
            'PWD[' . strlen($this->password) . ']' => $this->password,
            'SECURETOKENID' => $this->secure_token_id,
            'CREATESECURETOKEN' => 'Y',
            'TRXTYPE' => $this->transtype,
            'CUSTREF' => $order->id,
            'INVNUM' => $this->invoice_prefix . $order->id,
            'AMT' => PayPal_Advanced_Gateway_For_WooCommerce_Compatibility::get_order_total($order),
            'COMPANYNAME[' . strlen($order->billing_company) . ']' => $order->billing_company,
            'CURRENCY' => get_woocommerce_currency(),
            'EMAIL' => $order->billing_email,
            'BILLTOFIRSTNAME[' . strlen($order->billing_first_name) . ']' => $order->billing_first_name,
            'BILLTOLASTNAME[' . strlen($order->billing_last_name) . ']' => $order->billing_last_name,
            'BILLTOSTREET[' . strlen($order->billing_address_1 . ' ' . $order->billing_address_2) . ']' => $order->billing_address_1 . ' ' . $order->billing_address_2,
            'BILLTOCITY[' . strlen($order->billing_city) . ']' => $order->billing_city,
            'BILLTOSTATE[' . strlen($order->billing_state) . ']' => $order->billing_state,
            'BILLTOZIP' => $order->billing_postcode,
            'BILLTOCOUNTRY[' . strlen($order->billing_country) . ']' => $order->billing_country,
            'BILLTOEMAIL' => $order->billing_email,
            'BILLTOPHONENUM' => $order->billing_phone,
            'SHIPTOFIRSTNAME[' . strlen($order->shipping_first_name) . ']' => $order->shipping_first_name,
            'SHIPTOLASTNAME[' . strlen($order->shipping_last_name) . ']' => $order->shipping_last_name,
            'SHIPTOSTREET[' . strlen($order->shipping_address_1 . ' ' . $order->shipping_address_2) . ']' => $order->shipping_address_1 . ' ' . $order->shipping_address_2,
            'SHIPTOCITY[' . strlen($order->shipping_city) . ']' => $order->shipping_city,
            'SHIPTOZIP' => $order->shipping_postcode,
            'SHIPTOCOUNTRY[' . strlen($order->shipping_country) . ']' => $order->shipping_country,
            'BUTTONSOURCE' => 'mbjtechnolabs_SP',
            'RETURNURL[' . strlen($this->relay_response_url) . ']' => $this->relay_response_url,
            'ERRORURL[' . strlen($this->relay_response_url) . ']' => $this->relay_response_url,
            'SILENTPOSTURL[' . strlen($this->relay_response_url) . ']' => $this->relay_response_url,
            'URLMETHOD' => 'POST',
            'TEMPLATE' => $this->layout,
            'PAGECOLLAPSEBGCOLOR' => ltrim($this->page_collapse_bgcolor, '#'),
            'PAGECOLLAPSETEXTCOLOR' => ltrim($this->page_collapse_textcolor, '#'),
            'PAGEBUTTONBGCOLOR' => ltrim($this->page_button_bgcolor, '#'),
            'PAGEBUTTONTEXTCOLOR' => ltrim($this->page_button_textcolor, '#'),
            'LABELTEXTCOLOR' => ltrim($this->settings['label_textcolor'], '#')
        );

        if (empty($order->shipping_state)) {
            $paypal_args['SHIPTOSTATE[' . strlen($order->shipping_city) . ']'] = $order->shipping_city;
        } else {
            $paypal_args['SHIPTOSTATE[' . strlen($order->shipping_state) . ']'] = $order->shipping_state;
        }

        $cancelurl = add_query_arg('wc-api', 'PayPal_Advanced_Gateway_For_WooCommerce_Admin_Display', add_query_arg('cancel_ec_trans', 'true', $this->home_url));
        $paypal_args['CANCELURL[' . strlen($cancelurl) . ']'] = $cancelurl;

        $errorurl = add_query_arg('wc-api', 'PayPal_Advanced_Gateway_For_WooCommerce_Admin_Display', add_query_arg('error', 'true', $this->home_url));
        $paypal_args['ERRORURL[' . strlen($errorurl) . ']'] = $errorurl;

        $silentposturl = add_query_arg('wc-api', 'PayPal_Advanced_Gateway_For_WooCommerce_Admin_Display', add_query_arg('silent', 'true', $this->home_url));
        $paypal_args['SILENTPOSTURL[' . strlen($silentposturl) . ']'] = $silentposturl;

        if ($order->prices_include_tax == 'yes' || $order->get_order_discount() > 0 || $length_error > 1) {
            $paypal_args['discount_amount_cart'] = $order->get_order_discount();
            $item_names = array();

            if (sizeof($order->get_items()) > 0)
                if ($length_error <= 1) {
                    foreach ($order->get_items() as $item) {
                        if ($item['qty']) {
                            $item_names[] = $item['name'] . ' x ' . $item['qty'];
                        }
                    }
                } else {
                    $item_names[] = "All selected items,refer to Woocommerce order details";
                }
            $items_str = sprintf(__('Order %s', 'paypal_advanced_gateway_for_woocommerce'), $order->get_order_number()) . " - " . implode(', ', $item_names);
            $paypal_args['L_NAME1[' . strlen($items_str) . ']'] = $items_str;
            $paypal_args['L_QTY1'] = 1;
            $paypal_args['L_COST1'] = number_format($order->get_total() - PayPal_Advanced_Gateway_For_WooCommerce_Compatibility::get_shipping_total($order) - $order->get_shipping_tax() + $order->get_order_discount(), 2, '.', '');

            if (( PayPal_Advanced_Gateway_For_WooCommerce_Compatibility::get_shipping_total($order) + $order->get_shipping_tax() ) > 0) :

                $ship_method_title = __('Shipping via', 'woocommerce') . ' ' . ucwords($order->shipping_method_title);
                $paypal_args['L_NAME2[' . strlen($ship_method_title) . ']'] = $ship_method_title;
                $paypal_args['L_QTY2'] = '1';
                $paypal_args['L_COST2'] = number_format(PayPal_Advanced_Gateway_For_WooCommerce_Compatibility::get_shipping_total($order) + $order->get_shipping_tax(), 2, '.', '');
            endif;
        } else {

            $paypal_args['TAXAMT'] = $order->get_total_tax();

            $item_loop = 0;
            if (sizeof($order->get_items()) > 0) {
                foreach ($order->get_items() as $item) {
                    if ($item['qty']) {

                        $item_loop++;

                        $product = $order->get_product_from_item($item);

                        $item_name = $item['name'];

                        $item_meta = new WC_order_item_meta($item['item_meta']);
                        if ($length_error == 0 && $meta = $item_meta->display(true, true))
                            $item_name .= ' (' . $meta . ')';

                        $paypal_args['L_NAME' . $item_loop . '[' . strlen($item_name) . ']'] = $item_name;
                        if ($product->get_sku())
                            $paypal_args['L_SKU' . $item_loop] = $product->get_sku();
                        $paypal_args['L_QTY' . $item_loop] = $item['qty'];
                        $paypal_args['L_COST' . $item_loop] = $order->get_item_total($item, false);
                        $paypal_args['L_TAXAMT' . $item_loop] = $order->get_item_tax($item, false);
                    }
                }
            }

            if (PayPal_Advanced_Gateway_For_WooCommerce_Compatibility::get_shipping_total($order) + $order->get_shipping_tax() > 0) {
                $item_loop++;
                $ship_method_title = __('Shipping via', 'woocommerce') . ' ' . ucwords($order->shipping_method_title);
                $paypal_args['L_NAME' . $item_loop . '[' . strlen($ship_method_title) . ']'] = $ship_method_title;
                $paypal_args['L_QTY' . $item_loop] = '1';
                $paypal_args['L_COST' . $item_loop] = PayPal_Advanced_Gateway_For_WooCommerce_Compatibility::get_shipping_total($order);
                $paypal_args['L_TAXAMT' . $item_loop] = $order->get_shipping_tax();
            }
        }

        $paypal_args = apply_filters('woocommerce_paypal_args', $paypal_args);

        try {

            $postData = '';
            $logData = '';
            foreach ($paypal_args as $key => $val) {

                $postData .='&' . $key . '=' . $val;
                if (strpos($key, 'PWD') === 0)
                    $logData .='&PWD=XXXX';
                else
                    $logData .='&' . $key . '=' . $val;
            }

            $postData = trim($postData, '&');

            if ($this->debug == 'yes') {

                reset($paypal_args);

                foreach ($paypal_args as $key => $val) {

                    if (strpos($key, 'PWD') === 0)
                        $logData .='&PWD=XXXX';
                    else
                        $logData .='&' . $key . '=' . $val;
                }
                $logData = trim($logData, '&');

                $this->log->add('paypal_payment_advanced', sprintf(__('Requesting for the Secured Token for the order #%s with following URL and Paramaters: %s', 'paypal_advanced_gateway_for_woocommerce'), $order->id, $this->hostaddr . '?' . $logData));
            }


            $response = wp_remote_post($this->hostaddr, array(
                'method' => 'POST',
                'body' => $postData,
                'timeout' => 70,
                'sslverify' => false,
                'user-agent' => 'WooCommerce ' . $woocommerce->version,
                'httpversion' => '1.1',
                'headers' => array('host' => 'www.paypal.com')
            ));


            if (is_wp_error($response)) {

                throw new Exception($response->get_error_message());
            }
            if (empty($response['body']))
                throw new Exception(__('Empty response.', 'paypal_advanced_gateway_for_woocommerce'));

            parse_str($response['body'], $arr);

            if ($arr['RESULT'] > 0) {

                throw new Exception(__('There was an error processing your order - ' . $arr['RESPMSG'], 'paypal_advanced_gateway_for_woocommerce'));
            } else {
                return $arr['SECURETOKEN'];
            }
        } catch (Exception $e) {

            if ($this->debug == 'yes')
                $this->log->add('paypal_payment_advanced', sprintf(__('Secured Token generation failed for the order #%s with error: %s', 'paypal_advanced_gateway_for_woocommerce'), $order->id, $e->getMessage()));

            if ($arr['RESULT'] != 7) {
                PayPal_Advanced_Gateway_For_WooCommerce_Compatibility::wc_add_notice(__('Error:', 'paypal_advanced_gateway_for_woocommerce') . ' "' . $e->getMessage() . '"', 'error');
                $length_error = 0;
                return;
            } else {

                if ($this->debug == 'yes')
                    $this->log->add('paypal_payment_advanced', sprintf(__('Secured Token generation failed for the order #%s with error: %s', 'paypal_advanced_gateway_for_woocommerce'), $order->id, $e->getMessage()));

                $length_error++;
                return $this->get_secure_token($order);
            }
        }
    }

    /**
     * 
     * @return boolean
     */
    public function is_available() {


        if ($this->enabled == 'yes')
            return true;

        return false;
    }

    /**
     * 
     * @return type
     */
    public function admin_options() {
        ?>
        <h3><?php _e('PayPal Payments Advanced', 'paypal_advanced_gateway_for_woocommerce'); ?></h3>
        <p><?php _e('PayPal Payments Advanced uses an iframe to seamlessly integrate PayPal hosted pages into the checkout process.', 'paypal_advanced_gateway_for_woocommerce'); ?></p>
        <table class="form-table">
            <?php
            if (!in_array(get_woocommerce_currency(), array('USD', 'CAD'))) {
                ?>
                <div class="inline error"><p><strong><?php _e('Gateway Disabled', 'paypal_advanced_gateway_for_woocommerce'); ?></strong>: <?php _e('PayPal does not support your store currency.', 'paypal_advanced_gateway_for_woocommerce'); ?></p></div>
                <?php
                return;
            } else {

                $this->generate_settings_html();
            }
            ?>
        </table>
        <?php
    }

    /**
     * 
     */
    public function init_form_fields() {

        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Enable/Disable', 'paypal_advanced_gateway_for_woocommerce'),
                'type' => 'checkbox',
                'label' => __('Enable PayPal Payments Advanced', 'paypal_advanced_gateway_for_woocommerce'),
                'default' => 'yes'
            ),
            'debug' => array(
                'title' => __('Debug Log', 'paypal_advanced_gateway_for_woocommerce'),
                'type' => 'checkbox',
                'label' => __('Enable logging', 'paypal_advanced_gateway_for_woocommerce'),
                'default' => 'no',
                'description' => __('Log PayPal events, such as Secured Token requests, inside <code>woocommerce/logs/paypal_payment_advanced.txt</code>', 'paypal_advanced_gateway_for_woocommerce'),
            ),
            'testmode' => array(
                'title' => __('PayPal sandbox', 'paypal_advanced_gateway_for_woocommerce'),
                'type' => 'checkbox',
                'label' => __('Enable PayPal sandbox', 'paypal_advanced_gateway_for_woocommerce'),
                'default' => 'yes',
                'description' => sprintf(__('PayPal sandbox can be used to test payments. Sign up for a developer account <a target="_blank" href="%s">here</a>', 'paypal_advanced_gateway_for_woocommerce'), 'https://developer.paypal.com/'),
            ),
            'title' => array(
                'title' => __('Title', 'paypal_advanced_gateway_for_woocommerce'),
                'type' => 'text',
                'description' => __('This controls the title which the user sees during checkout.', 'paypal_advanced_gateway_for_woocommerce'),
                'default' => __('PayPal Payments Advanced', 'paypal_advanced_gateway_for_woocommerce')
            ),
            'description' => array(
                'title' => __('Description', 'paypal_advanced_gateway_for_woocommerce'),
                'type' => 'text',
                'description' => __('This controls the description which the user sees during checkout.', 'paypal_advanced_gateway_for_woocommerce'),
                'default' => __('PayPal Payments Advanced dsecription', 'paypal_advanced_gateway_for_woocommerce')
            ),
            'loginid' => array(
                'title' => __('Merchant Login', 'paypal_advanced_gateway_for_woocommerce'),
                'type' => 'text',
                'description' => '',
                'default' => ''
            ),
            'resellerid' => array(
                'title' => __('Partner', 'paypal_advanced_gateway_for_woocommerce'),
                'type' => 'text',
                'description' => __('Enter your PayPal Payments Advanced Partner. If you purchased the account directly from PayPal, use PayPal.', 'paypal_advanced_gateway_for_woocommerce'),
                'default' => ''
            ),
            'user' => array(
                'title' => __('User (or Merchant Login if no designated user is set up for the account)', 'paypal_advanced_gateway_for_woocommerce'),
                'type' => 'text',
                'description' => __('Enter your PayPal Payments Advanced user account for this site.', 'paypal_advanced_gateway_for_woocommerce'),
                'default' => ''
            ),
            'password' => array(
                'title' => __('Password', 'paypal_advanced_gateway_for_woocommerce'),
                'type' => 'password',
                'description' => __('Enter your PayPal Payments Advanced account password.', 'paypal_advanced_gateway_for_woocommerce'),
                'default' => ''
            ),
            'transtype' => array(
                'title' => __('Transaction Type', 'paypal_advanced_gateway_for_woocommerce'),
                'type' => 'select',
                'label' => __('Transaction Type', 'paypal_advanced_gateway_for_woocommerce'),
                'default' => 'S',
                'description' => '',
                'options' => array('A' => 'Authorization', 'S' => 'Sale')
            ),
            'layout' => array(
                'title' => __('Layout', 'paypal_advanced_gateway_for_woocommerce'),
                'type' => 'select',
                'label' => __('Layout', 'paypal_advanced_gateway_for_woocommerce'),
                'default' => 'C',
                'description' => __('Layouts A and B redirect to PayPal\'s website for the user to pay. <br/>Layout C (recommended) is a secure PayPal-hosted page but is embedded on your site using an iFrame.', 'paypal_advanced_gateway_for_woocommerce'),
                'options' => array('A' => 'Layout A', 'B' => 'Layout B', 'C' => 'Layout C')
            ),
            'invoice_prefix' => array(
                'title' => __('Invoice Prefix', 'paypal_advanced_gateway_for_woocommerce'),
                'type' => 'text',
                'description' => __('Please enter a prefix for your invoice numbers. If you use your PayPal account for multiple stores ensure this prefix is unique as PayPal will not allow orders with the same invoice number.Please use hyphen(-) as suffix.', 'woocommerce'),
                'default' => 'WC-PPADV-',
                'desc_tip' => true,
            ),
            'page_collapse_bgcolor' => array(
                'title' => __('Page Collapse Border Color', 'paypal_advanced_gateway_for_woocommerce'),
                'type' => 'text',
                'description' => __('Sets the color of the border around the embedded template C.', 'paypal_advanced_gateway_for_woocommerce'),
                'default' => '',
                'desc_tip' => true,
                'class' => 'paypal_advanced_gateway_for_woocommerce_color_field'
            ),
            'page_collapse_textcolor' => array(
                'title' => __('Page Collapse Text Color', 'paypal_advanced_gateway_for_woocommerce'),
                'type' => 'text',
                'description' => __('Sets the color of the words "Pay with PayPal" and "Pay with credit or debit card".', 'paypal_advanced_gateway_for_woocommerce'),
                'default' => '',
                'desc_tip' => true,
                'class' => 'paypal_advanced_gateway_for_woocommerce_color_field'
            ),
            'page_button_bgcolor' => array(
                'title' => __('Page Button Background Color', 'paypal_advanced_gateway_for_woocommerce'),
                'type' => 'text',
                'description' => __('Sets the background color of the Pay Now / Submit button.', 'paypal_advanced_gateway_for_woocommerce'),
                'default' => '',
                'desc_tip' => true,
                'class' => 'paypal_advanced_gateway_for_woocommerce_color_field'
            ),
            'page_button_textcolor' => array(
                'title' => __('Page Button Text Color', 'paypal_advanced_gateway_for_woocommerce'),
                'type' => 'text',
                'description' => __('Sets the color of the text on the Pay Now / Submit button.', 'paypal_advanced_gateway_for_woocommerce'),
                'default' => '',
                'desc_tip' => true,
                'class' => 'paypal_advanced_gateway_for_woocommerce_color_field'
            ),
            'label_textcolor' => array(
                'title' => __('Label Text Color', 'paypal_advanced_gateway_for_woocommerce'),
                'type' => 'text',
                'description' => __('Sets the color of the text for "card number", "expiration date", ..etc.', 'paypal_advanced_gateway_for_woocommerce'),
                'default' => '',
                'desc_tip' => true,
                'class' => 'paypal_advanced_gateway_for_woocommerce_color_field'
            )
        );
    }

    /**
     * 
     */
    public function payment_fields() {

        if ($this->description)
            echo wpautop(wptexturize($this->description));
    }

    /**
     * 
     * @global type $woocommerce
     * @param type $order_id
     * @return type
     */
    public function process_payment($order_id) {
        global $woocommerce;


        $order = new WC_Order($order_id);


        try {


            $this->securetoken = $this->get_secure_token($order);


            if ($this->securetoken != "") {


                update_post_meta($order->id, '_secure_token_id', $this->secure_token_id);
                update_post_meta($order->id, '_secure_token', $this->securetoken);


                if ($this->debug == 'yes')
                    $this->log->add('paypal_payment_advanced', sprintf(__('Secured Token generated successfully for the order #%s', 'paypal_advanced_gateway_for_woocommerce'), $order_id));


                return array(
                    'result' => 'success',
                    'redirect' => $order->get_checkout_payment_url(true)
                );
            }
        } catch (Exception $e) {


            PayPal_Advanced_Gateway_For_WooCommerce_Compatibility::wc_add_notice(__('Error:', 'paypal_advanced_gateway_for_woocommerce') . ' "' . $e->getMessage() . '"', 'error');


            if ($this->debug == 'yes')
                $this->log->add('paypal_payment_advanced', 'Error Occurred while processing the order #' . $order_id);
        }
        return;
    }

    /**
     * 
     * @param type $order_id
     */
    public function receipt_page($order_id) {


        $PF_MODE = $this->settings['testmode'] == 'yes' ? 'TEST' : 'LIVE';


        $order = new WC_Order($order_id);


        $this->secure_token_id = get_post_meta($order->id, '_secure_token_id', true);
        $this->securetoken = get_post_meta($order->id, '_secure_token', true);


        if ($this->debug == 'yes')
            $this->log->add('paypal_payment_advanced', sprintf(__('Browser Info: %s', 'paypal_advanced_gateway_for_woocommerce'), $_SERVER['HTTP_USER_AGENT']));


        if ($this->layout == 'MINLAYOUT' || $this->layout == 'C') {

            $location = 'https://payflowlink.paypal.com?mode=' . $PF_MODE . '&amp;SECURETOKEN=' . $this->securetoken . '&amp;SECURETOKENID=' . $this->secure_token_id;


            if ($this->debug == 'yes')
                $this->log->add('paypal_payment_advanced', sprintf(__('Show payment form(IFRAME) for the order #%s as it is configured to use Layout C', 'paypal_advanced_gateway_for_woocommerce'), $order_id));
            ?>
            <iframe id="paypal_advanced_gateway_for_woocommerce_iframe" src="<?php echo $location; ?>" width="550" height="565" scrolling="no" frameborder="0" border="0" allowtransparency="true"></iframe>

            <?php
        }else {

            $location = 'https://payflowlink.paypal.com?mode=' . $PF_MODE . '&SECURETOKEN=' . $this->securetoken . '&SECURETOKENID=' . $this->secure_token_id;


            if ($this->debug == 'yes')
                $this->log->add('paypal_payment_advanced', sprintf(__('Show payment form redirecting to ' . $location . ' for the order #%s as it is not configured to use Layout C', 'paypal_advanced_gateway_for_woocommerce'), $order_id));


            wp_redirect($location);
            exit;
        }
    }

}