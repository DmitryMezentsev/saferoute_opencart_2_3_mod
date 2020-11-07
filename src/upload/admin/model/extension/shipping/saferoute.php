<?php

class ModelExtensionShippingSaferoute extends Model
{
    const PVZ = 1;
    const COURIER = 2;
    const PRF = 3;


    public function enrichData($data, $orderId) {
        $order = $this->getData($orderId);
        if ($order === false) {
            return $data;
        }
        $data['saferouteDeliveryType'] = (!empty($order->row['saferoute_delivery_type']))
            ? $this->mapDeliveryType( (int) $order->row['saferoute_delivery_type'])
            : '';

        $data['saferouteDeliveryCompany'] = (!empty($order->row['saferoute_delivery_company']))
            ? $order->row['saferoute_delivery_company']
            : '';

        return $data;
    }

    public function mapDeliveryType($code) {
        $deliveryTypeList = array(
            self::PVZ => 'Самовывоз',
            self::COURIER => 'Курьерская доставка',
            self::PRF => 'Почта РФ',
        );

        if (array_key_exists($code, $deliveryTypeList)) {
            return $deliveryTypeList[$code];
        } else {
            return false;
        }
    }
    public function getData($orderId) {
        $query = $this->db->query("
SELECT saferoute_delivery_type, saferoute_delivery_company 
FROM `" . DB_PREFIX . "order` WHERE order_id = '" . (int) $orderId . "'"
        );

        return $query->num_rows ? $query : false;
    }
}