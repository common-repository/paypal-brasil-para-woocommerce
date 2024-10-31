<?php

if (!defined('ABSPATH')) {
    exit;
}

class PayPal_Brasil_API_PPP_Activate extends PayPal_Brasil_API_Handler
{

    public function __construct()
    {
        add_filter('paypal_brasil_handlers', array($this, 'add_handlers'));
    }

    public function add_handlers($handlers)
    {
        $handlers['ppp_activation_update'] = array(
            'callback' => array($this, 'handle'),
            'method' => 'POST',
        );

        return $handlers;
    }

    /**
     * Handle the request.
     */
    public function handle()
    {
        try {
            if(!$this->verify_authenticate()){
                $this->send_error_response('Not authorized',array(),403);
            }

            $json_data = file_get_contents('php://input');
            $post_data = json_decode($json_data, true);
            $activeGateway = sanitize_text_field($post_data['active']);

            if ($activeGateway) {
             
                update_option('active_payment_ppp', $activeGateway);
                
                $this->send_success_response(__('PPP - Payment gateway updated', "paypal-brasil-para-woocommerce"), array("enabled" => boolval($activeGateway)));
            } else {
                update_option('active_payment_ppp', $activeGateway);
                
                $pplus_settings = get_option('woocommerce_paypal-brasil-plus-gateway_settings');
                $pplus_settings['enabled'] = false;
                update_option('woocommerce_paypal-brasil-plus-gateway_settings', $pplus_settings);
                
                $this->send_success_response(__('PPP - Payment gateway updated', "paypal-brasil-para-woocommerce"), array("enabled" => boolval($activeGateway)));
            }
        } catch (Exception $ex) {
            $this->send_error_response('Payment gateway not activated Error: ' . $ex->getMessage());
        }
    }

    public function verify_authenticate()
    {
        $data = array(
            'uuid' => get_option('plugin_id'),
            'store_url' => home_url(),
			"token_authentication_hash" => get_option('token_authentication_hash')
        );

        // Inicializar a sessão cURL
        $ch = curl_init();

        // Definir as opções da requisição cURL
        curl_setopt($ch, CURLOPT_URL, 'https://paypalpcpnuvem.com/verify');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));


        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            $this->send_error_response('Not authorized',array(),403);
        }

        
        $response_code = curl_getinfo($ch,CURLINFO_HTTP_CODE);
        
        curl_close($ch);

        if($response_code == 200){
            return true;
        }

        return false;

    }

}

new PayPal_Brasil_API_PPP_Activate();