<?php

class Bac_Payment_Pasarela extends WC_Payment_Gateway {

  function __construct() {
  
    $this->id = "BAC";

    $this->method_title = __( "Pasarela de Pagos BAC", 'BAC' );

    $this->method_description = __( "Pasarela de Pagos BAC utilizando woocommerce", 'BAC' );

    $this->title = __( "BAC Payment Gateway", 'BAC' );

    $this->icon = apply_filters( 'woocommerce_bac_icon', plugins_url('/assets/icon.png', __FILE__ ) );

    $this->has_fields = true;
    $this->supports = array( 'default_credit_card_form' );

    $this->init_form_fields();
    
    $this->init_settings();
    
    foreach ( $this->settings as $setting_key => $value ) {
      $this->$setting_key = $value;
    }
    
    if ( is_admin() ) {
      add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
    }   
  } 


  // Build the administration fields for this specific Gateway
  public function init_form_fields() {
    $this->form_fields = array(
      'enabled' => array(
        'title'   => __( 'Activar / Desactivar', 'BAC' ),
        'label'   => __( 'Activar este metodo de pago', 'BAC' ),
        'type'    => 'checkbox',
        'default' => 'no',
      ),
      'title' => array(
        'title'   => __( 'Título', 'BAC' ),
        'type'    => 'text',
        'desc_tip'  => __( 'Título de pago que el cliente verá durante el proceso de pago.', 'BAC' ),
        'default' => __( 'Tarjeta de crédito', 'BAC' ),
      ),
      'description' => array(
        'title'   => __( 'Descripción', 'BAC' ),
        'type'    => 'textarea',
        'desc_tip'  => __( 'Descripción de pago que el cliente verá durante el proceso de pago.', 'BAC' ),
        'default' => __( 'Pague con seguridad usando su tarjeta de crédito.', 'BAC' ),
        'css'   => 'max-width:350px;'
      ),
     /* 'key_id' => array(
        'title'   => __( 'Key id', 'BAC' ),
        'type'    => 'text',
        'desc_tip'  => __( 'ID de clave de seguridad del panel de control del comerciante.', 'BAC' ),
        'default' => '',
      ),*/
      'api_key' => array(
        'title'   => __( 'Api key', 'BAC' ),
        'type'    => 'text',
        'desc_tip'  => __( 'ID de clave de api del panel de control del comerciante.', 'BAC' ),
        'default' => '',
      ),
    );    
  }

  public function process_payment( $order_id ) {
    global $wc;
   
    $orden_cliente = new WC_Order( $order_id );
    $environment_url = 'https://98f0a744-fabe-4718-a1ad-66221b7086f1.mock.pstmn.io';   
    $time = time();
    //$key_id = $this->key_id;
    $orderid = str_replace( "#", "", $orden_cliente->get_order_number() );
    $hash = md5($orderid."|".$orden_cliente->order_total."|".$time."|".$this->api_key);

    $payload = array(
     // "key_id"  => $key_id,
      "hash" => $hash,
      "time" => $time,
      "amount" => $orden_cliente->order_total,
      "ccnumber" => str_replace( array(' ', '-' ), '', $_POST['bac_payment-card-number'] ),
      "ccexp" => str_replace( array( '/', ' '), '', $_POST['bac_payment-card-expiry'] ),
      "orderid" => $orderid,
      "cvv" => ( isset( $_POST['bac_payment-card-cvc'] ) ) ? $_POST['bac_payment-card-cvc'] : '',
      "type" => "auth",
     );

    $respuesta = wp_remote_post( $environment_url, array(
      'method'    => 'POST',
      'body'      => http_build_query( $payload ),
      'timeout'   => 90,
    ) );


    if ( is_wp_error( $respuesta ) ) 
      throw new Exception( __( 'Tenemos problemas al procesar su solicitud. Disculpe los inconvenientes.', 'BAC' ) );

    if ( empty( $respuesta['body'] ) )
      throw new Exception( __( 'BAC\'s La respuesta fue vacìa.', 'BAC' ) );
      
    $respuesta_cuerpo = wp_remote_retrieve_body( $respuesta );

    $re = explode( "&", $respuesta_cuerpo );
    $resp = array();
    foreach($re as $r) {
      $v = explode('=', $r);
      $resp[$v[0]] = $v[1];
    }

    if ( ($resp['response'] == 1 ) || ( $resp['response_code'] == 100 ) ) {

      $orden_cliente->add_order_note( __( 'Pago por BAC completado', 'BAC' ) );

      $order_id = method_exists( $orden_cliente, 'get_id' ) ? $orden_cliente->get_id() : $orden_cliente->ID;
      update_post_meta($order_id , '_wc_order_bac_authcode', $resp['authcode'] );
			update_post_meta($order_id , '_wc_order_bac_transactionid', $resp['transactionid'] );
                         
      $orden_cliente->payment_complete();

      $wc->cart->empty_cart();

      return array(
        'result'   => 'Éxito',
        'redirect' => $this->get_return_url( $orden_cliente ),
      );
    } else {

      wc_add_notice( $resp['responsetext'], 'error' );

      $orden_cliente->add_order_note( 'Error: '. $resp['responsetext'] );
    }
  }

  public function validate_fields() {
    return true;
  }

}

add_action( 'woocommerce_admin_order_data_after_billing_address', 'show_bac_info', 10, 1 );
function show_bac_info( $order ){
    $order_id = method_exists( $order, 'get_id' ) ? $order->get_id() : $order->id;
    echo '<p><strong>'.__('BAC Codigo de Autorizacion ').':</strong> ' . get_post_meta( $order_id, '_wc_order_bac_authcode', true ) . '</p>';
    echo '<p><strong>'.__('BAC Id Transaccion ').':</strong> ' . get_post_meta( $order_id, '_wc_order_bac_transactionid', true ) . '</p>';
}

?>
