<?php
  /**
   * ### Midtrans Payment Plugin for Wordrpress-WooCommerce ###
   *
   * This plugin allow your Wordrpress-WooCommerce to accept payment from customer using Midtrans Payment Gateway solution.
   *
   * @category   Wordrpress-WooCommerce Payment Plugin
   * @author     Rizda Dwi Prasetya <rizda.prasetya@midtrans.com>
   * @version    1.1.0
   * @link       http://docs.midtrans.com
   * (This plugin is made based on Payment Plugin Template by WooCommerce)
   *
   * LICENSE: This program is free software; you can redistribute it and/or
   * modify it under the terms of the GNU General Public License
   * as published by the Free Software Foundation; either version 2
   * of the License, or (at your option) any later version.
   * 
   * This program is distributed in the hope that it will be useful,
   * but WITHOUT ANY WARRANTY; without even the implied warranty of
   * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
   * GNU General Public License for more details.
   * 
   * You should have received a copy of the GNU General Public License
   * along with this program; if not, write to the Free Software
   * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
   */

    // TODO uncomment these, use the real snap php library class (make sure to do this on other file too)
     require_once(dirname(__FILE__) . '/../lib/veritrans/Veritrans.php'); 
    
    // TODO remove this
    // require_once(dirname(__FILE__) . '/../../veritrans-woocommerce-2.1.0/lib/veritrans/Veritrans.php');

    /**
     * Midtrans Payment Gateway Class
     */
    class WC_Gateway_Midtrans extends WC_Payment_Gateway {

      /**
       * Constructor
       */
      function __construct() {
        $this->id           = 'midtrans';
        $this->icon         = apply_filters( 'woocommerce_midtrans_icon', '' );
        $this->method_title = __( 'Midtrans', 'Midtrans' );
        $this->has_fields   = true;
        $this->notify_url   = str_replace( 'https:', 'http:', add_query_arg( 'wc-api', 'WC_Gateway_Midtrans', home_url( '/' ) ) );

        // Load the settings
        $this->init_form_fields();
        $this->init_settings();

        // Get Settings
        $this->title              = $this->get_option( 'title' );
        $this->description        = $this->get_option( 'description' );
        $this->select_midtrans_payment = $this->get_option( 'select_midtrans_payment' );
        
        $this->client_key_v2_sandbox         = $this->get_option( 'client_key_v2_sandbox' );
        $this->server_key_v2_sandbox         = $this->get_option( 'server_key_v2_sandbox' );
        $this->client_key_v2_production         = $this->get_option( 'client_key_v2_production' );
        $this->server_key_v2_production         = $this->get_option( 'server_key_v2_production' );

        $this->api_version        = 2;
        $this->environment        = $this->get_option( 'select_midtrans_environment' );
        
        $this->to_idr_rate        = $this->get_option( 'to_idr_rate' );

        $this->enable_3d_secure   = $this->get_option( 'enable_3d_secure' );
        $this->enable_savecard   = $this->get_option( 'enable_savecard' );
        $this->custom_expiry   = $this->get_option( 'custom_expiry' );
        // $this->enable_sanitization = $this->get_option( 'enable_sanitization' );
        $this->enable_credit_card = $this->get_option( 'credit_card' );
        $this->enable_mandiri_clickpay = $this->get_option( 'mandiri_clickpay' );
        $this->enable_cimb_clicks = $this->get_option( 'cimb_clicks' );
        $this->enable_permata_va = $this->get_option( 'bank_transfer' );
        $this->enable_bri_epay = $this->get_option( 'bri_epay' );
        $this->enable_telkomsel_cash = $this->get_option( 'telkomsel_cash' );
        $this->enable_xl_tunai = $this->get_option( 'xl_tunai' );
        $this->enable_bbmmoney = $this->get_option( 'bbmmoney' );
        $this->enable_mandiri_bill = $this->get_option( 'mandiri_bill' );
        $this->enable_indomaret = $this->get_option('cstore');
        $this->enable_indosat_dompetku = $this->get_option('indosat_dompetku');
        $this->enable_mandiri_ecash = $this->get_option('mandiri_ecash');

        $this->client_key         = ($this->environment == 'production')
            ? $this->client_key_v2_production
            : $this->client_key_v2_sandbox;

        $this->log = new WC_Logger();

        // Payment listener/API hook
        add_action( 'woocommerce_api_wc_gateway_midtrans', array( &$this, 'midtrans_vtweb_response' ) );
        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( &$this, 'process_admin_options' ) ); 
        add_action( 'wp_enqueue_scripts', array( &$this, 'midtrans_scripts' ) );
        add_action( 'admin_print_scripts-woocommerce_page_woocommerce_settings', array( &$this, 'midtrans_admin_scripts' ));
        add_action( 'admin_print_scripts-woocommerce_page_wc-settings', array( &$this, 'midtrans_admin_scripts' ));
        add_action( 'valid-midtrans-web-request', array( $this, 'successful_request' ) );
        add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'receipt_page' ) );// Payment page hook
      }

      /**
       * Enqueue Javascripts
       */
      function midtrans_admin_scripts() {
        wp_enqueue_script( 'admin-midtrans', MT_PLUGIN_DIR . 'js/admin-scripts.js', array('jquery') );
      }

      function midtrans_scripts() {
        if( is_checkout() ) {
          // wp_enqueue_script( 'midtrans', 'https://api.veritrans.co.id/v2/assets/js/veritrans.min.js', array('jquery') );
          //wp_enqueue_script( 'midtrans-integration', MT_PLUGIN_DIR . 'js/script.js', array('jquery') );
          //wp_localize_script( 'midtrans-integration', 'wc_midtrans_client_key', $this->client_key );
        }
      }

      /**
       * Admin Panel Options
       * - Options for bits like 'title' and availability on a country-by-country basis
       *
       * @access public
       * @return void
       */
      public function admin_options() { ?>
        <h3><?php _e( 'Midtrans', 'woocommerce' ); ?></h3>
        <p><?php _e('Allows payments using Midtrans.', 'woocommerce' ); ?></p>
        <table class="form-table">
          <?php
            // Generate the HTML For the settings form.
            $this->generate_settings_html();
          ?>
        </table><!--/.form-table-->
        <?php
      }

      /**
       * Initialise Gateway Settings Form Fields
       * Method ini digunakan untuk mengatur halaman konfigurasi admin
       */
      function init_form_fields() {
        
        $v2_sandbox_key_url = 'https://dashboard.sandbox.midtrans.com/settings/config_info';
        $v2_production_key_url = 'https://dashboard.midtrans.com/settings/config_info';

        $this->form_fields = array(
          'enabled' => array(
            'title' => __( 'Enable/Disable', 'woocommerce' ),
            'type' => 'checkbox',
            'label' => __( 'Enable Midtrans Payment', 'woocommerce' ),
            'default' => 'yes'
          ),
          'title' => array(
            'title' => __( 'Title', 'woocommerce' ),
            'type' => 'text',
            'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce' ),
            'default' => __( 'Online Payment via Midtrans', 'woocommerce' ),
            'desc_tip'      => true,
          ),
          'description' => array(
            'title' => __( 'Customer Message', 'woocommerce' ),
            'type' => 'textarea',
            'description' => __( 'This controls the description which the user sees during checkout', 'woocommerce' ),
            'default' => ''
          ),
          'select_midtrans_environment' => array(
            'title' => __( 'Environment', 'woocommerce' ),
            'type' => 'select',
            'default' => 'sandbox',
            'description' => __( 'Select the Midtrans Environment', 'woocommerce' ),
            'options'   => array(
              'sandbox'    => __( 'Sandbox', 'woocommerce' ),
              'production'   => __( 'Production', 'woocommerce' ),
            ),
          ),
          'client_key_v2_sandbox' => array(
            'title' => __("Client Key", 'woocommerce'),
            'type' => 'text',
            'description' => sprintf(__('Input your <b>Sandbox</b> Midtrans Client Key. Get the key <a href="%s" target="_blank">here</a>', 'woocommerce' ),$v2_sandbox_key_url),
            'default' => '',
            'class' => 'sandbox_settings toggle-midtrans',
          ),
          'server_key_v2_sandbox' => array(
            'title' => __("Server Key", 'woocommerce'),
            'type' => 'text',
            'description' => sprintf(__('Input your <b>Sandbox</b> Midtrans Server Key. Get the key <a href="%s" target="_blank">here</a>', 'woocommerce' ),$v2_sandbox_key_url),
            'default' => '',
            'class' => 'sandbox_settings toggle-midtrans'
          ),
          'client_key_v2_production' => array(
            'title' => __("Client Key", 'woocommerce'),
            'type' => 'text',
            'description' => sprintf(__('Input your <b>Production</b> Midtrans Client Key. Get the key <a href="%s" target="_blank">here</a>', 'woocommerce' ),$v2_production_key_url),
            'default' => '',
            'class' => 'production_settings toggle-midtrans',
          ),
          'server_key_v2_production' => array(
            'title' => __("Server Key", 'woocommerce'),
            'type' => 'text',
            'description' => sprintf(__('Input your <b>Production</b> Midtrans Server Key. Get the key <a href="%s" target="_blank">here</a>', 'woocommerce' ),$v2_production_key_url),
            'default' => '',
            'class' => 'production_settings toggle-midtrans'
          ),
          // 'credit_card' => array(
          //   'title' => __( 'Enable credit card', 'woocommerce' ),
          //   'type' => 'checkbox',
          //   'label' => __( 'Enable Credit card?', 'woocommerce' ),
          //   'description' => __( 'Please contact us if you wish to enable this feature in the Production environment.', 'woocommerce' ),
          //   'default' => 'no'
          // ),
          // 'mandiri_clickpay' => array(
          //   'title' => __( 'Enable Mandiri Clickpay', 'woocommerce' ),
          //   'type' => 'checkbox',
          //   'label' => __( 'Enable Mandiri Clickpay?', 'woocommerce' ),
          //   'description' => __( 'Please contact us if you wish to enable this feature in the Production environment.', 'woocommerce' ),
          //   'default' => 'no'
          // ),
          // 'cimb_clicks' => array(
          //   'title' => __( 'Enable CIMB Clicks', 'woocommerce' ),
          //   'type' => 'checkbox',
          //   'label' => __( 'Enable CIMB Clicks?', 'woocommerce' ),
          //   'description' => __( 'Please contact us if you wish to enable this feature in the Production environment.', 'woocommerce' ),
          //   'default' => 'no'
          // ), 
          // 'bank_transfer' => array(
          //   'title' => __( 'Enable Bank Transfer', 'woocommerce' ),
          //   'type' => 'checkbox',
          //   'label' => __( 'Enable Bank Transfer?', 'woocommerce' ),
          //   'description' => __( 'Please contact us if you wish to enable this feature in the Production environment.', 'woocommerce' ),
          //   'default' => 'no'
          // ),
          // 'bri_epay' => array(
          //   'title' => __( 'Enable Bri e-pay', 'woocommerce' ),
          //   'type' => 'checkbox',
          //   'label' => __( 'Enable BRI e-pay?', 'woocommerce' ),
          //   'description' => __( 'Please contact us if you wish to enable this feature in the Production environment.', 'woocommerce' ),
          //   'default' => 'no'
          // ),
          // 'telkomsel_cash' => array(
          //   'title' => __( 'Enable T-cash', 'woocommerce' ),
          //   'type' => 'checkbox',
          //   'label' => __( 'Enable T-cash?', 'woocommerce' ),
          //   'description' => __( 'Please contact us if you wish to enable this feature in the Production environment.', 'woocommerce' ),
          //   'default' => 'no'
          // ),
          // 'xl_tunai' => array(
          //   'title' => __( 'Enable XL tunai', 'woocommerce' ),
          //   'type' => 'checkbox',
          //   'label' => __( 'Enable XL tunai?', 'woocommerce' ),
          //   'description' => __( 'Please contact us if you wish to enable this feature in the Production environment.', 'woocommerce' ),
          //   'default' => 'no'
          // ),
          // 'mandiri_bill' => array(
          //   'title' => __( 'Enable Mandiri Bill', 'woocommerce' ),
          //   'type' => 'checkbox',
          //   'label' => __( 'Enable Mandiri Bill?', 'woocommerce' ),
          //   'description' => __( 'Please contact us if you wish to enable this feature in the Production environment.', 'woocommerce' ),
          //   'default' => 'no'
          // ),
          // 'cstore' => array(
          //   'title' => __( 'Enable Indomaret', 'woocommerce' ),
          //   'type' => 'checkbox',
          //   'label' => __( 'Enable Indomaret?', 'woocommerce' ),
          //   'description' => __( 'Please contact us if you wish to enable this feature in the Production environment.', 'woocommerce' ),
          //   'default' => 'no'
          // ),
          // 'indosat_dompetku' => array(
          //   'title' => __( 'Enable Indosat Dompetku', 'woocommerce' ),
          //   'type' => 'checkbox',
          //   'label' => __( 'Enable Indosat Dompetku?', 'woocommerce' ),
          //   'description' => __( 'Please contact us if you wish to enable this feature in the Production environment.', 'woocommerce' ),
          //   'default' => 'no'
          // ),
          // 'mandiri_ecash' => array(
          //   'title' => __( 'Enable Mandiri Ecash', 'woocommerce' ),
          //   'type' => 'checkbox',
          //   'label' => __( 'Enable Mandiri Ecash?', 'woocommerce' ),
          //   'description' => __( 'Please contact us if you wish to enable this feature in the Production environment.', 'woocommerce' ),
          //   'default' => 'no'
          // ),
          'enable_3d_secure' => array(
            'title' => __( 'Enable 3D Secure', 'woocommerce' ),
            'type' => 'checkbox',
            'label' => __( 'Enable 3D Secure?', 'woocommerce' ),
            'description' => __( 'You must enable 3D Secure.
                Please contact us if you wish to disable this feature in the Production environment.', 'woocommerce' ),
            'default' => 'yes'
          ),
          'enable_savecard' => array(
            'title' => __( 'Enable Save Card', 'woocommerce' ),
            'type' => 'checkbox',
            'label' => __( 'Enable Save Card?', 'woocommerce' ),
            'description' => __( 'This will allow your customer to save their card on the payment popup, for faster payment flow on the following purchase', 'woocommerce' ),
            'default' => 'no'
          ),
          'custom_expiry' => array(
            'title' => __( 'Custom Expiry', 'woocommerce' ),
            'type' => 'text',
            'description' => __( 'This will allow you to set custom duration on how long the transaction available to be paid.<br> example: 45 minutes', 'woocommerce' ),
            'default' => 'disabled'
          )
          // 'enable_sanitization' => array(
          //   'title' => __( 'Enable Sanitization', 'woocommerce' ),
          //   'type' => 'checkbox',
          //   'label' => __( 'Enable Sanitization?', 'woocommerce' ),
          //   'default' => 'yes'
          // )
        );

        if (get_woocommerce_currency() != 'IDR')
        {
          $this->form_fields['to_idr_rate'] = array(
            'title' => __("Current Currency to IDR Rate", 'woocommerce'),
            'type' => 'text',
            'description' => 'The current currency to IDR rate',
            'default' => '10000',
          );
        }
      }

      /**
       * Call Midtrans SNAP API to return SNAP token
       * using parameter from cart & configuration
       */
      function get_snap_token( $order_id ){
        global $woocommerce;
        $order_items = array();
        $cart = $woocommerce->cart;

        $order = new WC_Order( $order_id );     
      
        Veritrans_Config::$isProduction = ($this->environment == 'production') ? true : false;
        Veritrans_Config::$serverKey = (Veritrans_Config::$isProduction) ? $this->server_key_v2_production : $this->server_key_v2_sandbox;     
        Veritrans_Config::$is3ds = ($this->enable_3d_secure == 'yes') ? true : false;
        Veritrans_Config::$isSanitized = true;
        
        $params = array(
          'transaction_details' => array(
            'order_id' => $order_id,
            'gross_amount' => 0,
          ),
          'vtweb' => array()
        );

        $enabled_payments = array();
        if ($this->enable_credit_card == 'yes'){
          $enabled_payments[] = 'credit_card';
        }
        if ($this->enable_mandiri_clickpay =='yes'){
          $enabled_payments[] = 'mandiri_clickpay';
        }
        if ($this->enable_cimb_clicks =='yes'){
          $enabled_payments[] = 'cimb_clicks';
        }
        if ($this->enable_permata_va =='yes'){
          $enabled_payments[] = 'bank_transfer';   
        }
        if ($this->enable_bri_epay =='yes'){
          $enabled_payments[] = 'bri_epay';
        }
        if ($this->enable_telkomsel_cash =='yes'){
          $enabled_payments[] = 'telkomsel_cash';
        }
        if ($this->enable_xl_tunai =='yes'){
          $enabled_payments[] = 'xl_tunai';
        }
        if ($this->enable_mandiri_bill =='yes'){
          $enabled_payments[] = 'echannel';
        }
        if ($this->enable_bbmmoney =='yes'){
          $enabled_payments[] = 'bbm_money';
        }
        if ($this->enable_indomaret =='yes'){
          $enabled_payments[] = 'cstore';
        }
        if ($this->enable_indosat_dompetku =='yes'){
          $enabled_payments[] = 'indosat_dompetku';
        }
        if ($this->enable_mandiri_ecash =='yes'){
          $enabled_payments[] = 'mandiri_ecash';
        }

        // $params['enabled_payments'] = $enabled_payments; // Disable customize payment method from config

        $customer_details = array();
        $customer_details['first_name'] = (string)$order->billing_first_name;
        $customer_details['last_name'] = (string)$order->billing_last_name;
        $customer_details['email'] = (string)$order->billing_email;
        $customer_details['phone'] = (string)$order->billing_phone;

        $billing_address = array();
        $billing_address['first_name'] = (string)$order->billing_first_name;
        $billing_address['last_name'] = (string)$order->billing_last_name;
        $billing_address['address'] = (string)$order->billing_address_1;
        $billing_address['city'] = (string)$order->billing_city;
        $billing_address['postal_code'] = (string)$order->billing_postcode;
        $billing_address['phone'] = (string)$order->billing_phone;
        $billing_address['country_code'] = (strlen($this->convert_country_code($order->billing_country) != 3 ) ? 'IDN' : $this->convert_country_code($order->billing_country) );

        $customer_details['billing_address'] = $billing_address;
        $customer_details['shipping_address'] = $billing_address;
        
        if ( isset ( $_POST['ship_to_different_address'] ) ) {
          $shipping_address = array();
          $shipping_address['first_name'] = (string)$order->shipping_first_name;
          $shipping_address['last_name'] = (string)$order->shipping_last_name;
          $shipping_address['address'] = (string)$order->shipping_address_1;
          $shipping_address['city'] = (string)$order->shipping_city;
          $shipping_address['postal_code'] = (string)$order->shipping_postcode;
          $shipping_address['phone'] = (string)$order->billing_phone;
          $shipping_address['country_code'] = (strlen($this->convert_country_code($order->shipping_country) != 3 ) ? 'IDN' : $this->convert_country_code($order->billing_country) );
          
          $customer_details['shipping_address'] = $shipping_address;
        }
        
        $params['customer_details'] = $customer_details;
        //error_log(print_r($params,true));

        $items = array();

        if( sizeof( $order->get_items() ) > 0 ) {
          foreach( $order->get_items() as $item ) {
            if ( $item['qty'] ) {
              $product = $order->get_product_from_item( $item );

              $midtrans_item = array();

              $midtrans_item['id']    = $item['product_id'];
              $midtrans_item['price']      = ceil($order->get_item_subtotal( $item, false ));
              $midtrans_item['quantity']   = $item['qty'];
              $midtrans_item['name'] = $item['name'];
              
              $items[] = $midtrans_item;
            }
          }
        }

        // Shipping fee
        if( $order->get_total_shipping() > 0 ) {
          $items[] = array(
            'id' => 'shippingfee',
            'price' => ceil($order->get_total_shipping()),
            'quantity' => 1,
            'name' => 'Shipping Fee',
          );
        }

        // Tax
        if( $order->get_total_tax() > 0 ) {
          $items[] = array(
            'id' => 'taxfee',
            'price' => ceil($order->get_total_tax()),
            'quantity' => 1,
            'name' => 'Tax',
          );
        }

        // Discount
        if ( $order->get_total_discount() > 0) {
          $items[] = array(
            'id' => 'totaldiscount',
            'price' => ceil($order->get_total_discount())  * -1,
            'quantity' => 1,
            'name' => 'Total Discount'
          );
        }

        // Fees
        if ( sizeof( $order->get_fees() ) > 0 ) {
          $fees = $order->get_fees();
          $i = 0;
          foreach( $fees as $item ) {
            $items[] = array(
              'id' => 'itemfee' . $i,
              'price' => ceil($item['line_total']),
              'quantity' => 1,
              'name' => $item['name'],
            );
            $i++;
          }
        }

        // sift through the entire item to ensure that currency conversion is applied
        if (get_woocommerce_currency() != 'IDR')
        {
          foreach ($items as &$item) {
            $item['price'] = $item['price'] * $this->to_idr_rate;
            $item['price'] = intval($item['price']);
          }

          unset($item);

          $params['transaction_details']['gross_amount'] *= $this->to_idr_rate;
        }

        $total_amount=0;
        // error_log('print r items[]' . print_r($items,true)); //debugan
        foreach ($items as $item) {
          $total_amount+=($item['price']*$item['quantity']);
          // error_log('|||| Per item[]' . print_r($item,true)); //debugan
        }

        // error_log('order get total = '.$order->get_total());
        // error_log('total amount = '.$total_amount);
        $params['transaction_details']['gross_amount'] = $total_amount;

        $params['item_details'] = $items;
        // add custom expiry params
        $custom_expiry_params = explode(" ",$this->custom_expiry);
        if ( !empty($custom_expiry_params[1]) && !empty($custom_expiry_params[0]) ){
          $time = time();
          $time += 30; // add 30 seconds to allow margin of error
          $params['expiry'] = array(
            'start_time' => date("Y-m-d H:i:s O",$time), 
            'unit' => $custom_expiry_params[1], 
            'duration'  => (int)$custom_expiry_params[0],
          );
        }
        // add savecard params
        if ($this->enable_savecard =='yes'){
          $params['user_id'] = crypt( (string)$customer_details['email'] , Veritrans_Config::$serverKey );
          $params['credit_card']['save_card'] = true;
        }
        
        $woocommerce->cart->empty_cart();
        // error_log(print_r($params,true)); //debug
        
        try {
          $snapToken = Veritrans_Snap::getSnapToken($params);
        } catch (Exception $e) {
          error_log("Error : ".$e->getMessage());
          echo 'Error exception: ',  $e->getMessage().". \n";
          echo 'Please redo transaction by using another cart'."\n";
          exit();
        }
        return $snapToken;
      }

      /**
       * Process the payment and return the result
       * Method ini akan dipanggil ketika customer akan melakukan pembayaran
       * Return value dari method ini adalah link yang akan digunakan untuk
       * me-redirect customer ke halaman pembayaran Midtrans
       */
      function process_payment( $order_id ) {
        global $woocommerce;
        
        //create the order object
        $order = new WC_Order( $order_id );

        //get SNAP token
        $snapToken = $this->get_snap_token($order_id);

        return array(
          'result'  => 'success',
          // 'redirect' => $this->charge_payment( $order_id )
          'redirect' => $order->get_checkout_payment_url( true )."&snap_token=".$snapToken
        );
      }

      /**
       * receipt_page
       * Method ini digunakan untuk menampilkan SNAP popout berdasarkan token SNAP
       */
      function receipt_page( $order_id ) {
        global $woocommerce;
        // $order_items = array();
        // $cart = $woocommerce->cart;
        $snapToken = $_GET['snap_token'];

          // TODO evaluate whether finish & error url need to be hardcoded
          $wp_base_url = home_url( '/' );
          $finish_url = $wp_base_url."?wc-api=WC_Gateway_Midtrans";
          $error_url = $wp_base_url."?wc-api=WC_Gateway_Midtrans";
          $snap_script_url = ($this->environment == 'production') ? "https://app.midtrans.com/snap/snap.js" : "https://app.sandbox.midtrans.com/snap/snap.js";

          // ## Print HTML
          ?>

          <a id="pay-button" title="Do Payment!" class="button alt">
            Proceed to Payment
          </a>
          
          <div id="payment-instruction" style="display:none;">
            <h3 class="alert alert-info"> Awaiting Your Payment </h3>
            <!-- <br> -->
            <p> Please complete your payment as instructed </p>
            <!-- <br> -->
            <a target="_blank" href="#" id="payment-instruction-btn" title="Do Payment!" class="button alt" >
              Payment Instruction
            </a>
          </div>

          <script type="text/javascript">
          document.addEventListener("DOMContentLoaded", function(event) { 
            // Safely load the snap.js
            function loadExtScript(src) {
              // Append script to doc
              var s = document.createElement("script");
              s.src = src;
              a = document.body.appendChild(s);
              a.setAttribute('data-client-key','<?php echo $this->client_key; ?>');
            }

            // Continously retry to execute SNAP popup if fail, with 1000ms delay between retry
            function execSnapCont(){
              var callbackTimer = setInterval(function() {
                var snapExecuted = false;
                try{
                  snap.pay("<?php echo $snapToken; ?>", 
                  {
                    skipOrderSummary : true,
                    onSuccess: function(result){
                      // console.log(result); // debug
                      window.location = "<?php echo $finish_url;?>&order_id="+result.order_id+"&status_code="+result.status_code+"&transaction_status="+result.transaction_status;
                    },
                    onPending: function(result){ // on pending, instead of redirection, show PDF instruction link
                      // console.log(result); // debug
                      
                      if (result.fraud_status == 'challenge'){ // if challenge redirect to finish
                        window.location = "<?php echo $finish_url;?>&order_id="+result.order_id+"&status_code="+result.status_code+"&transaction_status="+result.transaction_status;
                      }

                      // Show payment instruction and hide payment button
                      document.getElementById('payment-instruction-btn').href = result.pdf_url;
                      document.getElementById('pay-button').style.display = "none";
                      document.getElementById('payment-instruction').style.display = "block";
                      // if no pdf instruction, hide the btn
                      if(!result.hasOwnProperty("pdf_url")){
                        document.getElementById('payment-instruction-btn').style.display = "none";
                      }
                    },
                      onError: function(result){
                      // console.log(result); // debug
                      window.location = "<?php echo $error_url;?>&order_id="+result.order_id+"&status_code="+result.status_code+"&transaction_status="+result.transaction_status;
                    }
                  });
                  snapExecuted = true; // if SNAP popup executed, change flag to stop the retry.
                } catch (e){ 
                  console.log(e);
                  console.log("Snap s.goHome not ready yet... Retrying in 1000ms!");
                }
                if (snapExecuted) {
                  clearInterval(callbackTimer);
                }
              }, 1000);
            };

            console.log("Loading snap JS library now!");
            // Loading SNAP JS Library to the page    
            loadExtScript("<?php echo $snap_script_url;?>");
            console.log("Snap library is loaded now");
            // Call execSnapCont() 
            execSnapCont();
            /**
             */
            
            var payButton = document.getElementById("pay-button");
            payButton.onclick = function(){
              execSnapCont();
            };
          });
          </script>
          
          <?php
          // ## End of print HTML

      }

      // Response early with 200 OK status for Midtrans notification & handle HTTP GET
      public function earlyResponse(){
        if ( $_SERVER['REQUEST_METHOD'] == 'GET' ){
          die('This endpoint should not be opened using browser (HTTP GET). This endpoint is for Midtrans notification URL (HTTP POST)');
          exit();
        }

        ob_start();

        $input_source = "php://input";
        $raw_notification = json_decode(file_get_contents($input_source), true);
        echo "Notification Received: \n";
        print_r($raw_notification);
        
        header('Connection: close');
        header('Content-Length: '.ob_get_length());
        ob_end_flush();
        ob_flush();
        flush();
      }


      /**
       * Check for Midtrans Web Response
       * Method ini akan dipanggil untuk merespon notifikasi yang
       * diberikan oleh server Midtrans serta melakukan verifikasi
       * apakah notifikasi tersebut berasal dari Midtrans dan melakukan
       * konfirmasi transaksi pembayaran yang dilakukan customer
       *
       * update: sekaligus untuk menjadi finish/failed URL handler.
       * @access public
       * @return void
       */


      function midtrans_vtweb_response() {

        global $woocommerce;
        @ob_clean();

        global $woocommerce;
        $order = new WC_Order( $order_id );
        
        Veritrans_Config::$isProduction = ($this->environment == 'production') ? true : false;
        
        if ($this->environment == 'production') {
          Veritrans_Config::$serverKey = $this->server_key_v2_production;
        } else {
          Veritrans_Config::$serverKey = $this->server_key_v2_sandbox;
        }
        
        // check whether the request is GET or POST, 
        // if request == GET, request is for finish OR failed URL, then redirect to WooCommerce's order complete/failed
        // else if request == POST, request is for payment notification, then update the payment status
        if(!isset($_GET['order_id']) && !isset($_GET['id']) && !isset($_POST['response'])){    // Check if POST, then create new notification
          $this->earlyResponse();
          $midtrans_notification = new Veritrans_Notification();

          if (in_array($midtrans_notification->status_code, array(200, 201, 202))) {
            if ($order->get_order($midtrans_notification->order_id) == true) {
              do_action( "valid-midtrans-web-request", $midtrans_notification );
            }
          }
          exit;

        } else {    // else if GET, redirect to order complete/failed
          // error_log('status_code '. $_GET['status_code']); //debug
          // error_log('status_code '. $_GET['transaction_status']); //debug
          if( isset($_GET['order_id']) && isset($_GET['transaction_status']) && $_GET['status_code'] == 200)  //if capture or pending or challenge or settlement, redirect to order received page
          {
            $order_id = $_GET['order_id'];
            // error_log($this->get_return_url( $order )); //debug
            $order = new WC_Order( $order_id );
            wp_redirect($order->get_checkout_order_received_url());
          }else if( isset($_GET['order_id']) && isset($_GET['transaction_status']) && $_GET['status_code'] != 200)  //if deny, redirect to order checkout page again
          {
            wp_redirect( get_permalink( woocommerce_get_page_id( 'shop' ) ) );
          } else if( isset($_GET['order_id']) && !isset($_GET['transaction_status'])){ // if customer click "back" button, redirect to checkout page again
            wp_redirect( get_permalink( woocommerce_get_page_id( 'shop' ) ) );
          } else if ( isset($_POST['response']) ){ // if customer redirected from async payment
            $responses = json_decode( stripslashes($_POST['response']), true);
            $order = new WC_Order( $responses['order_id'] );
            if ( $responses['status_code'] == 200) { // async payment success
              wp_redirect($order->get_checkout_order_received_url());
            } else {
              wp_redirect( get_permalink( woocommerce_get_page_id( 'shop' ) ) );
            }
          } else if (isset($_GET['id']) ){ // if customer redirected form bca klikpay
            $midtrans_notification = Veritrans_Transaction::status($_GET['id']);
            $order_id = $midtrans_notification->order_id;
            if ($midtrans_notification->transaction_status == 'settlement'){
              $order = new WC_Order( $order_id );
              wp_redirect($order->get_checkout_order_received_url());              
            } else {
              wp_redirect( get_permalink( woocommerce_get_page_id( 'shop' ) ) );
            }
          } else {
            wp_redirect( get_permalink( woocommerce_get_page_id( 'shop' ) ) );
          }
        }

      }
 
      /**
       * Method ini akan dipanggil jika customer telah sukses melakukan
       * pembayaran. Method ini akan mengubah status order yang tersimpan
       * di back-end berdasarkan status pembayaran yang dilakukan customer.
       * 
       */

      function successful_request( $midtrans_notification ) {

        global $woocommerce;

        $order = new WC_Order( $midtrans_notification->order_id );
       // error_log(var_dump($order));
        if ($midtrans_notification->transaction_status == 'capture') {
          if ($midtrans_notification->fraud_status == 'accept') {
            $order->payment_complete();
          }
          else if ($midtrans_notification->fraud_status == 'challenge') {
            $order->update_status('on-hold');
          }
        }
        else if ($midtrans_notification->transaction_status == 'cancel') {
          $order->update_status('cancelled');
        }
        else if ($midtrans_notification->transaction_status == 'deny') {
          $order->update_status('failed');
        }
        else if ($midtrans_notification->transaction_status == 'settlement') {
          if($midtrans_notification->payment_type != 'credit_card'){
            $order->payment_complete();
          }
        }
        else if ($midtrans_notification->transaction_status == 'pending') {
          $order->update_status('on-hold');
        }

        exit;
      }

      /**
       * Convert 2 digits coundry code to 3 digit country code
       *
       * @param String $country_code Country code which will be converted
       */
      public function convert_country_code( $country_code ) {

        // 3 digits country codes
        $cc_three = array( 'AF' => 'AFG', 'AX' => 'ALA', 'AL' => 'ALB', 'DZ' => 'DZA', 'AD' => 'AND', 'AO' => 'AGO', 'AI' => 'AIA', 'AQ' => 'ATA', 'AG' => 'ATG', 'AR' => 'ARG', 'AM' => 'ARM', 'AW' => 'ABW', 'AU' => 'AUS', 'AT' => 'AUT', 'AZ' => 'AZE', 'BS' => 'BHS', 'BH' => 'BHR', 'BD' => 'BGD', 'BB' => 'BRB', 'BY' => 'BLR', 'BE' => 'BEL', 'PW' => 'PLW', 'BZ' => 'BLZ', 'BJ' => 'BEN', 'BM' => 'BMU', 'BT' => 'BTN', 'BO' => 'BOL', 'BQ' => 'BES', 'BA' => 'BIH', 'BW' => 'BWA', 'BV' => 'BVT', 'BR' => 'BRA', 'IO' => 'IOT', 'VG' => 'VGB', 'BN' => 'BRN', 'BG' => 'BGR', 'BF' => 'BFA', 'BI' => 'BDI', 'KH' => 'KHM', 'CM' => 'CMR', 'CA' => 'CAN', 'CV' => 'CPV', 'KY' => 'CYM', 'CF' => 'CAF', 'TD' => 'TCD', 'CL' => 'CHL', 'CN' => 'CHN', 'CX' => 'CXR', 'CC' => 'CCK', 'CO' => 'COL', 'KM' => 'COM', 'CG' => 'COG', 'CD' => 'COD', 'CK' => 'COK', 'CR' => 'CRI', 'HR' => 'HRV', 'CU' => 'CUB', 'CW' => 'CUW', 'CY' => 'CYP', 'CZ' => 'CZE', 'DK' => 'DNK', 'DJ' => 'DJI', 'DM' => 'DMA', 'DO' => 'DOM', 'EC' => 'ECU', 'EG' => 'EGY', 'SV' => 'SLV', 'GQ' => 'GNQ', 'ER' => 'ERI', 'EE' => 'EST', 'ET' => 'ETH', 'FK' => 'FLK', 'FO' => 'FRO', 'FJ' => 'FJI', 'FI' => 'FIN', 'FR' => 'FRA', 'GF' => 'GUF', 'PF' => 'PYF', 'TF' => 'ATF', 'GA' => 'GAB', 'GM' => 'GMB', 'GE' => 'GEO', 'DE' => 'DEU', 'GH' => 'GHA', 'GI' => 'GIB', 'GR' => 'GRC', 'GL' => 'GRL', 'GD' => 'GRD', 'GP' => 'GLP', 'GT' => 'GTM', 'GG' => 'GGY', 'GN' => 'GIN', 'GW' => 'GNB', 'GY' => 'GUY', 'HT' => 'HTI', 'HM' => 'HMD', 'HN' => 'HND', 'HK' => 'HKG', 'HU' => 'HUN', 'IS' => 'ISL', 'IN' => 'IND', 'ID' => 'IDN', 'IR' => 'RIN', 'IQ' => 'IRQ', 'IE' => 'IRL', 'IM' => 'IMN', 'IL' => 'ISR', 'IT' => 'ITA', 'CI' => 'CIV', 'JM' => 'JAM', 'JP' => 'JPN', 'JE' => 'JEY', 'JO' => 'JOR', 'KZ' => 'KAZ', 'KE' => 'KEN', 'KI' => 'KIR', 'KW' => 'KWT', 'KG' => 'KGZ', 'LA' => 'LAO', 'LV' => 'LVA', 'LB' => 'LBN', 'LS' => 'LSO', 'LR' => 'LBR', 'LY' => 'LBY', 'LI' => 'LIE', 'LT' => 'LTU', 'LU' => 'LUX', 'MO' => 'MAC', 'MK' => 'MKD', 'MG' => 'MDG', 'MW' => 'MWI', 'MY' => 'MYS', 'MV' => 'MDV', 'ML' => 'MLI', 'MT' => 'MLT', 'MH' => 'MHL', 'MQ' => 'MTQ', 'MR' => 'MRT', 'MU' => 'MUS', 'YT' => 'MYT', 'MX' => 'MEX', 'FM' => 'FSM', 'MD' => 'MDA', 'MC' => 'MCO', 'MN' => 'MNG', 'ME' => 'MNE', 'MS' => 'MSR', 'MA' => 'MAR', 'MZ' => 'MOZ', 'MM' => 'MMR', 'NA' => 'NAM', 'NR' => 'NRU', 'NP' => 'NPL', 'NL' => 'NLD', 'AN' => 'ANT', 'NC' => 'NCL', 'NZ' => 'NZL', 'NI' => 'NIC', 'NE' => 'NER', 'NG' => 'NGA', 'NU' => 'NIU', 'NF' => 'NFK', 'KP' => 'MNP', 'NO' => 'NOR', 'OM' => 'OMN', 'PK' => 'PAK', 'PS' => 'PSE', 'PA' => 'PAN', 'PG' => 'PNG', 'PY' => 'PRY', 'PE' => 'PER', 'PH' => 'PHL', 'PN' => 'PCN', 'PL' => 'POL', 'PT' => 'PRT', 'QA' => 'QAT', 'RE' => 'REU', 'RO' => 'SHN', 'RU' => 'RUS', 'RW' => 'EWA', 'BL' => 'BLM', 'SH' => 'SHN', 'KN' => 'KNA', 'LC' => 'LCA', 'MF' => 'MAF', 'SX' => 'SXM', 'PM' => 'SPM', 'VC' => 'VCT', 'SM' => 'SMR', 'ST' => 'STP', 'SA' => 'SAU', 'SN' => 'SEN', 'RS' => 'SRB', 'SC' => 'SYC', 'SL' => 'SLE', 'SG' => 'SGP', 'SK' => 'SVK', 'SI' => 'SVN', 'SB' => 'SLB', 'SO' => 'SOM', 'ZA' => 'ZAF', 'GS' => 'SGS', 'KR' => 'KOR', 'SS' => 'SSD', 'ES' => 'ESP', 'LK' => 'LKA', 'SD' => 'SDN', 'SR' => 'SUR', 'SJ' => 'SJM', 'SZ' => 'SWZ', 'SE' => 'SWE', 'CH' => 'CHE', 'SY' => 'SYR', 'TW' => 'TWN', 'TJ' => 'TJK', 'TZ' => 'TZA', 'TH' => 'THA', 'TL' => 'TLS', 'TG' => 'TGO', 'TK' => 'TKL', 'TO' => 'TON', 'TT' => 'TTO', 'TN' => 'TUN', 'TR' => 'TUR', 'TM' => 'TKM', 'TC' => 'TCA', 'TV' => 'TUV', 'UG' => 'UGA', 'UA' => 'UKR', 'AE' => 'ARE', 'GB' => 'GBR', 'US' => 'USA', 'UY' => 'URY', 'UZ' => 'UZB', 'VU' => 'VUT', 'VA' => 'VAT', 'VE' => 'VEN', 'VN' => 'VNM', 'WF' => 'WLF', 'EH' => 'ESH', 'WS' => 'WSM', 'YE' => 'YEM', 'ZM' => 'ZMB', 'ZW' => 'ZWE' );

        // Check if country code exists
        if( isset( $cc_three[ $country_code ] ) && $cc_three[ $country_code ] != '' ) {
          $country_code = $cc_three[ $country_code ];
        }
        else{
         $country_code = ''; 
        }

        return $country_code;
      }
    }
