<?php

/* 
 * Main Gateway of ROI using a daemon online 
 * Authors: Serhack and cryptochangements
 * Copyright (c) 2017 Monero Integrations
 * Copyright (c) 2017 Phillip Whelan
 */


class Roi_Gateway extends WC_Payment_Gateway
{
    private $reloadTime = 30000;
    private $discount;
    private $confirmed = false;
    public $roi_daemon;

    function __construct()
    {
        $this->id = "roi_gateway";
        $this->method_title = __("Roicoin Gateway", 'roi_gateway');
        $this->method_description = __("Roicoin Payment Gateway plugin for WooCommerce. You can find more information about this payment gateway on our website. You'll need a daemon online for your address.", 'sumo_gateway');
        $this->title = __("Roicoin Gateway", 'roi_gateway');
        $this->version = "0.3";
        //
        $this->icon = apply_filters('woocommerce_offline_icon', '');
        $this->has_fields = false;

        $this->log = new WC_Logger();

        $this->init_form_fields();
        $this->host = $this->get_option('daemon_host');
        $this->port = $this->get_option('daemon_port');
        $this->address = $this->get_option('roi_address');
        $this->username = $this->get_option('username');
        $this->password = $this->get_option('password');
        $this->discount = $this->get_option('discount');

        // After init_settings() is called, you can get the settings and load them into variables, e.g:
        // $this->title = $this->get_option('title' );
        $this->init_settings();

        // Turn these settings into variables we can use
        foreach ($this->settings as $setting_key => $value) {
            $this->$setting_key = $value;
        }

        add_action('admin_notices', array($this, 'do_ssl_check'));
        add_action('admin_notices', array($this, 'validate_fields'));
        add_action('woocommerce_thankyou_' . $this->id, array($this, 'instruction'));
        if (is_admin()) {
            /* Save Settings */
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
            add_filter('woocommerce_currencies', [$this, 'add_my_currency']);
            add_filter('woocommerce_currency_symbol', [$this, 'add_my_currency_symbol'], 10, 2);
            add_action('woocommerce_email_before_order_table', array($this, 'email_instructions'), 10, 2);
        }
        $this->roi_daemon = new Roi_Library($this->host . ':' . $this->port . '/json_rpc', $this->username, $this->password);
    }

    public function get_icon()
    {
        return apply_filters('woocommerce_gateway_icon', "<img src='".plugins_url('/../assets/roi-logo.png', __FILE__ )."'>");
    }

    public function init_form_fields()
    {
        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Enable / Disable', 'roi_gateway'),
                'label' => __('Enable this payment gateway', 'roi_gateway'),
                'type' => 'checkbox',
                'default' => 'no'
            ),

            'title' => array(
                'title' => __('Title', 'roi_gateway'),
                'type' => 'text',
                'desc_tip' => __('Payment title the customer will see during the checkout process.', 'roi_gateway'),
                'default' => __('Roicoin Payment', 'roi_gateway')
            ),
            'description' => array(
                'title' => __('Description', 'roi_gateway'),
                'type' => 'textarea',
                'desc_tip' => __('Payment description the customer will see during the checkout process.', 'roi_gateway'),
                'default' => __('Pay securely using Roi.', 'roi_gateway')

            ),
            'roi_address' => array(
                'title' => __('Roicoin Address', 'roi_gateway'),
                'label' => __('Useful for people that have not a daemon online'),
                'type' => 'text',
                'desc_tip' => __('Roi Wallet Address', 'roi_gateway')
            ),
            'daemon_host' => array(
                'title' => __('Wallet RPC Host/IP', 'roi_gateway'),
                'type' => 'text',
                'desc_tip' => __('This is the Daemon Host/IP to authorize the payment with port', 'roi_gateway'),
                'default' => 'localhost',
            ),
            'daemon_port' => array(
                'title' => __('Wallet RPC port', 'roi_gateway'),
                'type' => 'text',
                'desc_tip' => __('This is the Daemon Host/IP to authorize the payment with port', 'roi_gateway'),
                'default' => '19733',
            ),
            'username' => array(
                'title' => __('Roi Wallet username', 'roi_gateway'),
                'desc_tip' => __('This is the username that you used with your roi wallet-rpc', 'roi_gateway'),
                'description' => __('You can leave this field empty if you did not set any username', 'roi_gateway'),
                'type' => __('text'),
                'default' => __('username', 'roi_gateway'),

            ),
            'password' => array(
                'title' => __('Roi wallet RPC password', 'roi_gateway'),
                'desc_tip' => __('This is the password that you used to secure your roi wallet-rpc', 'roi_gateway'),
                'description' => __('You can leave this field empty if you did not set any password', 'roi_gateway'),
                'type' => __('text'),
                'default' => ''

            ),
            'discount' => array(
                'title' => __('% discount for using ROI', 'roi_gateway'),

                'desc_tip' => __('Provide a discount to your customers for making a private payment with ROI!', 'roi_gateway'),
                'description' => __('Do you want to spread the word about ROI Coin? Offer a small discount! Leave this empty if you do not wish to provide a discount', 'roi_gateway'),
                'type' => __('text'),
                'default' => '5%'

            ),
            'environment' => array(
                'title' => __(' Test Mode', 'roi_gateway'),
                'label' => __('Enable Test Mode', 'roi_gateway'),
                'type' => 'checkbox',
                'description' => __('Check this box if you are using testnet', 'roi_gateway'),
                'default' => 'no'
            ),
            'onion_service' => array(
                'title' => __(' Onion Service', 'roi_gateway'),
                'label' => __('Enable Onion Service', 'roi_gateway'),
                'type' => 'checkbox',
                'description' => __('Check this box if you are running on an Onion Service (Suppress SSL errors)', 'roi_gateway'),
                'default' => 'no'
            ),
            'subaddress_payments' => array(
                'title' => __('Subaddress Payments', 'roi_gateway'),
                'label' => __('Enable payments to subdaddresses', 'roi_gateway'),
                'type' => 'checkbox',
                'description' => __('Check this box if you wish to receive payments via Subaddresses instead of Payment IDs', 'roi_gateway'),
                'default' => 'no'
            ),
        );
    }

    public function add_my_currency($currencies)
    {
        $currencies['ROI'] = __('Roi', 'woocommerce');
        return $currencies;
    }

    function add_my_currency_symbol($currency_symbol, $currency)
    {
        switch ($currency) {
            case 'ROI':
                $currency_symbol = 'ROI';
                break;
        }
        return $currency_symbol;
    }

    public function admin_options()
    {
        $this->log->add('Roi_gateway', '[SUCCESS] ROI Coin Settings OK');

        echo "<h1>ROI Payment Gateway</h1>";

        echo "<p>Welcome to ROI Coin payment plugin for WooCommerce. Getting started: Make a connection with ROI Coin daemon. For more info, see <a href=\"https://github.com/sumoweb/sumowp/blob/master/README.md\"> our GitHub page</a>";
        echo "<div style='border:1px solid #DDD;padding:5px 10px;font-weight:bold;color:#223079;background-color:#9ddff3;'>";
        $this->getamountinfo();
        echo "</div>";
        echo "<table class='form-table'>";
        $this->generate_settings_html();
        echo "</table>";
        echo "<h4>Secure your wallet RPC daemon! Learn more about using a password with the ROI Coin wallet RPC <a href=\"https://github.com/sumoweb/sumowp/blob/master/README.md\">here</a></h4>";
    }

    public function getamountinfo()
    {
        $wallet_amount = $this->roi_daemon->getbalance();
        if (!isset($wallet_amount)) {
            $this->log->add('Roi_gateway', '[ERROR] No connection with daemon');
            $wallet_amount['balance'] = "0";
            $wallet_amount['unlocked_balance'] = "0";
        }
        else {
            $real_wallet_amount = $wallet_amount['balance'] / 1000000000;
            $real_amount_rounded = round($real_wallet_amount, 6);
    
            $unlocked_wallet_amount = $wallet_amount['unlocked_balance'] / 1000000000;
            $unlocked_amount_rounded = round($unlocked_wallet_amount, 6);
    
            echo "Your balance is: " . $real_amount_rounded . " ROI </br>";
            echo "Unlocked balance: " . $unlocked_amount_rounded . " ROI </br>";
        }
    }

    public function process_payment($order_id)
    {
        $order = wc_get_order($order_id);
        $order->update_status('on-hold', __('Awaiting offline payment', 'roi_gateway'));
        // Reduce stock levels
        $order->reduce_order_stock();

        // Remove cart
        WC()->cart->empty_cart();

        // Return thank you redirect
        return array(
            'result' => 'success',
            'redirect' => $this->get_return_url($order)
        );

    }

    // Submit payment and handle response

    public function validate_fields()
    {
        if ($this->check_subaddress_with_subaddress_payments() != TRUE) {
            echo "<div class=\"error\"><p>Do not mix subdaddresses with subaddress payments.</p></div>";
        }
        if ($this->check_roi_address() != TRUE) {
            echo "<div class=\"error\"><p>Your ROI Coin address doesn't seem to be valid. Check that you've inserted it correctly.</p></div>";
        }
    }


    // Validate fields

    public function check_subaddress_with_subaddress_payments()
    {
        $addr = $this->settings['roi_address'];
        if ($this->settings['subaddress_payments'] == "yes")
        {
            if ($this->settings['environment'] == "no") {
                if (substr($addr, 4) == "Subo") {
                    return false;
                }
            } else {
                if (substr($addr, 4) == "Susu") {
                    return false;
                }
            }
        }
        
        return true;
    }
    
    public function check_roi_address()
    {
        require_once __DIR__ . '/cryptonote.php';
        
        $addr = $this->settings['roi_address'];       
        if (function_exists('bcadd'))
        {
            $prefixes = $this->settings['environment'] == "no" ? 
                ["9ae7ae", "9ae3"] : 
                ["9aeadd", "9aea"];
            return Cryptonote::VerifyAddress($addr, $prefixes);
        }
        if ($this->settings['environment'] == "no")
        {
            switch(strlen($addr))
            {
                case 99: return substr($addr, 0, 4) == "Sumo";
                case 98: return substr($addr, 0, 4) == "Subo";
                default: return false;
            }
        }
        else
        {
            switch(strlen($addr))
            {
                case 99: return substr($addr, 0, 4) == "Suto";
                case 98: return substr($addr, 0, 4) == "Susu";
                default: return false;
            }
        }
    }

    public function instruction($order_id)
    {
        $order = wc_get_order($order_id);
        $amount = floatval(preg_replace('#[^\d.]#', '', $order->get_total()));
        $payment = $this->get_payment($order_id);
        $currency = $order->get_currency();
        $amount_roi2 = $this->changeto($amount, $currency, $order_id);
        if ($amount_roi2 <= 0)
        {
            echo "ERROR: Temporarily unable to get exchange rate<br/>";
            echo "
             <script type='text/javascript'>setTimeout(function () { location.reload(true); }, $this->reloadTime);</script>";
            return;
        }
        
        $uri = $payment->get_uri($amount_roi2);
        $this->confirmed = $payment->verify($order, $amount_roi2);
        $message = "We are waiting for your payment to be confirmed";
	
	$order->update_meta_data( "Address", $payment->get_address());
        $order->update_meta_data( "Amount requested (ROI)", $amount_roi2);
        $order->save();
        
        if ($this->confirmed) {
            $message = "Payment has been received and confirmed. Thanks!";
            $this->log->add('Roi_gateway', '[SUCCESS] Payment has been recorded. Congratulations!');
            $order = wc_get_order($order_id);
            $order->update_status('completed', __('Payment has been received', 'roi_gateway'));
            $this->reloadTime = 3000000000000; // Greatly increase the reload time as it is no longer needed
            
            $color = "006400";
        } else {
            $color = "DC143C";
        }
        echo "<h4><font color=$color>" . $message . "</font></h4>";
        echo "
        <head>
        <!--Import Google Icon Font-->
        <link href='https://fonts.googleapis.com/icon?family=Material+Icons' rel='stylesheet'>
        <link href='https://fonts.googleapis.com/css?family=Montserrat:400,800' rel='stylesheet'>
        <link href='".plugins_url('/../assets/style.css', __FILE__)."' rel='stylesheet'>
        <link rel='stylesheet' href='https://use.fontawesome.com/releases/v5.0.8/css/all.css' integrity='sha384-3AB7yXWz4OeoZcPbieVW64vVXEwADiYyAEhwilzWsLw+9FgqpyjjStpPnpBO8o8S' crossorigin='anonymous'>
            <script>
            function RoiCopy() {
            var copyText = document.getElementById('roi-amount');
            copyText.select();
            document.execCommand('Copy');
  
            var tooltip = document.getElementById('RoiTooltipAmount');
            tooltip.innerHTML = 'Copied: ' + copyText.value;
            }
            function AddressCopy() {
            var copyText = document.getElementById('roi-address');
            copyText.select();                                      
            document.execCommand('Copy'); 
            var tooltip = document.getElementById('RoiTooltipAddress');
            tooltip.innerHTML = 'Copied: ' + copyText.value;
            }
            function outROI() {
            var tooltip = document.getElementById('RoiTooltipAmount');
            tooltip.innerHTML = 'Copy to clipboard';
            }
            function outAddress() {
            var tooltip = document.getElementById('RoiTooltipAddress');
            tooltip.innerHTML = 'Copy to clipboard';
            }
            </script>
            <!--Let browser know website is optimized for mobile-->
            <meta name='viewport' content='width=device-width, initial-scale=1.0'/>
            </head>
            <body>
            <!-- page container  -->
            <div class='page-container'>
            <!-- roi container payment box -->
            <div class='container-roi-payment'>
            <!-- header -->
            <div class='header-roi-payment'>
            <span class='logo-roi'><img src='".plugins_url('/../assets/sumo-logo.png', __FILE__ )."'/></span>
            <span class='sumo-payment-text-header'><h2>ROI PAYMENT</h2></span>
            </div>
            <!-- end header -->
            <!-- roi content box -->
            <div class='content-xmr-payment'>
            <div class='roi-amount-send'>
            <span class='roi-label'>Send:</span>
            <input class='roi-amount-box' id='roi-amount' type='text' value='".$amount_roi2."' readonly><div class='roi-box' onclick='RoiCopy()' onmouseout='outROI()'><i class='far fa-copy'></i> ROI<span class='RoiTooltip' id='RoiTooltipAmount'>Copy to clipboard</span></div>
            </div>
            <div class='roi-address'>
            <span class='roi-label'>To this address:</span>
            <textarea class='roi-address-box' id='roi-address' type='text' readonly>".$payment->get_address()."</textarea><div class='roi-box' onclick='AddressCopy()' style='margin-right:10px' onmouseout='outAddress()'><i class='far fa-copy'></i><span class='RoiTooltip' id='RoiTooltipAddress'>Copy to clipboard</span></div>
            </div> 
            <div class='roi-qr-code'>
            <span class='roi-label'>Or scan QR:</span>
            <div class='roi-qr-code-box'><img src='https://api.qrserver.com/v1/create-qr-code/? size=200x200&data=".$uri."' /></div>
            </div>
            <div class='clear'></div>
            </div>
            <!-- end content box -->
            <!-- footer roi payment -->
            <div class='footer-roi-payment'>
            <a href='https://roi-coin.com' target='_blank'>Help</a> | <a href='https://roi-coin.com' target='_blank'>About ROI</a>
            </div>
            <!-- end footer roi payment -->
            </div>
            <!-- end roi container payment box -->
            </div>
            <!-- end page container  -->
            </body>
        ";
	    
	    
	    
        echo "
      <script type='text/javascript'>setTimeout(function () { location.reload(true); }, $this->reloadTime);</script>";
    }

    private function get_payment($order_id)
    {
        require_once __DIR__ . '/roi_payment.php';
        return new Roi_Payment($this, $order_id);
    }

    private function get_rate($currency, $order_id)
    {
        global $wpdb;
        // This will create a table named whatever the payment id is inside the database "WordPress"
        $create_table = "
            CREATE TABLE IF NOT EXISTS {$wpdb->prefix}roi_order_rates (
                order_id char(16) UNIQUE PRIMARY KEY,
                currency char(3) NOT NULL,
                rate DECIMAL(10,4) NOT NULL,
                timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ";
        
        $wpdb->query($create_table);
        $sql = $wpdb->prepare("
            SELECT count(*) as count 
            FROM {$wpdb->prefix}roi_order_rates
            WHERE order_id = %s
        ", [$order_id]);
        $rows_num = $wpdb->get_results($sql);
        if ($rows_num[0]->count > 0) // Checks if the row has already been created or not
        {
            $sql = $wpdb->prepare("
                SELECT rate 
                FROM {$wpdb->prefix}roi_order_rates
                WHERE order_id = %s
            ", [$order_id]);
            $stored_rate = $wpdb->get_results($sql);
            return $stored_rate[0]->rate; //this will turn the stored rate back into a decimaled number
        }
        
        $roi_live_price = $this->retrieveprice($currency);
        if ($roi_live_price == -1) return -1;
        
        $sql = $wpdb->prepare("
            INSERT INTO {$wpdb->prefix}roi_order_rates (order_id, currency, rate)
            VALUES (%s, %s, %f)
        ", [$order_id, $currency, $roi_live_price]);
        $wpdb->query($sql);
        
        return $roi_live_price;
    }
    
    public function changeto($amount, $currency, $order_id)
    {
        $rate = $this->get_rate($currency, $order_id);
        if ($rate == -1) return -1;
        
        if (isset($this->discount)) {
            $discount_decimal = $this->discount / 100;
            $new_amount = $amount / $rate;
            $discount = $new_amount * $discount_decimal;
            $final_amount = $new_amount - $discount;
            $rounded_amount = round($final_amount, 9);
        } else {
            $new_amount = $amount / $rate;
            $rounded_amount = round($new_amount, 9); //the roi coin wallet can't handle decimals smaller than 0.000000000001
        }
        
        return $rounded_amount;
    }


    // Check if we are forcing SSL on checkout pages
    // Custom function not required by the Gateway

    public function retrieveprice($currency)
    {
        $currencies = ['USD', 'EUR', 'CAD', 'GBP', 'INR', 'ROI'];
        if (!in_array($currency, $currencies)) $currencies[] = $currency;
        
        $roi_price = file_get_contents('https://min-api.cryptocompare.com/data/price?fsym=ROI&tsyms='.implode(",", $currencies).'&extraParams=roi_woocommerce');
        $price = json_decode($roi_price, TRUE);
        if (!isset($price)) {
            $this->log->add('Roi_Gateway', '[ERROR] Unable to get the price of ROI');
            return -1;
        }
        
        if (!isset($price[$currency])) {
            $this->log->add('Roi_Gateway', '[ERROR] Unable to retrieve ROI in currency '.$currency);
            return -1;
        }
        
        return $price[$currency];
    }

    public function do_ssl_check()
    {
        if ($this->enabled == "yes" && !$this->get_option('onion_service')) {
            if (get_option('woocommerce_force_ssl_checkout') == "no") {
                echo "<div class=\"error\"><p>" . sprintf(__("<strong>%s</strong> is enabled and WooCommerce is not forcing the SSL certificate on your checkout page. Please ensure that you have a valid SSL certificate and that you are <a href=\"%s\">forcing the checkout pages to be secured.</a>"), $this->method_title, admin_url('admin.php?page=wc-settings&tab=checkout')) . "</p></div>";
            }
        }
    }

    public function connect_daemon()
    {
        $host = $this->settings['daemon_host'];
        $port = $this->settings['daemon_port'];
        $roi_library = new Roi($host, $port);
        if ($roi_library->works() == true) {
            echo "<div class=\"notice notice-success is-dismissible\"><p>Everything works! Congratulations and welcome to ROI. <button type=\"button\" class=\"notice-dismiss\">
						<span class=\"screen-reader-text\">Dismiss this notice.</span>
						</button></p></div>";

        } else {
            $this->log->add('Roi_gateway', '[ERROR] Plugin can not reach wallet rpc.');
            echo "<div class=\" notice notice-error\"><p>Error connecting with daemon, see documentation for more info</p></div>";
        }
    }
}
