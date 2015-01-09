<?php
/*
Plugin Name: PagosWeb Payment Gateway For WooCommerce
Description: This Payment Gateway For WooCommerce extends the functionality of WooCommerce to accept payments from credit/debit cards using PagosWeb Gateway
Version: 1.0
Plugin URI: 
Author: Victor Nessi
License: Under GPLv2

*/


define('PAGOSWEB_CARDTYPES', '2,MasterCard,1,VISA,3,Oca,4,RedPagos,5,E-Brou,6,Banred,7,Skrill,8,Diners,9,Diners Discover,10,Lider,11,Santander,12,BBVA,13,Banque Heritage');

add_action('plugins_loaded', 'woocommerce_pagosweb_init', 0);
add_action( 'wp_enqueue_scripts', 'load_pagosweb_scripts' );

function load_pagosweb_scripts() {
    wp_enqueue_script(
        'pagosweb-script',
        plugins_url( '/js/pagosweb.js' , __FILE__ ),
        array( 'jquery' )
    );
    wp_enqueue_script(
        'ci-script',
        plugins_url( '/js/ci.jquery.js' , __FILE__ ),
        array( 'jquery' )
    );
    wp_enqueue_style( 'style-pagosweb', plugins_url( '/css/styles.css' , __FILE__ ) );
}



function woocommerce_pagosweb_init() {

    if (!class_exists('WC_Payment_Gateway'))
        return;

    /**
     * Localization
     */
    load_plugin_textdomain('wc-pagosweb', false, dirname(plugin_basename(__FILE__)) . '/languages');

    /**
     * PagosWeb Payment Gateway class
     */
    class WC_Genux_PagosWeb extends WC_Payment_Gateway 
    {
        protected $msg = array();

        public function __construct() {

            $this->id = 'pagosweb';
            $this->method_title = __('PagosWeb', 'wp-pagosweb');
            $this->icon = WP_PLUGIN_URL . "/" . plugin_basename(dirname(__FILE__)) . '/images/plugin-logo.png';
            $this->has_fields = true;
            $this->init_form_fields();
            $this->init_settings();
            $this->title = $this->settings['title'];
            $this->description = $this->settings['description'];
            $this->id_cliente = $this->settings['id_cliente'];
            $this->version = $this->settings['version'];
            $this->mode = $this->settings['working_mode'];
            $this->security_token = $this->settings['security_token'];
            $this->success_message = $this->settings['success_message'];
            $this->failed_message = $this->settings['failed_message'];
            $this->habilitar_cuotas = $this->settings['habilitar_cuotas'];
            $this->cantidad_cuotas = $this->settings['cantidad_cuotas'];
            $this->moneda = $this->settings['moneda'];
            $this->liveurl = 'https://service.pagosweb.com.uy/v3.4/requestprocessor.aspx';
            $this->testurl = 'http://testing.pagosweb.com.uy/v3.4/requestprocessor.aspx';
            $this->msg['message'] = "";
            $this->msg['class'] = "";

            add_action('init', array(&$this, 'check_pagosweb_response'));
            //update for woocommerce >2.0
            add_action('woocommerce_api_wc_genux_pagosweb', array($this, 'check_pagosweb_response'));
            add_action('valid-pagosweb-request', array(&$this, 'successful_request'));

            if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=')) {
                add_action('woocommerce_update_options_payment_gateways_' . $this->id, array(&$this, 'process_admin_options'));
            } else {
                add_action('woocommerce_update_options_payment_gateways', array(&$this, 'process_admin_options'));
            }

            add_action('woocommerce_receipt_pagosweb', array(&$this, 'receipt_page'));
            //add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'receipt_page' ) );
            add_action('woocommerce_thankyou_pagosweb', array(&$this, 'thankyou_page'));
        }

        function init_form_fields() {

            $this->form_fields = array(
                'enabled' => array(
                    'title' => __('Habilitado/Deshabilitado', 'wp-pagosweb'),
                    'type' => 'checkbox',
                    'label' => __('Habilitar Módulo de PagosWeb.', 'wp-pagosweb'),
                    'default' => 'no'),
                'title' => array(
                    'title' => __('Título:', 'wp-pagosweb'),
                    'type' => 'text',
                    'description' => __('Este título se mostrará al momento de realizar el pago.', 'wp-pagosweb'),
                    'default' => __('PagosWeb', 'wp-pagosweb')),
                'description' => array(
                    'title' => __('Descripción:', 'wp-pagosweb'),
                    'type' => 'textarea',
                    'description' => __('Esta descripción se mostrará al momento de realizar el pago.', 'wp-pagosweb'),
                    'default' => __('Pague de forma segura utilizando Tarjetas de Crédito mediante los servidores de PagosWeb.', 'wp-pagosweb')),
                'id_cliente' => array(
                    'title' => __('ID de Cliente', 'wp-pagosweb'),
                    'type' => 'text',
                    'description' => __('Número de cliente dentro de la aplicación PagosWeb, asignado en la instalación. Es fijo a partir de la primera instalación.', 'wp-pagosweb')),
                'version' => array(
                    'title' => __('Version', 'wp-pagosweb'),
                    'type' => 'text',
                    'description' => __('Versión actual del paquete de información.', 'wp-pagosweb'),
                    'default' => __('3.4', 'wp-pagosweb')),
                'security_token' => array(
                    'title' => __('Llave 3DES', 'wp-pagosweb'),
                    'type' => 'text',
                    'description' => __('Llave 3DES provista por PagosWeb al momento de la instalación', 'wp-pagosweb')),
                'success_message' => array(
                    'title' => __('Mensaje de transacción exitosa', 'wp-pagosweb'),
                    'type' => 'textarea',
                    'description' => __('Mensaje que se mostrará cuando una transacción fue realizada con éxito.', 'wp-pagosweb'),
                    'default' => __('Tú pago fué realizado con éxito.', 'wp-pagosweb')),
                'failed_message' => array(
                    'title' => __('Mensaje de transacción fallida', 'wp-pagosweb'),
                    'type' => 'textarea',
                    'description' => __('Mensaje que se mostrará cuando una transacción fue rechazada.', 'wp-pagosweb'),
                    'default' => __('La transacción fue rechazada.', 'wp-pagosweb')),
                'working_mode' => array(
                    'title' => __('Modo de API'),
                    'type' => 'select',
                    'options' => array('false' => 'Live Mode', 'true' => 'Test/Sandbox Mode'),
                    'description' => "Live/Test Mode"),
                'habilitar_cuotas' => array(
                    'title' => __('Habilitar pago en cuotas', 'wp-pagosweb'),
                    'type' => 'checkbox',
                    'label' => __('Habilitar Pago en Cuotas', 'wp-pagosweb'),
                    'default' => 'no'),
                'cantidad_cuotas' => array(
                    'title' => __('Cantidad de cuotas'),
                    'type' => 'select',
                    'options' => array('2' => '2 cuotas', '3' => '3 cuotas', '4' => '4 cuotas', '5' => '5 cuotas', '6' => '6 cuotas'),
                    'description' => "Cantidad de cuotas"),
                'moneda' => array(
                    'title' => __('Tipo de Moneda'),
                    'type' => 'select',
                    'options' => array('858'=>'Pesos', '840'=>'Dólares'),
                    'description' => "Tipo de moneda")
            );

            $available_cardtypes = explode(',', PAGOSWEB_CARDTYPES);

            for ($i = 0; $i < count($available_cardtypes); $i+=2) {
                $this->form_fields['cardtype-' . $available_cardtypes[$i]] = array(
                    'type' => 'checkbox',
                    'label' => $available_cardtypes[$i + 1],
                    'default' => 'no'
                );
                if ($i == 0) {
                    $this->form_fields['cardtype-' . $available_cardtypes[$i]]['title'] = __('Tarjetas Disponibles', 'wp-pagosweb');
                }
            }
        }

        /**
         * Admin Panel Options
         * - Options for bits like 'title' and availability on a country-by-country basis
         * */
        public function admin_options() {
            echo '<h3>' . __('Pasarela de Pagos PagosWeb', 'wp-pagosweb') . '</h3>';
            echo '<p>' . __('PagosWeb - Su pasarela de pagos') . '</p>';
            echo '<table class="form-table">';
            $this->generate_settings_html();
            echo '</table>';
        }

        /**
         *  There are no payment fields for Authorize.net, but want to show the description if set.
         * */
        function payment_fields() {
            if ($this->description)
                echo wpautop(wptexturize($this->description));

            global $woocommerce; 

            $checkout = $woocommerce->checkout();

            // add available cards
            $card_select = "<option value='0' > -- Seleccione -- </option>\n";
            $available_cardtypes = explode(',', PAGOSWEB_CARDTYPES);
            for ($i=0; $i < count($available_cardtypes); $i+=2){
                if($this->settings['cardtype-' . $available_cardtypes[$i]] == 'yes')
                    $card_select .= "<option value='" . $available_cardtypes[$i] . "' >" . $available_cardtypes[$i+1] . "</option>\n";
            }

            $coutas_select = "";
            
            for ($i=1; $i <= $this->cantidad_cuotas; $i++){
                    $coutas_select .= "<option value='" . $i . "' >" . $i . "</option>\n";
            }

            ?>

            <table style="width: 75%;">
            <tbody>
            <tr>
                <td><label for="genux_cardtype"><?php _e('Seleccione medio de pago:', 'wp-pagosweb') ?> <span class="required">*</span></label></td>
                <td>
                    <select id="genux_cardtype" name="genux_cardtype">
                      <?php echo $card_select; ?>
                    </select>
                </td>
            </tr>
            <tr id="cedula_tr" style="display:none">
                <td><label for="pagosweb_cedula">Cedula: <span class="required">*</span></label></td>
                <td><input type="text" name="pagosweb_cedula" id="pagosweb_cedula" placeholder="1234567-0"></td>
            </tr>

            <?php 

            if($this->habilitar_cuotas == 'yes') {

                echo '<tr id="cuotas_tr">';
                echo '<td><label for="genux_cuotas">';
                     _e('Cuotas', 'wp-pagosweb');
                echo '<span class="required">*</span></label></td>';
                echo '<td><select id="genux_cuotas" name="genux_cuotas">' . $coutas_select . '</select></td>';
                echo '</tr>';

            } 
            ?>

            </tbody>
            </table>

            <?php
        }

        public function thankyou_page($order_id) {

        }

        /**
         * Receipt Page
         * */
        function receipt_page($order) {

            $tarjeta = $_REQUEST['card'];
            $cedula  = $_REQUEST['ci'];

            echo '<p>' . __('Gracias por su orden, por favor haga click debajo para pagar via PagosWeb.', 'wp-pagosweb') . '</p>';
            
            //Por defecto siempre voy a mandar el pago en 1 cuota
            $cuotas = 1;
            
            if($this->habilitar_cuotas == 'yes') { 
                $cuotas = $_REQUEST['cuotas'];
            } 
            echo $this->generate_pagosweb_form($order, $tarjeta, $cuotas, $cedula);

        }

        /**
         * Process the payment and return the result
         * */
        function process_payment($order_id) {

            if($_REQUEST['genux_cardtype'] == '4'){
                if($_REQUEST['pagosweb_cedula'] == ''){
                    return array(
                        'result'   => 'fail',
                        'redirect' => ''
                    );

                }  
            } 

            if($_REQUEST['genux_cardtype'] == '0'){
                return array(
                    'result'   => 'fail',
                    'redirect' => ''
                );
            } 

            $order = new WC_Order($order_id);

            return array('result' => 'success',
                'redirect' => add_query_arg('card', $_REQUEST['genux_cardtype'], add_query_arg('cuotas', $_REQUEST['genux_cuotas'], add_query_arg('ci', $_REQUEST['pagosweb_cedula'], add_query_arg('order', $order->id, add_query_arg('key', $order->order_key, $order->get_checkout_payment_url( $on_checkout = true ))))))
            );		
        }

        function decrypt($encrypted_text, $key, $iv) {
            $cipher = mcrypt_module_open(MCRYPT_3DES, '', MCRYPT_MODE_CBC, '');
            mcrypt_generic_init($cipher, $key, $iv);
            $decrypted = mdecrypt_generic($cipher, base64_decode($encrypted_text));
            mcrypt_generic_deinit($cipher);
            return $decrypted;
        }

        /**
         * Check for valid PagosWeb server callback to validate the transaction response.
         * */
        function check_pagosweb_response() {
            global $woocommerce;
            $redirect_url = '';
            if (count($_POST)) {

                if (isset($_POST['numeroOrden'])) {
                    $order = new WC_Order($_POST['numeroOrden']);
                }

                if ($_POST['codigoAutorizacion'] != '') {
                    
                    try {

                        $transauthorised = false;
                        if ($order->status != 'completed') {
                            if (isset($_POST['ventaAprobada'])) {
                                if ($_POST['ventaAprobada'] == 'True') {
                                    $date = date_create($_POST['fecha']);
                                    $fechaRespuesta = date_format($date, 'ymdhis');
                                    $iv = base64_decode(substr($fechaRespuesta, 1) . '=');
                                    $key = base64_decode($this->security_token);
                                    $strToDesc = $_POST['responseSecurityToken'];
                                    $infoRespuesta = $this->decrypt($strToDesc, $key, $iv);
                                    $stringRespuesta = $_POST['ventaAprobada'] . $_POST['numeroTransaccion']

                                            . $_POST['monto'] . $_POST['codigoAutorizacion'] . $_POST['mensaje']

                                            . $_POST['numeroOrden'] . $_POST['idCliente'] . $_POST['fecha'];

                                    if (substr($infoRespuesta, 0, strlen($stringRespuesta)) == (string) $stringRespuesta) {

                                        $order->payment_complete( $_REQUEST['numeroTransaccion'] );

                                        $order->add_order_note('Pago exitoso desde PagosWeb' . 

                                                                '<br/>Numero de Transaccion: ' . $_REQUEST['numeroTransaccion'] .

                                                                '<br/>Codigo de Autorizacion: ' . $_REQUEST['codigoAutorizacion'] .

                                                                '<br/>Numero de Orden: ' . $_REQUEST['numeroOrden'] .

                                                                '<br/>Fecha: ' . $_REQUEST['fecha'] .

                                                                '<br/>Monto de Transaccion: ' . $_REQUEST['monto'] .

                                                                '<br/>Mensaje: ' . $_REQUEST['mensaje']);                                        

                                        $woocommerce->cart->empty_cart();
                                        $transauthorised = true;
                                        $this->msg['message'] = $this->success_message;
                                        $this->msg['class'] = 'success';
                                        $redirect_url = add_query_arg('order', $order->id, add_query_arg('key', $order->order_key, $order->get_checkout_order_received_url()));
                                        $this->web_redirect($redirect_url);
                                        exit();

                                    } else {
                                        $this->msg['class'] = 'error';
                                        $this->msg['message'] = $this->failed_message;
                                        $order->add_order_note($this->msg['message']);
                                        $order->update_status('failed');
                                    }

                                } else {
                                    $this->msg['class'] = 'error';
                                    $this->msg['message'] = $this->failed_message;
                                    $order->add_order_note($this->msg['message']);
                                    $order->add_order_note('Se ha producido un error al procesar al transaccion.');
                                    $order->update_status('failed');
                                    $redirect_url = add_query_arg('order', $order->id, add_query_arg('key', $order->order_key, $order->get_checkout_order_received_url()));
                                    $this->web_redirect($redirect_url);
                                    exit();
                                }
                            } else {
                                $woocommerce->add_error(__('Payment error:', 'wp-pagosweb') . 'Error!');
                                $this->msg['class'] = 'error';
                                $this->msg['message'] = $this->failed_message;
                                $order->add_order_note($this->msg['message']);
                                $order->update_status('failed');
                                exit();
                            }
                        }
                        if ($transauthorised == false) {
                            $order->update_status('failed');
                            $order->add_order_note($this->msg['message']);
                        }
                    } catch (Exception $e) {
                        $this->msg['class'] = 'error';
                        $this->msg['message'] = $this->failed_message;
                        exit();
                    }
                } else {
                    $this->msg['class'] = 'error';
                    $this->msg['message'] = $this->failed_message;
                    /*$order->add_order_note($this->msg['message']);
                    $order->add_order_note('Se ha producido un error al procesar al transaccion.');
                    $order->update_status('failed');
                    $redirect_url = add_query_arg('order', $order->id, add_query_arg('key', $order->order_key, $order->get_checkout_order_received_url()));
                    $this->web_redirect($redirect_url);
					*/
					$woocommerce->add_error(__('Payment error:', 'wp-pagosweb') . ' - Error al procesar la transaccion, intentelo nuevamente - ');
                    exit();

                }

            } else {
                $woocommerce->add_error(__('Payment error:', 'wp-pagosweb') . ' <<<<  ERROR  >>>>');
                exit();
            }
        }

        public function web_redirect($url) {
            echo "<html><head><script language=\"javascript\">
                <!--
                window.location=\"{$url}\";
                //-->
                </script>
                </head><body><noscript><meta http-equiv=\"refresh\" content=\"0;url={$url}\"></noscript></body></html>";
        }

        /**
         * Generate PagosWeb button link
         * */
        public function generate_pagosweb_form($order_id, $id_tarjeta, $cuotas, $cedula) {
            global $woocommerce;
            $order = new WC_Order($order_id);
            $redirect_url = (get_option('woocommerce_thanks_page_id') != '' ) ? $order->get_checkout_order_received_url() : get_site_url() . '/';
            $relay_url = add_query_arg(array('wc-api' => get_class($this), 'order_id' => $order_id), $redirect_url);
            $fechaTransaccion = date;
            $iv = base64_decode(substr($fechaTransaccion('ymdhis'), 1) . '=');
            $importeTransaccion = ($order->order_total * 100) / 122;
            $string = $this->id_cliente . /*$order->order_custom_fields['Tarjeta'][0] .*/ $id_tarjeta . $order->billing_first_name
                    . $order->billing_last_name . $cedula . $order->billing_email . $order->order_total
                    . $cuotas . $this->moneda . $order_id . $this->version . '0'
                    . $fechaTransaccion("Y-m-dh:i:s") . '1' . $importeTransaccion . $order_id;

            $key = base64_decode($this->security_token);
            $encryptedString = encryptNET3DES($key, $iv, $string);
            $pagosweb_args = array(
                'idCliente' => $this->id_cliente,
                'idTarjetaCredito' => $id_tarjeta, //$order->order_custom_fields['Tarjeta'][0],
                'primerNombre' => $order->billing_first_name,
                'primerApellido' => $order->billing_last_name,
                'email' => $order->billing_email,
                'valorTransaccion' => $order->order_total,
                'cantidadCuotas' => $cuotas,
                'moneda' => $this->moneda,
                'numeroOrden' => $order_id,
                'version' => $this->version,
                'fecha' => $fechaTransaccion("Y-m-dh:i:s"),
                'plan' => "0",
                'cedula' => $cedula,
                'consumidorFinal' => "1",
                'importeGravado' => $importeTransaccion,
                'numeroFactura' => $order_id,
                'transactionSecurityToken' => $encryptedString,
                'encriptado' => $string
            );

            $pagosweb_args_array = array();

            foreach ($pagosweb_args as $key => $value) {
                $pagosweb_args_array[] = "<input type='hidden' name='$key' value='$value'/>";
            }

            if ($this->mode == 'true') {
                $processURI = $this->testurl;
            } else {
                $processURI = $this->liveurl;
            }

            $html_form = '<form action="' . $processURI . '" method="post" id="authorize_payment_form">'
                    . implode('', $pagosweb_args_array)
                    . '<input type="submit" class="button" id="submit_authorize_payment_form" value="' . __('Pagar via PagosWeb', 'wp-pagosweb') . '" /> <a class="button cancel" href="' . $order->get_cancel_order_url() . '">' . __('Cancelar Orden &amp; limpiar carro', 'wp-pagosweb') . '</a>'
                    . '<script type="text/javascript">

                  jQuery(function(){
                     jQuery("body").block({
                           message: "<img src=\"' . $woocommerce->plugin_url() . '/assets/images/ajax-loader.gif\" alt=\"Redirigiendo…\" style=\"float:left; margin-right: 10px;\" />' . __('Gracias por su compra. Estamos rediregiendo a los servidores de PagosWeb.', 'wp-pagosweb') . '",
                           overlayCSS:
                        {
                           background:       "#ccc",
                           opacity:          0.6,
                           "z-index": "99999999999999999999999999999999"
                        },
                     css: {
                           padding:          20,
                           textAlign:        "center",
                           color:            "#555",
                           border:           "3px solid #aaa",
                           backgroundColor:  "#fff",
                           cursor:           "wait",
                           lineHeight:       "32px",
                           "z-index": "999999999999999999999999999999999"
                     }
                     });
                  jQuery("#submit_authorize_payment_form").click();
               });
               </script>
               </form>';

            return $html_form;
        }
    }

    function encryptNET3DES($key, $vector, $text) {
        $td = mcrypt_module_open(MCRYPT_3DES, '', MCRYPT_MODE_CBC, '');

        // Complete the key
        $key_add = 24 - strlen($key);
        $key .= substr($key, 0, $key_add);

        // Padding the text
        $text_add = strlen($text) % 8;

        for ($i = $text_add; $i < 8; $i++) {
            $text .= chr(8 - $text_add);
        }

        mcrypt_generic_init($td, $key, $vector);
        $encrypt64 = mcrypt_generic($td, $text);
        mcrypt_generic_deinit($td);
        mcrypt_module_close($td);

        // Return the encrypt text in 64 bits code
        return base64_encode($encrypt64);
    }

    /**
     * Add this Gateway to WooCommerce
     * */
    function woocommerce_add_genux_pagosweb_gateway($methods) {
        $methods[] = 'WC_Genux_PagosWeb';
        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'woocommerce_add_genux_pagosweb_gateway');
}