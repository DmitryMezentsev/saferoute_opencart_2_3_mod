<?php

class ModelExtensionShippingSaferoute extends Model
{
    const PICKUP  = 1;
    const COURIER = 2;
    const POST    = 3;

    /**
     * @param $data array
     * @param $orderId int|string
     * @return array
     */
    public function enrichData(array $data, $orderId) {
        $order = $this->getData($orderId);
        if (!$order) return $data;
        
        $data['saferouteDeliveryType'] = (!empty($order->row['saferoute_delivery_type']))
            ? $this->mapDeliveryType((int) $order->row['saferoute_delivery_type'])
            : '';

        $data['saferouteDeliveryCompany'] = (!empty($order->row['saferoute_delivery_company']))
            ? $order->row['saferoute_delivery_company']
            : '';

        return $data;
    }

    /**
     * @param $code int
     * @return string
     */
    public function mapDeliveryType($code) {
        $deliveryTypeList = [
            self::PICKUP  => 'Самовывоз',
            self::COURIER => 'Курьерская доставка',
            self::POST    => 'Почта РФ',
        ];

        return (array_key_exists($code, $deliveryTypeList))
            ? $deliveryTypeList[$code]
            : '';
    }
    
    /**
     * @param $orderId int|string
     * @return mixed
     */
    public function getData($orderId) {
        $query = $this->db->query(
            "SELECT saferoute_delivery_type, saferoute_delivery_company FROM `" . DB_PREFIX . "order` WHERE order_id = '" . $orderId . "'"
        );

        return $query->num_rows ? $query : false;
    }
}
