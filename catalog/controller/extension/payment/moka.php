<?php

class ControllerExtensionPaymentMoka extends Controller
{
    public function index()
    {
        $this->load->language('extension/payment/moka');

        if (!isset($this->session->data['order_id'])) {
            return false;
        }

        $order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);

        $data['checkout'] = html_entity_decode($this->url->link('extension/payment/moka/checkout', '', true), ENT_COMPAT, "UTF-8");

        return $this->load->view('extension/payment/moka', $data);
    }

    public function checkout()
    {
        $this->load->library('moka');
        $this->load->model('checkout/order');
        $this->load->model('setting/setting');

        $this->setCookieSameSite('OCSESSID', $_COOKIE['OCSESSID'], time() + 86400, "/", $_SERVER['SERVER_NAME'], true, true);

        $order_id = $this->session->data['order_id'];
        $order_info = $this->model_checkout_order->getOrder($order_id);
        $products = $this->cart->getProducts();

        $dealer_code = $this->config->get('payment_moka_dealer_code');
        $username = $this->config->get('payment_moka_username');
        $password = $this->config->get('payment_moka_password');
        $api_environment = $this->config->get('payment_moka_api_environment');

        if ($api_environment == 'test') {
            $base_url = 'https://service.refmoka.com';
        } else {
            $base_url = '';
        }

        $moka = new \Moka\MokaClient([
            'dealerCode' => $dealer_code,
            'username' => $username,
            'password' => $password,
            'baseUrl' => $base_url
        ]);

        $software = 'OPENCART';
        $order_info['payment_address'] = $order_info['payment_address_1'] . ' ' . $order_info['payment_address_2'];
        $callback_url = html_entity_decode($this->url->link('extension/payment/moka/callback', '', true), ENT_COMPAT, "UTF-8");

        $payment = new Moka\Model\CreatePaymentRequest();
        $payment->setCardHolderFullName('John Doe');
        $payment->setCardNumber('5269551122223339');
        $payment->setExpMonth('12');
        $payment->setExpYear('2022');
        $payment->setCvcNumber('000');
        $payment->setAmount($order_info['total']);
        $payment->setCurrency('TL');
        $payment->setInstallmentNumber(1);
        $payment->setClientIp('192.168.1.116');
        $payment->setOtherTrxCode($order_id);
        $payment->setIsPoolPayment(0);
        $payment->setIsTokenized(0);
        $payment->setIntegratorId(0);
        $payment->setSoftware($software);
        $payment->setIsPreAuth(0);
        $payment->setRedirectUrl($callback_url);

        $buyer = new Moka\Model\Buyer;
        $buyer->setBuyerFullName($order_info['firstname'] . ' ' . $order_info['lastname']);
        $buyer->setBuyerGsmNumber('5551110022');
        $buyer->setBuyerEmail('email@email.com');
        $buyer->setBuyerAddress($order_info['payment_address']);

        $payment->setBuyerInformation($buyer);

        $basketProducts = array();

        foreach ($products as $product) {
            $basketProduct = new \Moka\Model\BasketProduct();
            $basketProduct->setProductId($product['product_id']);
            $basketProduct->setProductCode($product['model']);
            $basketProduct->setUnitPrice($product['price']);
            $basketProduct->setQuantity($product['quantity']);

            $basketProducts[] = $basketProduct;
        }

        $shipping = $this->shippingInfo();

        if (!empty($shipping) && $shipping['cost'] && $shipping['cost'] != '0.00') {
            $basketProduct = new \Moka\Model\BasketProduct();
            $basketProduct->setProductId(0);
            $basketProduct->setProductCode($shipping['title']);
            $basketProduct->setUnitPrice($shipping['cost']);
            $basketProduct->setQuantity(1);

            $basketProducts[] = $basketProduct;
        }

        $payment->setBasketProduct($basketProducts);

        $response = $moka->payments()->createThreeds($payment);

        // $response->getData();
        // $response->getResultCode();
        // $response->getResultMessage();
        // $response->getException();

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($response->getData()));
    }

    public function callback()
    {
        print_r($this->request->post);
    }

    private function shippingInfo()
    {
        if (isset($this->session->data['shipping_method'])) {
            $shipping_info = $this->session->data['shipping_method'];
        } else {

            $shipping_info = false;
        }

        if ($shipping_info) {
            if ($shipping_info['tax_class_id']) {
                $shipping_info['tax'] = $this->tax->getRates($shipping_info['cost'], $shipping_info['tax_class_id']);
            } else {
                $shipping_info['tax'] = false;
            }
        }

        return $shipping_info;
    }

    private function setCookieSameSite($name, $value, $expire, $path, $domain, $secure, $httponly)
    {
        if (PHP_VERSION_ID < 70300) {
            setcookie($name, $value, $expire, "$path; samesite=None", $domain, $secure, $httponly);
        } else {
            setcookie($name, $value, [
                'expires' => $expire,
                'path' => $path,
                'domain' => $domain,
                'samesite' => 'None',
                'secure' => $secure,
                'httponly' => $httponly
            ]);
        }
    }
}
