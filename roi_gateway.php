<?php
/*
Plugin Name: ROI Coin - WooCommerce Gateway
Plugin URI: https://roi-coin.com
Description: Extends WooCommerce by Adding the ROI Coin Gateway
Version: 1.1
Author: DisasterFaster
Author URI: https://github.com/DisasterFaster
*/

// This code isn't for Dark Net Markets, please report them to Authority!
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}
// Include our Gateway Class and register Payment Gateway with WooCommerce
add_action('plugins_loaded', 'roi_init', 0);
function roi_init()
{
    /* If the class doesn't exist (== WooCommerce isn't installed), return NULL */
    if (!class_exists('WC_Payment_Gateway')) return;


    /* If we made it this far, then include our Gateway Class */
    include_once('include/roi_payments.php');
    require_once('library.php');

    // Lets add it too WooCommerce
    add_filter('woocommerce_payment_gateways', 'roi_gateway');
    function roi_gateway($methods)
    {
        $methods[] = 'Roi_Gateway';
        return $methods;
    }
}

/*
 * Add custom link
 * The url will be http://yourworpress/wp-admin/admin.php?=wc-settings&tab=checkout
 */
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'roi_payment');
function roi_payment($links)
{
    $plugin_links = array(
        '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout') . '">' . __('Settings', 'roi_payment') . '</a>',
    );

    return array_merge($plugin_links, $links);
}

add_action('admin_menu', 'roi_create_menu');
function roi_create_menu()
{
    add_menu_page(
        __('ROIcoin', 'textdomain'),
        'ROIcoin',
        'manage_options',
        'admin.php?page=wc-settings&tab=checkout&section=roi_gateway',
        '',
        plugins_url('/assets/roi-icon.png', __FILE__),
        56 // Position on menu, woocommerce has 55.5, products has 55.6

    );
}

