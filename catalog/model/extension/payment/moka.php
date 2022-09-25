<?php

class ModelExtensionPaymentMoka extends Model
{
    public function getMethod($address, $total) {
		$this->load->language('extension/payment/moka');

        $payment_moka_geo_zone_id   = $this->config->get('payment_iyzico_geo_zone_id');
		$payment_moka_geo_zone_id   = $this->db->escape($payment_moka_geo_zone_id);
		$address_country_id         = $this->db->escape($address['country_id']);
		$address_zone_id 			= $this->db->escape($address['zone_id']);

		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "zone_to_geo_zone` WHERE `geo_zone_id` = '" . $payment_moka_geo_zone_id . "' AND `country_id` = '" . $address_country_id . "' AND (`zone_id` = '" . $address_zone_id . "' OR `zone_id` = '0')");

		if ($this->config->get('payment_moka_total') > $total) {
			$status = false;
		} elseif (!$this->config->get('payment_moka_geo_zone_id')) {
			$status = true;
		} elseif ($query->num_rows) {
			$status = true;
		} else {
			$status = false;
		}

		$method_data = array();

		if ($status) {
			$method_data = array(
				'code'       => 'moka',
				'title'      => $this->language->get('text_title'),
				'terms'      => '',
				'sort_order' => $this->config->get('payment_moka_sort_order')
			);
		}

		return $method_data;
	}
}
