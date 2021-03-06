<?php
class ControllerPaymentBluesnap extends Controller {
	
	public function __construct($registry) {
                parent::__construct($registry);
                $this->registry = $registry;
                require_once(DIR_SYSTEM . "library/payment/bluesnap.php");
                $this->bluesnap = new Bluesnap($registry);
        }

	public function form() { 
                $this->load->language('payment/bluesnap');
                $this->data['bluesnap_url'] = $this->bluesnap->get_url();
                $this->data['bluesnap_config_error'] = 0;


		$this->data['entry_firstname'] = $this->language->get('entry_firstname');
                $this->data['placeholder_firstname'] = $this->language->get('placeholder_firstname');
                $this->data['error_firstname'] = $this->language->get('error_firstname');
 
               	$this->data['entry_lastname'] = $this->language->get('entry_lastname');
                $this->data['placeholder_lastname'] = $this->language->get('placeholder_lastname');
                $this->data['error_lastname'] = $this->language->get('error_lastname');

                $this->data['entry_card_number'] = $this->language->get('entry_card_number');
                $this->data['placeholder_card_number'] = $this->language->get('placeholder_card_number');
                $this->data['error_card_number'] = $this->language->get('error_card_number');

               	$this->data['entry_expiry_date'] = $this->language->get('entry_expiry_date');
                $this->data['placeholder_expiry_date'] = $this->language->get('placeholder_expiry_date');
                $this->data['error_expiry_date'] = $this->language->get('error_expiry_date');

               	$this->data['entry_security_code'] = $this->language->get('entry_security_code');
                $this->data['placeholder_security_code'] = $this->language->get('placeholder_security_code');
                $this->data['error_security_code'] = $this->language->get('error_security_code');

			$this->data['button_pay_now']	= $this->language->get('button_pay_now');
                try {
                        unset($this->session->data['bluesnap_hosted_payments_field']);
			$pfToken = $this->bluesnap->get_payment_field_token();
                        $this->session->data['bluesnap_hosted_payments_field'] 	= $pfToken;
			$this->session->data['bluesnap_fraud_session_id']	= $this->bluesnap->generate_fraud_session_id();
                        $this->data['bluesnap_hosted_payments_field'] = $pfToken;
			$this->data['bluesnap_fraud_session_id']	= $this->session->data['bluesnap_fraud_session_id'];
                } catch (Exception $e) {
                        $this->data['bluesnap_config_error'] = 1;
                        $this->data['bluesnap_config_error_message'] =  $this->language->get("bluesnap_config_error_message");
                }
                $this->data['continue'] = $this->url->link('checkout/success');
			if (file_exists(DIR_TEMPLATE . $this->config->get('config_template') . '/template/payment/bluesnap_form.tpl')) {
				$this->template = $this->config->get('config_template') . '/template/payment/bluesnap_form.tpl';
			} else {
				$this->template = 'default/template/payment/bluesnap_form.tpl';
			}	
			$this->response->setOutput($this->render());
	}

	public function index() {
		$this->data['button_confirm'] = $this->language->get('button_confirm');
		$this->load->language('payment/bluesnap');
		$this->data['button_confirm'] = $this->language->get('button_confirm_bluesnap');
		$this->data['bluesnap_url'] = $this->bluesnap->get_url();
		if (file_exists(DIR_TEMPLATE . $this->config->get('config_template') . '/template/payment/bluesnap.tpl')) {
			$this->template = $this->config->get('config_template') . '/template/payment/bluesnap.tpl';
		} else {
			$this->template = 'default/template/payment/bluesnap.tpl';
		}	
		$this->render(); 
	}

	public function confirm() {
		$this->load->language('payment/bluesnap');
		$result_code = 0;
		$result_msg_code = '';
		$result_msg = '';
		$redirect_url = '';
		if ($this->session->data['payment_method']['code'] != 'bluesnap') {
			$result_code = 1;
			$result_msg_code = "MB-BP-001";
			$result_msg = $this->language->get('error_not_bluesnap_payment_method');
		} else if (!isset($this->session->data['bluesnap_hosted_payments_field']) || !isset($this->session->data['bluesnap_fraud_session_id'])) {
			$result_code = 1;
			$result_msg_code = "MB-BP-002";
                        $result_msg = $this->language->get('error_not_bluesnap_hosted_payments_field_missing');
		} else if (new DateTime() > new DateTime($this->session->data['bluesnap_hosted_payments_field']['EXPIRY_DATE_TIME'])) {
			$result_code = 1;
			$result_msg_code = "MB-BP-003";
			$result_msg = $this->language->get('error_bluesnap_hosted_payment_field_expired');
			$redirect_url = $this->url->link('checkout/checkout');
		} else if (
			!isset($this->request->post['ccType']) || strlen($this->request->post['ccType']) == 0
			|| !isset($this->request->post['last4Digits']) || strlen($this->request->post['last4Digits']) == 0
			|| !isset($this->request->post['expiryDate']) || strlen($this->request->post['expiryDate']) == 0
		) {
			$result_code = 1;
			$result_msg_code = "MB-BP-004";
			$result_msg = $this->language->get('error_missing_credit_card_details'); 
		} else if ( !isset($this->request->post['cardholderFirstName']) || strlen($this->request->post['cardholderFirstName']) == 0) {
			$result_code = 1;
			$result_msg_code = "MB-BP-005";
			$result_msg = $this->language->get('error_firstname_on_card_missing');
		}  else if ( !isset($this->request->post['cardholderLastName']) || strlen($this->request->post['cardholderLastName']) == 0) {
                        $result_code = 1;
			$result_msg_code = "MB-BP-006";
                        $result_msg = $this->language->get('error_lastname_on_card_missing');
		} else if (!isset($this->session->data['order_id'])) {
			$result_code = 1;		
			$result_msg_code = "MB-BP-007";
			$result_msg = $this->language->get('error_orderid_not_found');
		} else { 
			$hosted_payments_field = $this->session->data['bluesnap_hosted_payments_field'];
			$fraud_session_id = $this->session->data['bluesnap_fraud_session_id'];
			$ccType = $this->request->post['ccType'];
			$last4Digits = $this->request->post['last4Digits'];
			$expiryDate = $this->request->post['expiryDate'];
			$order_id = $this->session->data['order_id'];
			$this->load->model('checkout/order');
			$order_info = $this->model_checkout_order->getOrder($order_id);
			$order_total_query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "order_total` WHERE code='total' and order_id = '" . (int)$order_id . "' ORDER BY sort_order ASC");
			$order_total = $order_total_query->row['value'];
			$credit_card_info = $this->request->post;
			$result = $this->bluesnap->auth_capture($order_info, $order_total, $hosted_payments_field, $fraud_session_id, $credit_card_info);
			$result_code = $result['result_code'];
			$result_msg = $result['result_msg'];
			$result_msg_code = $result['result_msg_code'];
			if ($result_code == 0)
			{
				$this->model_checkout_order->confirm($this->session->data['order_id'], $this->config->get('free_checkout_order_status_id'));
				// $this->model_checkout_order->addOrderHistory($this->session->data['order_id'], $this->config->get('bluesnap_order_status_id'));
				unset($this->session->data['bluesnap_hosted_payments_field']);
				$redirect_url =  $this->url->link('checkout/success');
			}
		}

		$json = array (
			'result_code' => $result_code,
			'result_msg_code' => $result_msg_code, 
			'result_msg' => $result_msg,
		);
		if ($redirect_url != '')
			$json['redirect_url'] = $redirect_url;
		$this->response->addHeader('Content-Type: application/json');
                $this->response->setOutput(json_encode($json));
	}
}
