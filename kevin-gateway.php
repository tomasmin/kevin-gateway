<?php
/*
 * Plugin Name: WooCommerce Kevin Payment Gateway
 * Plugin URI: N/A
 * Description: Kevin gateway
 * Author: Tomas Mineika
 * Author URI: http://tomasmin.github.io
 * Version: 1.0.0
 */

add_filter('woocommerce_payment_gateways', 'kevin_add_gateway_class');
function kevin_add_gateway_class($gateways)
{
    $gateways[] = 'WC_Kevin_Gateway';
    return $gateways;
}

add_action('plugins_loaded', 'kevin_init_gateway_class');
function kevin_init_gateway_class()
{

    class WC_Kevin_Gateway extends WC_Payment_Gateway
    {

        public function __construct()
        {
            $this->id = 'kevin';
            $this->has_fields = false;
            $this->method_title = 'Kevin Gateway';
            $this->method_description = 'Description of Kevin payment gateway';

            $this->supports = array(
                'products'
            );

            $this->init_form_fields();

            $this->init_settings();
            $this->title = $this->get_option('title');
            $this->description = $this->get_option('description');
            $this->display_bank_options = $this->get_option('display_bank_options');
            $this->enabled = $this->get_option('enabled');
            $this->testmode = 'yes' === $this->get_option('testmode');
            $this->client_secret = $this->testmode ? $this->get_option('test_client_secret') : $this->get_option('client_secret');
            $this->client_id = $this->testmode ? $this->get_option('test_client_id') : $this->get_option('client_id');
            $this->apiUrl = $this->testmode ? 'https://api.getkevin.eu/platform/pis/payment/' : 'https://api.getkevin.eu/platform/pis/payment/';
            $this->creditor_name = $this->get_option('creditor_name');
            $this->creditor_account = $this->get_option('creditor_account');
            $this->payment_details = $this->get_option('payment_details');
			
			if($this->display_bank_options == 'no') {
				$this->icon = apply_filters('woocommerce_kevin_icon', plugins_url('assets/images/kevin.png', __FILE__));
			}

            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
            add_action('woocommerce_api_wc_gateway_kevin', array($this, 'webhook'));
			add_action('woocommerce_thankyou_kevin', array($this, 'thankyou'));
        }

        public function init_form_fields()
        {
            $this->form_fields = array(
                'enabled' => array(
                    'title'       => 'Enable/Disable',
                    'label'       => 'Enable Kevin Gateway',
                    'type'        => 'checkbox',
                    'description' => '',
                    'default'     => 'no'
                ),
                'title' => array(
                    'title'       => 'Title',
                    'type'        => 'text',
                    'description' => 'This controls the title which the user sees during checkout.',
                    'default'     => 'Kevin',
                    'desc_tip'    => true,
                ),
                'description' => array(
                    'title'       => 'Description',
                    'type'        => 'textarea',
                    'description' => 'This controls the description which the user sees during checkout.',
                    'default'     => 'Pay with Kevin',
                ),
                'display_bank_options' => array(
                    'title'       => 'Display Bank Options',
                    'type'        => 'checkbox',
                    'description' => 'Does not work with test mode',
                    'default'     => 'no',
					'desc_tip'    => true
                ),
                'testmode' => array(
                    'title'       => 'Test mode',
                    'label'       => 'Enable Test Mode',
                    'type'        => 'checkbox',
                    'description' => 'Place the payment gateway in test mode using test API keys.',
                    'default'     => 'yes',
                    'desc_tip'    => true
                ),
                'test_client_id' => array(
                    'title'       => 'Test Client ID',
                    'type'        => 'text'
                ),
                'test_client_secret' => array(
                    'title'       => 'Test Client Secret',
                    'type'        => 'text',
                ),
                'client_id' => array(
                    'title'       => 'Client ID',
                    'type'        => 'text'
                ),
                'client_secret' => array(
                    'title'       => 'Client Secret',
                    'type'        => 'text'
                ),
                'creditor_name' => array(
                    'title'       => 'Creditor Name',
                    'type'        => 'text'
                ),
                'creditor_account' => array(
                    'title'       => 'Creditor Account',
                    'type'        => 'text'
                ),
                'payment_details' => array(
                    'title'       => 'Payment Details',
                    'type'        => 'text'
                )
            );
        }

        public function payment_fields()
        {

            if ($this->display_bank_options == 'yes') {
//                 if ($this->description) {
//                     if ($this->testmode) {
//                         $this->description .= ' TEST MODE ENABLED. In test mode bank options will not work work';
//                         $this->description  = trim($this->description);
//                     }
//                     echo wpautop(wp_kses_post($this->description));
//                 }
                $SB_LT = plugins_url('assets/images/SB_LT.png', __FILE__);
                $SEB_LT = plugins_url('assets/images/SEB_LT.png', __FILE__);
                $SWEDBANK_LT = plugins_url('assets/images/SWEDBANK_LT.png', __FILE__);

                echo '<div><input type="radio" id="SB_LT" style="margin-bottom: 1em;" name="kevinBank" value="SB_LT">
                <label for="SB_LT"><img style="display: inline-block; float: none;" src="'.$SB_LT.'" alt="Šiaulių Bankas"></img></label><br>
                <input type="radio" id="SEB_LT" style="margin-bottom: 1em;" name="kevinBank" value="SEB_LT">
                <label for="SEB_LT"><img style="display: inline-block; float: none;" src="'.$SEB_LT.'" alt="SEB"></img></label><br>
                <input type="radio" id="SWEDBANK_LT" name="kevinBank" value="SWEDBANK_LT">
                <label for="SWEDBANK_LT"><img style="display: inline-block; float: none;" src="'.$SWEDBANK_LT.'" alt="Swedbank"></img></label></div>';

            } else {
                $description = $this->get_description();
		        if ( $description ) {
			    echo wpautop( wptexturize( $description ) ); // @codingStandardsIgnoreLine.
		    }
            }
        }

        public function validate_fields(){
			if($this->display_bank_options == 'yes'){
            	if( empty( $_POST[ 'kevinBank' ]) ) {
                	wc_add_notice(  'Bank is required!', 'error' );
                	return false;
            	}
            	return true;
			}
        }

        public function process_payment($order_id)
        {

            global $woocommerce;

            $order = wc_get_order($order_id);

            $payment_details = $this->payment_details . ' ' . $order_id;

            $redirectUrl = $this->get_return_url($order);

            $args = array(
                'headers' => array(
                    'Client-Id' => $this->client_id,
                    'Client-Secret' => $this->client_secret,
                    'Redirect-URL' => $redirectUrl,
                    'Webhook-URL' => trailingslashit(get_bloginfo('wpurl')) . 'index.php/wc-api/wc_gateway_kevin/',
                    'Content-Type' => 'application/json'
                ),
                'body' => json_encode(
                    array(
                        'endToEndId' => strval($order_id),
                        'informationUnstructured' => $payment_details,
                        'currencyCode' => $order->get_currency(),
                        'amount' => $order->get_total(),
                        'creditorName' => $this->creditor_name,
                        'creditorAccount' => array('iban' => $this->creditor_account)
                    )
                )
            );

            if($this->display_bank_options == 'yes'){
                $response = wp_remote_post($this->apiUrl . '?bankId=' . $_POST[ 'kevinBank' ], $args);
            } else {
                $response = wp_remote_post($this->apiUrl, $args);
            }

            if (!is_wp_error($response)) {

                $responseBody = wp_remote_retrieve_body($response);

                $responseBodyDecoded = json_decode($responseBody);

                //$woocommerce->cart->empty_cart();

                return array(
                    'result' => 'success',
                    'redirect' => $responseBodyDecoded->confirmLink
                );
            } else {
                wc_add_notice('Connection error.', 'error');
                return;
            }
        }

        public function webhook()
        {

            $payment_id = $_POST['id'];

            $args = array(
                'headers' => array(
                    'Client-Id' => $this->client_id,
                    'Client-Secret' => $this->client_secret,
                    'PSU-IP-Address' => WC_Geolocation::get_ip_address()
                )
            );

            $response = wp_remote_get($this->apiUrl . $payment_id, $args);

            $responseBody = wp_remote_retrieve_body($response);
            $responseBodyDecoded = json_decode($responseBody);

            $order_id = $responseBodyDecoded->endToEndId;
            $order = wc_get_order($order_id);
            $order->add_order_note('Kevin: Payment status: ' . $responseBodyDecoded->statusGroup);

            if ($responseBodyDecoded->statusGroup == 'completed') {
                $order->payment_complete();
                $order->reduce_order_stock();
            }
        }
		
		function thankyou() {
    		
			if( !is_wc_endpoint_url( 'order-received' ) || empty( $_GET['key'] ) || empty( $_GET['paymentId'] ) ) {
				return;
			}
			
			$payment_id = $_GET['paymentId'];
			$key = $_GET['key'];
			$order_id = wc_get_order_id_by_order_key( $key );

            $args = array(
                'headers' => array(
                    'Client-Id' => $this->client_id,
                    'Client-Secret' => $this->client_secret,
                    'PSU-IP-Address' => WC_Geolocation::get_ip_address()
                )
            );

            $response = wp_remote_get($this->apiUrl . $payment_id . '/status', $args);
			
			$responseBody = wp_remote_retrieve_body($response);
            $responseBodyDecoded = json_decode($responseBody);

            if ($responseBodyDecoded->group != 'completed') {
				$order = wc_get_order( $order_id );
				$order->add_order_note('Kevin: Client is redirected to payment page. Payment status: ' . $responseBodyDecoded->status);
				$url = $order->get_checkout_payment_url( $on_checkout = false );
                wp_redirect( $url );
				exit;
            }
			
		}
    }
}
