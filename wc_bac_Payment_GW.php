<?php

/*
Plugin Name: BAC-Credomatic Pasarela de Pago Plugin 
Plugin URI: https://www.baccredomatic.com/
Author: Eli&Mily
Description: Este es un plugin para usar el metodo de pago del Bac-Credomatic
Version: 1.0.0
Licence: 1.0.0
*/
if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) return;
  add_action( 'plugins_loaded', 'bac_pago_init', 0 );
  function bac_pago_init() {
  
    if ( ! class_exists( 'WC_Payment_Gateway' ) ) return;
    
    include_once( 'wc_bac-pay.php' );

    add_filter( 'woocommerce_payment_gateways', 'añadir_bac_pago_pasarela' );
    function añadir_bac_pago_pasarela( $methods ) {
      $methods[] = 'Bac_Payment_Pasarela';
      return $methods;
    }
  }

  add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'bac_payment_action_links' );
  function bac_payment_action_links( $links ) {
    $plugin_links = array(
      '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout' ) . '">' . __( 'Settings', 'bac-payment' ) . '</a>',
    );

    return array_merge( $plugin_links, $links );
  }
