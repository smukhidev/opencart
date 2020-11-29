<?php
class ControllerExtensionPaymentShurjopay4 extends Controller {

	const SHURJOPAY_LIVE_URL = 'https://xxxxx.com/x.php';
	const SHURJOPAY_DECRYPT_LIVE_URL = 'https://xxxx.com/xxx/x.php';
    const SHURJOPAY_SANDBOX_URL = 'https://xxxx.com/xx.php';
    const SHURJOPAY_DECRYPT_SANDBOX_URL = 'https://xxx.com/xx/x.php';

	public function index() {
		$this->load->model('checkout/order');

		$this->load->language('extension/payment/shurjopay4');

		$data['button_confirm'] = $this->language->get('button_confirm');
		
		$data['action'] = $this->url->link('extension/payment/shurjopay4/curl');
		$data['returnUrl'] = $this->url->link('extension/payment/shurjopay4/callback');
		$data['return_url'] = $this->url->link('checkout/success');
		$data['cancel_url'] = $this->url->link('checkout/checkout', '', true);
			

        $data['pay_to_username'] = $this->config->get('payment_shurjopay4_merchant_username');
        $data['pay_to_password'] = $this->config->get('payment_shurjopay4_merchant_password');
        $data['uniq_transaction_key'] = $this->config->get('payment_shurjopay4_merchant_uniq_transaction_key').uniqid();

        $data['userIP'] = $this->config->get('payment_shurjopay4_merchant_userIP');
        $data['paymentOption'] = $this->config->get('payment_shurjopay4_merchant_paymentOption');

        $data['sandbox'] = $this->config->get('payment_shurjopay4_merchant_sandbox');
        
		$data['description'] = $this->config->get('config_name');
		$data['transaction_id'] = $this->session->data['order_id'];
		

		
		$data['language'] = $this->session->data['language'];
		$data['logo'] = $this->config->get('config_url') . 'image/' . $this->config->get('config_logo');

		$order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);

		$data['amount'] = $this->currency->format($order_info['total'], $order_info['currency_code'], $order_info['currency_value'], false);
		$data['currency'] = $order_info['currency_code'];
		$data['order_id'] = $this->session->data['order_id'];

		return $this->load->view('extension/payment/shurjopay4', $data);
	}


    public function callback()
    {
        $this->load->model('checkout/order');

        if(count($_POST) > 0) 
        {
        	$response_encrypted = $_POST['spdata'];
            $response_decrypted = $this->response_decrypte($response_encrypted);            
            $data = simplexml_load_string($response_decrypted) or die("Error: Cannot create object");

            $sp_code = $data->spCode;
            $order_id = $this->session->data['order_id'];

            $orderHistoryData = "Transaction ID:<b>"
                                .$data->txID
                                ."</b><br>Bank ID:<b>"
                                .$data->bankTxID
                                ."</b><br>Payment Method:<b>"
                                .$data->paymentOption."</b>";

            switch($sp_code) {

                case '000':     
                    $res = array('status'=>true,'msg'=>'Your Transaction is Success');
                    $this->model_checkout_order->addOrderHistory($order_id, $this->config->get('payment_shurjopay4_order_status_id'), $orderHistoryData, true);						
                    break;

                case '001':      
                	$res = array('status'=>false,'msg'=>'Your Transaction Failed');
                    $this->model_checkout_order->addOrderHistory($order_id, $this->config->get('config_order_status_id'), $orderHistoryData, true);                     
                    break;
            }

            if($res['status'])
            {
                $this->session->data['success'] = $res['msg'];
                header("location: ".$this->url->link('checkout/success'));
                die();
            } else {
            	$this->session->data['error'] = $res['msg'];                
                header("location: ".$this->url->link('checkout/checkout', '', true));
                die();
            }
        }
    }

    public function response_decrypte($response_encrypted)
    {

        if($this->config->get('payment_shurjopay4_merchant_sandbox'))
        {
            $url = self::SHURJOPAY_DECRYPT_SANDBOX_URL;
        }   
        else
        {
            $url = self::SHURJOPAY_DECRYPT_LIVE_URL;
        } 
    	$shurjopay_decryption_url = self::SHURJOPAY_DECRYPT_LIVE_URL;      	
      	$payment_url = $shurjopay_decryption_url.'?data='.$response_encrypted;
      	$ch = curl_init();  
      	curl_setopt($ch,CURLOPT_URL,$payment_url);
      	curl_setopt($ch,CURLOPT_RETURNTRANSFER, true);
      	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);    
      	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
      	$response_decrypted = curl_exec($ch);	
      	curl_close ($ch);
      	return $response_decrypted;
    }


	public function curl()
    {
        if ($this->request->server['REQUEST_METHOD'] == 'POST'){
            if($this->config->get('payment_shurjopay4_merchant_sandbox'))
            {
                $url = self::SHURJOPAY_SANDBOX_URL;
            }   
            else
            {
                $url = self::SHURJOPAY_LIVE_URL;
            } 

            $ch = curl_init();            
            $xml_data = 'spdata=<?xml version="1.0" encoding="utf-8"?>
                            <shurjoPay><merchantName>'.$this->request->post['pay_to_username'].'</merchantName>
                            <merchantPass>'.$this->request->post['pay_to_password'].'</merchantPass>
                            <userIP>'.$this->request->post['userIP'].'</userIP>
                            <uniqID>'.$this->request->post['uniq_transaction_key'].'</uniqID>
                            <totalAmount>'.$this->request->post['amount'].'</totalAmount>
                            <paymentOption>'.$this->request->post['paymentOption'].'</paymentOption>
                            <returnURL>'.$this->request->post['returnUrl'].'</returnURL></shurjoPay>';
            
            curl_setopt($ch,CURLOPT_URL,$url);
            curl_setopt($ch,CURLOPT_POST, 1);
            curl_setopt($ch,CURLOPT_POSTFIELDS,$xml_data);
            curl_setopt($ch,CURLOPT_RETURNTRANSFER, true);            
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            $response = curl_exec($ch);
            print_r($response);
            curl_close ($ch);
        }
    }
}