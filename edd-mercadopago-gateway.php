<?php
/*
Plugin Name: Easy Digital Downloads - MercadoPago Payment Gateway
Plugin URL: http://easydigitaldownloads.com/extension/mercadopago
Description: Adds MercadoPago Gateway ( Argentina & Brazil )
Version: 1.3
Author: Matt Varone
Author URI: http://www.mattvarone.com
*/

/**
* EDD MercadoPago Gateway
*
* @package MercadoPago Gateway
* @author Matt Varone
*/

if ( ! class_exists( 'EDD_MercadoPago_Gateway' ) )
{

    class EDD_MercadoPago_Gateway
    {

        /**
        * Path to the plugin dir
        *
        * @since    1.0
        */

        private $plugin_path;


        /**
        * IPN Listener URI
        *
        * @since    1.0
        */

        private $listener_uri = 'https://api.mercadolibre.com/collections/notifications/';


        /**
        * EDD MercadoPago Gateway
        *
        * Waits for plugins_loaded and launches first method.
        *
        * @return   void
        * @since    1.0
        */

        function __construct()
        {
            // wait and fire plugins_loaded method
            add_action( 'plugins_loaded', array( &$this, 'plugins_loaded' ) );
        }


        /**
        * EDD MercadoPago Gateway
        *
        * Internationalization, payment process and init method.
        *
        * @return   void
        * @since    1.0
        */

        function plugins_loaded()
        {
            // set plugin path
            $this->plugin_path = plugin_dir_path( __FILE__ );

            // load internationalization
            load_plugin_textdomain( 'edd-mercadopago-gateway', false, $this->plugin_path . '/lan' );

            // process payments
            add_action( 'edd_gateway_mercadopago-argentina', array( &$this, 'process_payment' ) );
            add_action( 'edd_gateway_mercadopago-brazil', array( &$this, 'process_payment' ) );

            // fire init method
            add_action( 'init', array( &$this, 'init' ), -1 );

        }


        /**
        * Init
        *
        * Sets the necessary filters and action.
        *
        * @return   void
        * @since    1.0
        */

        function init()
        {

            // set filters
            add_filter( 'edd_payment_gateways', array( &$this, 'register_gateway' ) );
            add_filter( 'edd_settings_gateways', array( &$this, 'add_settings' ) );
            add_filter( 'edd_currencies', array( &$this, 'add_ars_pesos' ) );
            add_filter( 'edd_ars_currency_filter_before', array( &$this, 'currency_filter_before' ), 1, 3 );
            add_filter( 'edd_ars_currency_filter_after', array( &$this, 'currency_filter_after' ), 1, 3 );
            add_filter( 'edd_payment_confirm_mercadopago-argentina', array( &$this, 'payment_confirm' ) );
            add_filter( 'edd_payment_confirm_mercadopago-brazil', array( &$this, 'payment_confirm' ) );

            // set actions
            add_action( 'edd_mercadopago-argentina_cc_form', array( &$this, 'cc_form' ) );
            add_action( 'edd_mercadopago-brazil_cc_form', array( &$this, 'cc_form' ) );

            $this->listen_for_mercadopago_ipn();

            if( class_exists( 'EDD_License' ) && is_admin() ) {
                $license = new EDD_License( __FILE__, 'MercadoPago', '1.3', 'Pippin Williamson' );
            }
        }


        /**
        * Register Gateway
        *
        * Registers the MercadoPago gateway.
        *
        * @return   array
        * @since    1.0
        */

        function register_gateway( $gateways )
        {
            $gateways['mercadopago-argentina'] = array( 'admin_label' => 'MercadoPago ( Argentina )', 'checkout_label' => 'MercadoPago Argentina' );
            $gateways['mercadopago-brazil'] = array( 'admin_label' => 'MercadoPago ( Brazil )', 'checkout_label' => 'MercadoPago Brazil' );
            return $gateways;
        }


        /**
        * Add Settings
        *
        * Adds the MercadoPago gateway settings.
        *
        * @return   array
        * @since    1.0
        */

        function add_settings( $settings )
        {
            // Labels
            $in_action = __( 'get it here', 'edd-mercadopago-gateway' );
            $in_ipn = __( 'IPN settings', 'edd-mercadopago-gateway' );

            // Credtendial Links
            $credentials_link_arg = sprintf( '<a href="https://www.mercadopago.com/mla/herramientas/aplicaciones">%s</a>', $in_action );
            $credentials_link_bra = sprintf( '<a href="https://www.mercadopago.com/mlb/herramientas/aplicaciones">%s</a>', $in_action );

            // IPN settings links
            $ipn_settings_link_arg = sprintf('<a href="https://www.mercadopago.com/mla/herramientas/notificaciones">%s</a>', $in_ipn );
            $ipn_settings_link_bra = sprintf('<a href="https://www.mercadopago.com/mlb/herramientas/notificaciones">%s</a>', $in_ipn );

            $gateway_settings = array(

                // MERCADOPAGO ARGENTINA
                array(
                    'id' => 'mercadopago_argentina_settings',
                    'name' => '<strong>' . __( 'MercadoPago Argentina Settings', 'edd-mercadopago-gateway' ) . '</strong>',
                    'desc' => __( 'Configure your MercadoPago Argentina Settings', 'edd-mercadopago-gateway' ),
                    'type' => 'header'
                ),
                array(
                    'id' => 'mercadopago_argentina_currency',
                    'name' => __( 'IMPORTANT', 'edd-mercadopago-gateway' ),
                    'desc' => '<strong>' . __( 'MercadoPago Argentina only supports payments in US Dollars ($) and Argentian Pesos ($). This Gateway does not support the test mode account, all payments will be processed live.', 'edd-mercadopago-gateway' ) . '</strong>',
                    'type' => 'mercadopago_notes',
                ),
                array(
                    'id' => 'mercadopago_argentina_ipn',
                    'name' => __( 'IPN', 'edd-mercadopago-gateway' ),
                    'desc' => sprintf( __( 'Set your MercadoPago Argentina %s to notify this URL: %s', 'edd-mercadopago-gateway' ), $ipn_settings_link_arg, '<br/> <textarea type="text" class="large-text" disabled="disabled">'.get_site_url().'/edd-listener-mercadopago-argentina/</textarea>' ),
                    'type' => 'mercadopago_notes',
                ),
                array(
                    'id' => 'mercadopago_argentina_client_id',
                    'name' => __( 'Client ID', 'edd-mercadopago-gateway' ),
                    'desc' => sprintf( __( 'Enter your MercadoPago Argentina client ID ( %s )', 'edd-mercadopago-gateway' ), $credentials_link_arg ),
                    'type' => 'text',
                    'size' => 'small'
                ),
                array(
                    'id' => 'mercadopago_argentina_client_secret',
                    'name' => __( 'Client Secret Key', 'edd-mercadopago-gateway' ),
                    'desc' => sprintf( __( 'Enter your MercadoPago Argentina client secret key ( %s )', 'edd-mercadopago-gateway' ), $credentials_link_arg ),
                    'type' => 'text',
                    'size' => 'regular'
                ),
                array(
                    'id' => 'mercadopago_argentina_excluded_methods',
                    'name' => __( 'Excluded Payment Methods', 'edd-mercadopago-gateway' ),
                    'desc' => __( 'Select the Payment methods to exclude', 'edd-mercadopago-gateway' ),
                    'type' => 'multicheck',
                    'options' => array(
                        'visa'          => __( 'Visa', 'edd-mercadopago-gateway' ),
                        'amex'          => __( 'American Express', 'edd-mercadopago-gateway' ),
                        'master'        => __( 'Mastercard', 'edd-mercadopago-gateway' ),
                        'tarshop'       => __( 'Tarjeta Shopping', 'edd-mercadopago-gateway' ),
                        'argencard'     => __( 'Argencard', 'edd-mercadopago-gateway' ),
                        'naranja'       => __( 'Naranja', 'edd-mercadopago-gateway' ),
                        'cabal'         => __( 'Cabal', 'edd-mercadopago-gateway' ),
                        'banelco'       => __( 'Banelco', 'edd-mercadopago-gateway' ),
                        'bapropagos'    => __( 'Provincia Pagos', 'edd-mercadopago-gateway' ),
                        'pagofacil'     => __( 'Pago F&aacute;cil', 'edd-mercadopago-gateway' ),
                        'rapipago'      => __( 'Rapipago', 'edd-mercadopago-gateway' ),
                        'redlink'       => __( 'RedLink', 'edd-mercadopago-gateway' )
                        )
                ),
                array(
                    'id'    => 'mercadopago_argentina_excluded_types',
                    'name'  => __( 'Excluded Payment Types', 'edd-mercadopago-gateway' ),
                    'desc'  => __( 'Select the Payment types to exclude', 'edd-mercadopago-gateway' ),
                    'type'  => 'multicheck',
                    'options' => array(
                        'credit_card'   => __( 'Credit Card', 'edd-mercadopago-gateway' ),
                        'ticket'        => __( 'Ticket', 'edd-mercadopago-gateway' ),
                        'atm'           => __( 'ATM', 'edd-mercadopago-gateway' )
                    )
                ),
                array(
                    'id' => 'mercadopago_argentina_installments',
                    'name' => __( 'Installments', 'edd-mercadopago-gateway' ),
                    'desc' => __( 'The maximum amount of installments to accept ( 1 to 24 )', 'edd-mercadopago-gateway' ),
                    'type' => 'text',
                    'size' => 'small',
                    'std'  => '24',
                ),

                // MERCADOPAGO BRAZIL
                array(
                    'id' => 'mercadopago_brazil_settings',
                    'name' => '<strong>' . __( 'MercadoPago Brazil Settings', 'edd-mercadopago-gateway' ) . '</strong>',
                    'desc' => __( 'Configure your MercadoPago Brazil Settings', 'edd-mercadopago-gateway' ),
                    'type' => 'header'
                ),
                array(
                    'id' => 'mercadopago_brazil_currency',
                    'name' => __( 'IMPORTANT', 'edd-mercadopago-gateway' ),
                    'desc' => '<strong>' . __( 'MercadoPago Brazil only supports payments in Brazilian Real ($). This Gateway does not support the test mode account, all payments will be processed live.', 'edd-mercadopago-gateway' ) . '</strong>',
                    'type' => 'mercadopago_notes',
                ),
                array(
                    'id' => 'mercadopago_brazil_ipn',
                    'name' => __( 'IPN', 'edd-mercadopago-gateway' ),
                    'desc' => sprintf( __( 'Set your MercadoPago Brazil %s to notify this URL: %s', 'edd-mercadopago-gateway' ), $ipn_settings_link_bra, '<br/> <textarea type="text" class="large-text" disabled="disabled">'.get_site_url().'/edd-listener-mercadopago-brazil/</textarea>' ),
                    'type' => 'mercadopago_notes',
                ),
                array(
                    'id' => 'mercadopago_brazil_client_id',
                    'name' => __( 'Client ID', 'edd-mercadopago-gateway' ),
                    'desc' => sprintf( __( 'Enter your MercadoPago Brazil client ID ( %s )', 'edd-mercadopago-gateway' ), $credentials_link_bra ),
                    'type' => 'text',
                    'size' => 'small'
                ),
                array(
                    'id' => 'mercadopago_brazil_client_secret',
                    'name' => __( 'Client Secret Key', 'edd-mercadopago-gateway' ),
                    'desc' => sprintf( __( 'Enter your MercadoPago Brazil client secret key ( %s )', 'edd-mercadopago-gateway' ), $credentials_link_bra ),
                    'type' => 'text',
                    'size' => 'regular'
                ),
                array(
                    'id' => 'mercadopago_brazil_excluded_methods',
                    'name' => __( 'Excluded Payment Methods', 'edd-mercadopago-gateway' ),
                    'desc' => __( 'Select the Payment methods to exclude', 'edd-mercadopago-gateway' ),
                    'type' => 'multicheck',
                    'options' => array(
                        'visa'          => __( 'Visa', 'edd-mercadopago-gateway' ),
                        'amex'          => __( 'American Express', 'edd-mercadopago-gateway' ),
                        'master'        => __( 'Mastercard', 'edd-mercadopago-gateway' ),
                        'aura'          => __( 'Aura', 'edd-mercadopago-gateway' ),
                        'diners'        => __( 'Diners', 'edd-mercadopago-gateway' ),
                        'hipercard'     => __( 'Hipercard', 'edd-mercadopago-gateway' ),
                        'elo'           => __( 'Elo', 'edd-mercadopago-gateway' ),
                        'bolbradesco'   => __( 'Boleto', 'edd-mercadopago-gateway' ),
                        'bbrasil'       => __( 'Banco do Brasil', 'edd-mercadopago-gateway' ),
                        'bradesco'      => __( 'Bradesco', 'edd-mercadopago-gateway' ),
                        )
                ),
                array(
                    'id'    => 'mercadopago_brazil_excluded_types',
                    'name'  => __( 'Excluded Payment Types', 'edd-mercadopago-gateway' ),
                    'desc'  => __( 'Select the Payment types to exclude', 'edd-mercadopago-gateway' ),
                    'type'  => 'multicheck',
                    'options' => array(
                        'credit_card'   => __( 'Credit Card', 'edd-mercadopago-gateway' ),
                        'ticket'        => __( 'Ticket', 'edd-mercadopago-gateway' ),
                        'atm'           => __( 'ATM', 'edd-mercadopago-gateway' )
                    )
                ),
                array(
                    'id' => 'mercadopago_brazil_installments',
                    'name' => __( 'Installments', 'edd-mercadopago-gateway' ),
                    'desc' => __( 'The maximum amount of installments to accept ( 1 to 24 )', 'edd-mercadopago-gateway' ),
                    'type' => 'text',
                    'size' => 'small',
                    'std'  => '24',
                ),
            );

            return array_merge( $settings, apply_filters( 'edd_mercadopago_gateway_settings', $gateway_settings ) );
        }


        /**
        * Add ARS Pesos
        *
        * Adds ARS Pesos Currency.
        *
        * @return   array
        * @since    1.0
        */

        function add_ars_pesos( $currencies )
        {
            $currencies['ARS'] = __( 'Argentian Pesos (&#36;)', 'edd-mercadopago-gateway' );
            return $currencies;
        }


        /**
        * Currency Filter Before
        *
        * Filters the currency position.
        *
        * @return   string
        * @since    1.0
        */

        function currency_filter_before( $formatted, $currency, $price )
        {
            if ( $currency == 'ARS' ) {
                $formatted = '&#36; ' . $price;
            }
            return $formatted;
        }


        /**
        * Currency Filter After
        *
        * Filters the currency position.
        *
        * @return   string
        * @since    1.0
        */

        function currency_filter_after( $formatted, $currency, $price )
        {
            if ( $currency == 'ARS' ) {
                $formatted = $price . ' &#36;';
            }
            return $formatted;
        }


        /**
        * Credit Card Form
        *
        * Registers the MercadoPago gateway.
        *
        * @return   null
        * @since    1.0
        */

        function cc_form() {
            // we only register the action so that the default CC form is not shown
        }


        /**
        * Get Credentials
        *
        * Gets the MercadoPago gateway credentials.
        *
        * @return   array
        * @since    1.0
        */

        function get_credentials( $country = 'argentina' )
        {
            global $edd_options;

            return array(
                'id'     => isset( $edd_options['mercadopago_' . $country . '_client_id'] ) ? $edd_options['mercadopago_' . $country . '_client_id'] : null,
                'secret' => isset( $edd_options['mercadopago_' . $country . '_client_secret'] ) ? $edd_options['mercadopago_' . $country . '_client_secret'] : null
            );

        }


        /**
        * Get Excluded Payment Methods
        *
        * Gets the MercadoPago gateway excluded payment methods.
        *
        * @return   array
        * @since    1.0
        */

        function get_excluded_payment_methods( $country = 'argentina' )
        {
            global $edd_options;

            return isset( $edd_options['mercadopago_' . $country . '_excluded_methods'] ) ? $edd_options['mercadopago_' . $country . '_excluded_methods'] : array();
        }


        /**
        * Get Excluded Payment Types
        *
        * Gets the MercadoPago gateway excluded payment types.
        *
        * @return   array
        * @since    1.0
        */

        function get_excluded_payment_types( $country = 'argentina' )
        {
            global $edd_options;

            return isset( $edd_options['mercadopago_' . $country . '_excluded_types'] ) ? $edd_options['mercadopago_' . $country . '_excluded_types'] : array();
        }


        /**
        * Get Installments
        *
        * Gets the installments.
        *
        * @return   integer
        * @since    1.0
        */

        function get_installments( $country = 'argentina' )
        {
            global $edd_options;

            return isset( $edd_options['mercadopago_' . $country . '_installments'] ) && ( $edd_options['mercadopago_' . $country . '_installments'] <= 24 ) ? ( int ) $edd_options['mercadopago_' . $country . '_installments'] : 24;
        }


        /**
        * Get Currency
        *
        * Gets the selected currency.
        *
        * @return   string
        * @since    1.0
        */

        function get_currency( $country = 'argentina' )
        {
            global $edd_options;

            $supported = ( $country == 'argentina' ) ? array( 'ARS', 'USD' ) : array( 'BRL' );

            return isset( $edd_options['currency'] ) && trim( $edd_options['currency'] ) != "" && in_array( $edd_options['currency'], $supported ) ? $edd_options['currency'] : $supported[0];

        }


        /**
        * Load MercadoPago SDK
        *
        * Loads the necessary MercadoPago SDK files.
        *
        * @return   void
        * @since    1.0
        */

        function load_mercadopago_sdk()
        {
            // Services
            require_once( $this->plugin_path . 'lib/mercadopago/src/services/checkoutService.php' );
            require_once( $this->plugin_path . 'lib/mercadopago/src/services/authService.php' );

            // Classes
            require_once( $this->plugin_path . 'lib/mercadopago/src/classes/accessData.php' );
            require_once( $this->plugin_path . 'lib/mercadopago/src/classes/checkoutPreferenceData.php' );
            require_once( $this->plugin_path . 'lib/mercadopago/src/classes/checkoutPreferenceDataItems.php' );
            require_once( $this->plugin_path . 'lib/mercadopago/src/classes/checkoutPreferenceDataPayer.php' );
            require_once( $this->plugin_path . 'lib/mercadopago/src/classes/checkoutPreferenceDataBackUrls.php' );
            require_once( $this->plugin_path . 'lib/mercadopago/src/classes/checkoutPreferenceDataPaymentMethods.php' );
            require_once( $this->plugin_path . 'lib/mercadopago/src/classes/checkoutPreferenceDataExcludedPaymentMethods.php' );
            require_once( $this->plugin_path . 'lib/mercadopago/src/classes/checkoutPreferenceDataExcludedPaymentTypes.php' );
            require_once( $this->plugin_path . 'lib/mercadopago/src/classes/checkoutPreference.php' );
            require_once( $this->plugin_path . 'lib/mercadopago/src/classes/checkoutPreferenceItems.php' );
            require_once( $this->plugin_path . 'lib/mercadopago/src/classes/checkoutPreferencePayer.php' );
            require_once( $this->plugin_path . 'lib/mercadopago/src/classes/checkoutPreferenceBackUrls.php' );
            require_once( $this->plugin_path . 'lib/mercadopago/src/classes/checkoutPreferencePaymentMethods.php' );
            require_once( $this->plugin_path . 'lib/mercadopago/src/classes/checkoutPreferenceExcludedPaymentMethods.php' );
            require_once( $this->plugin_path . 'lib/mercadopago/src/classes/checkoutPreferenceExcludedPaymentTypes.php' );

        }


        /**
        * Payment Confirm
        *
        * Checks for payment response.
        *
        * @return   void
        * @since    1.0
        */

        function payment_confirm( $content )
        {
            global $edd_options;

            // check if there is a confirmation arg
            if ( ! isset( $_GET['payment-confirmation'] ) || ( $_GET['payment-confirmation'] != 'mercadopago' ) ) {
                // return regular content
                return $content;
            }

            // check if it's pending mode
            if ( isset( $_GET['payment-pending'] ) && $_GET['payment-pending'] == 'true' ) {
                // generate pending mode output
                ob_start();
                do_action( 'edd_mercadopago_before_pending' );
                ?>
                <p> <?php _e( 'Thanks for completing the checkout. <strong>Your Payment is in pending mode</strong>. Please complete the process with MercadoPago to access your purchased files.', 'edd-mercadopago-gateway' ); ?> </p>
                <?php
                do_action( 'edd_mercadopago_after_pending' );
                return ob_get_clean();
            }

            // return succesful confirmation
            return $content;
        }

        /**
        * Listen for MercadoPago IPN
        *
        * MercadoPago instant payment notifications.
        *
        * @return   void
        * @since    1.0
        */

        function listen_for_mercadopago_ipn()
        {
            global $edd_options;

            // check for the edd-listener in the URI request
            if ( strpos( $_SERVER['REQUEST_URI'], 'edd-listener-mercadopago' ) === false )
            return;

            // get the IPN country
            if ( strpos( $_SERVER['REQUEST_URI'], 'edd-listener-mercadopago-brazil' ) !== false )
                $country = 'brazil';
            else
                $country = 'argentina';

            // check for incoming order id
            if ( ! isset( $_REQUEST['id'] ) || trim( $_REQUEST['id'] ) == "" )
            return;

            // get credentials
            $credentials = $this->get_credentials( $country );

            // check credentials have been set
            if ( is_null( $credentials['id'] ) || is_null( $credentials['secret'] ) )
            return;

            // require MercadoPago files
            $this->load_mercadopago_sdk();

            // try getting the access key
            try {

                $authService = new AuthService();
                $accessData = $authService->create_access_data( $credentials['id'], $credentials['secret'] );

                // verify data
                if ( gettype( $accessData ) == 'string' ) {
                    throw new exception( $accessData );
                }

            } catch ( Exception $e ) {
                wp_mail( get_bloginfo( 'admin_email' ), sprintf( __( 'MercadoPago %s Auth Service Error', 'edd-mercadopago-gateway' ), ucwords( $country ) ), $e->getMessage() );
                return;
            }

            // try to verify the notification
            try {

                // verify this notification
                $api_response = wp_remote_get( add_query_arg( 'access_token', $accessData->getAccessToken() , $this->listener_uri . $_REQUEST['id'] ) );

                // get json body
                $json = wp_remote_retrieve_body( $api_response );

                // verify there is a response
                if ( empty( $json ) ) {
                    return;
                }

                // decode json
                $json = json_decode( $json );

                // check status
                if ( ! isset( $json->collection->status ) || $json->collection->status != 'approved'  ) {
                    throw new exception( $json->message );
                }

                // check payment internal id
                if ( ! isset( $json->collection->external_reference ) ) {
                    throw new exception( var_export($json, true) );
                }

                // update succesful payment
                edd_update_payment_status( $json->collection->external_reference, 'publish' );

                // return status 200 OK
                die(1);

            } catch ( Exception $e ) {
                wp_mail( get_bloginfo( 'admin_email' ), sprintf( __( 'MercadoPago %s IPN Error', 'edd-mercadopago-gateway' ), ucwords( $country ) ), $e->getMessage() );
                die();
            }

        }


        /**
        * Process Payment
        *
        * Process payments trough the MercadoPago gateway.
        *
        * @return   void
        * @since    1.0
        */

        function process_payment( $purchase_data )
        {
            global $edd_options;

            // check there is a gateway name
            if ( ! isset( $purchase_data['post_data']['edd-gateway'] ) )
            return;

            // get the gateway country
            $country = str_replace( 'mercadopago-', '', $purchase_data['post_data']['edd-gateway'] );

            // get credentials
            $credentials = $this->get_credentials( $country );

            // check credentials have been set
            if ( is_null( $credentials['id'] ) || is_null( $credentials['secret'] ) ) {
              edd_set_error( 0, __( 'Please enter your MercadoPago Client ID and Secret Key in settings', 'edd-mercadopago-gateway' ) );
              edd_send_back_to_checkout( '?payment-mode=' . $purchase_data['post_data']['edd-gateway'] );
            }

            // get payment
            $payment_data = array(
                'price'         => $purchase_data['price'],
                'date'          => $purchase_data['date'],
                'user_email'    => $purchase_data['user_email'],
                'purchase_key'  => $purchase_data['purchase_key'],
                'currency'      => $edd_options['currency'],
                'downloads'     => $purchase_data['downloads'],
                'user_info'     => $purchase_data['user_info'],
                'cart_details'  => $purchase_data['cart_details'],
                'status'        => 'pending'
            );

            // insert pending payment
            $payment = edd_insert_payment( $payment_data );

            if ( ! $payment ) {
                // problems? send back
                edd_send_back_to_checkout( '?payment-mode=' . $purchase_data['post_data']['edd-gateway'] );
            } else {

                // require MercadoPago files
                $this->load_mercadopago_sdk();

                // verify classes loaded
                if ( ! class_exists( 'CheckoutPreferenceData' ) )
                edd_send_back_to_checkout( '?payment-mode=' . $purchase_data['post_data']['edd-gateway'] );

                /* ITEMS */
                $cart_summary = edd_get_purchase_summary($purchase_data, false);

                // generate the items checkout preferences
                $checkoutPreferenceDataItems = new CheckoutPreferenceDataItems();
                $checkoutPreferenceDataItems->setTitle( $cart_summary );
                $checkoutPreferenceDataItems->setQuantity( 1 );
                $checkoutPreferenceDataItems->setUnitPrice( $purchase_data['price'] );
                $checkoutPreferenceDataItems->setCurrencyId( $this->get_currency( $country ) );

                /* PAYER */

                // generate user checkout preferences
                $checkoutPreferenceDataPayer = new CheckoutPreferenceDataPayer();
                $checkoutPreferenceDataPayer->setName( $purchase_data['user_info']['first_name'] );
                $checkoutPreferenceDataPayer->setSurname( $purchase_data['user_info']['last_name'] );
                $checkoutPreferenceDataPayer->setEmail( $purchase_data['user_email'] );

                /* URI */

                // generate uri checkout preferences
                $uri = add_query_arg( 'payment-confirmation', 'mercadopago', get_permalink( $edd_options['success_page'] ) );
                $checkoutPreferenceDataBackUrls = new CheckoutPreferenceDataBackUrls();
                $checkoutPreferenceDataBackUrls->setSuccessUrl( $uri );
                $checkoutPreferenceDataBackUrls->setPendingUrl( add_query_arg( 'payment-pending', 'true', $uri ) );

                /* PAYMENT METHODS */

                // generate payment method preferences
                $checkoutPreferenceDataPaymentMethods = new CheckoutPreferenceDataPaymentMethods();

                // excluded payment methods
                $payment_methods = $this->get_excluded_payment_methods( $country );
                foreach ( $payment_methods as $payment_method_id => $payment_method_title ) {
                    $checkoutPreferenceDataExcludedPaymentMethods = new CheckoutPreferenceDataExcludedPaymentMethods();
                    $checkoutPreferenceDataExcludedPaymentMethods->setExcludedPaymentMethodsId( $payment_method_id );
                    $checkoutPreferenceDataPaymentMethods->setExcludedPaymentMethods( $checkoutPreferenceDataExcludedPaymentMethods );
                }

                // excluded payment types
                $payment_types = $this->get_excluded_payment_types( $country );
                foreach ( $payment_types as $payment_types_id => $payment_types_title ) {
                    $checkoutPreferenceDataExcludedPaymentTypes = new CheckoutPreferenceDataExcludedPaymentTypes();
                    $checkoutPreferenceDataExcludedPaymentTypes->setExcludedPaymentTypesId( $payment_types_id );
                    $checkoutPreferenceDataPaymentMethods->setExcludedPaymentTypes( $checkoutPreferenceDataExcludedPaymentTypes );
                }

                // payment installments
                $payment_installments = $this->get_installments( $country );
                $checkoutPreferenceDataPaymentMethods->setInstallments( $payment_installments );

                // generate checkout preferences
                $checkoutPreferenceData = new CheckoutPreferenceData();
                $checkoutPreferenceData->setExternalReference( $payment );
                $checkoutPreferenceData->setExpires( false );
                $checkoutPreferenceData->setItems( $checkoutPreferenceDataItems );
                $checkoutPreferenceData->setPayer( $checkoutPreferenceDataPayer );
                $checkoutPreferenceData->setBackUrls( $checkoutPreferenceDataBackUrls );
                $checkoutPreferenceData->setPaymentMethods( $checkoutPreferenceDataPaymentMethods );

                /* ACCESS KEY */

                // try getting the access key
                try {

                    // authservice
                    $authService = new AuthService();
                    $accessData = $authService->create_access_data( $credentials['id'], $credentials['secret'] );

                    // verify data
                    if ( gettype( $accessData ) == 'string' ) {
                        throw new exception( $accessData );
                    }

                } catch ( Exception $e ) {
                    // catch exception
                    wp_mail( get_bloginfo( 'admin_email' ), sprintf( __( 'MercadoPago %s Auth Service Error', 'edd-mercadopago-gateway' ), ucwords( $country ) ), $e->getMessage() );
                    edd_send_back_to_checkout( '?payment-mode=' . $purchase_data['post_data']['edd-gateway'] );
                }

                /* CHECKOUT */

                // try to complete the checkout
                try {

                    // new checkoutService instance
                    $checkoutService = new CheckoutService();
                    $checkoutPreference = $checkoutService->create_checkout_preference( $checkoutPreferenceData, $accessData->getAccessToken() );


                    // verify data
                    if ( gettype( $checkoutPreference ) == 'string' ) {
                        throw new exception( $checkoutPreference );
                    }

                    // get the checkout URI
                    $checkout_uri = $checkoutPreference->getInitPoint();

                    // empty cart
                    edd_empty_cart();

                    // send the user to MercadoPago
                    wp_redirect( $checkout_uri );
                    die();

                } catch ( Exception $e ) {
                    //catch exception
                    wp_mail( get_bloginfo( 'admin_email' ), sprintf( __( 'MercadoPago %s Checkout Error', 'edd-mercadopago-gateway' ), ucwords( $country ) ), $e->getMessage() );
                    edd_send_back_to_checkout( '?payment-mode=' . $purchase_data['post_data']['edd-gateway'] );
                }

            }

        }

    } // EDD_MercadoPago_Gateway

    new EDD_MercadoPago_Gateway();

}

if ( ! function_exists( 'edd_mercadopago_notes_callback' ) ) {
    function edd_mercadopago_notes_callback( $args ) {
        echo $args['desc'];
    }
}
