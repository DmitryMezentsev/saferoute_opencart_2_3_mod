<?php


require DIR_SYSTEM . 'library/ddelivery/Browser.php';


class ModelShippingDdelivery extends Model
{
    /**
     * Возвращает путь к API для обновления данных заказа в DDelivery
     *
     * @return string
     */
    private function getDDeliveryUpdateOrderAPI()
    {
        return 'https://ddelivery.ru/api/' . $this->config->get('ddelivery_api_key') . '/sdk/update-order.json';
    }

    /**
     * Отправляет запрос на обновление данных заказа в DDelivery SDK
     *
     * @param $values array Параметры запроса
     * @return array
     */
    private function sendDDeliveryUpdateOrderRequest($values)
    {
        $curl = curl_init($this->getDDeliveryUpdateOrderAPI());

        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($values));

        $response = json_decode(curl_exec($curl), true);
        curl_close($curl);

        return $response;
    }

    /**
     * Обновляет указанный параметр заказа в БД CMS
     *
     * @param $order_id int|string ID заказа
     * @param $param string Имя параметра в БД
     * @param $value mixed Значение параметра
     */
    private function updateOrder($order_id, $param, $value)
    {
        if (isset($value) && $value)
            $this->db->query("UPDATE " . DB_PREFIX . "order SET $param='$value' WHERE order_id='$order_id'");
    }

    /**
     * Обновляет ddelivery_id заказа и устанавливает флаг, что заказ был перенесен в ЛК DDelivery
     *
     * @param $order_id int|string ID заказа в БД CMS
     * @param $ddelivery_id int|string ID заказа DDelivery
     */
    private function setOrderDDeliveryCabinetID($order_id, $ddelivery_id)
    {
        if ($ddelivery_id)
        {
            $this->updateOrder($order_id, 'ddelivery_id', $ddelivery_id);
            $this->updateOrder($order_id, 'in_ddelivery_cabinet', 1);
        }
    }

    /**
     * Возвращает адрес для сохранения в заказе из данных виджета
     *
     * @param object Данные виджета
     * @return string
     */
    private function getAddress($widget_data)
    {
        if (!isset($widget_data->delivery->type)) return '';

        $address = '';

        if (intval($widget_data->delivery->type) === 1 && isset($widget_data->delivery->point->address))
        {
            // Адрес точки самовывоза
            $address = $widget_data->delivery->point->address;
        }
        elseif (isset($widget_data->contacts->address))
        {
            // Адрес клиента
            $a = $widget_data->contacts->address;

            if (isset($a->street)) $address .= $a->street . ', ';
            if (isset($a->house))  $address .= $a->house . ', ';
            if (isset($a->flat))   $address .= $a->flat;
        }

        return trim($address);
    }

    /**
     * Разбивает строку с ФИО на массив с фамилией в одной ячейке и именем-отчеством в другой
     *
     * @param $fullName string ФИО
     * @return array
     */
    private function splitFullName($fullName = '')
    {
        $result = [];

        $fullName = preg_split("/\s/", trim($fullName));

        // ФИО
        if (count($fullName) >= 3)
        {
            $result['firstName'] = $fullName[1] . ' ' . $fullName[2];
            $result['lastName'] = $fullName[0];
        }
        // ИФ
        elseif (count($fullName) === 2)
        {
            $result['firstName'] = $fullName[0];
            $result['lastName'] = $fullName[1];
        }
        // И
        else
        {
            $result['firstName'] = $fullName[0];
            $result['lastName'] = '';
        }

        return $result;
    }


    /**
     * Возвращает список возможных статусов заказа
     *
     * @return array
     */
    public function getOrderStatuses()
    {
        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "order_status WHERE language_id='" . $this->config->get('config_language_id') . "' ORDER BY name");

        $statuses = [];

        foreach ($query->rows as $status)
            $statuses[$status['order_status_id']] = $status['name'];

        return $statuses;
    }

    /**
     * Вызывается сразу после успешного создания заказа
     *
     * @param $order_id int|string ID заказа
     * @return boolean
     */
    public function onOrderCheckoutSuccess($order_id)
    {
        $this->load->model('checkout/order');

        if (!isset($_COOKIE['DDOrderData']) || !$order_id) return false;

        $dd_order_data = json_decode(urldecode($_COOKIE['DDOrderData']));

        // Сохраняем в заказе тот DDelivery ID, который был получен виджетом при создании заказа
        $this->updateOrder($order_id, 'ddelivery_id', $dd_order_data->id);

        if (isset($_COOKIE['DDWidgetData']))
        {
            $browser = (new Browser())->getBrowser();

            // Перекодирование в UTF-8 нужно для IE/Edge, без этого не работает
            $dd_widget_data_string = ($browser === Browser::BROWSER_IE || $browser === Browser::BROWSER_POCKET_IE || $browser === Browser::BROWSER_EDGE)
                ? mb_convert_encoding($_COOKIE['DDWidgetData'], 'utf-8', 'windows-1251')
                : $_COOKIE['DDWidgetData'];

            $dd_widget_data = json_decode(urldecode($dd_widget_data_string));

            // Сохраняем в заказе адрес...
            $this->updateOrder($order_id, 'shipping_address_1', $this->getAddress($dd_widget_data));
            // ...город
            if (isset($dd_widget_data->city->name))
                $this->updateOrder($order_id, 'shipping_city', $dd_widget_data->city->name);
            // ...индекс
            if (isset($dd_widget_data->contacts->address->index))
                $this->updateOrder($order_id, 'shipping_postcode', $dd_widget_data->contacts->address->index);
            // ...ФИО
            if (isset($dd_widget_data->contacts->fullName))
            {
                $fullName = $this->splitFullName($dd_widget_data->contacts->fullName);

                $this->updateOrder($order_id, 'shipping_firstname', $fullName['firstName']);
                $this->updateOrder($order_id, 'shipping_lastname', $fullName['lastName']);

                $this->updateOrder($order_id, 'firstname', $fullName['firstName']);
                $this->updateOrder($order_id, 'lastname', $fullName['lastName']);

                /*
                // todo: добавить сохранение этих данных, когда в виджет будет впилен эквайринг
                if (false)
                {
                    $this->updateOrder($order_id, 'payment_firstname', $fullName['firstName']);
                    $this->updateOrder($order_id, 'payment_lastname ', $fullName['lastName']);
                }
                */
            }
            // ...и телефон клиента из виджета
            if (isset($dd_widget_data->contacts->phone))
                $this->updateOrder($order_id, 'telephone', $dd_widget_data->contacts->phone);
        }

        // Получение заказа в CMS
        $order = $this->model_checkout_order->getOrder($order_id);

        // Отправка запроса к API SDK DDelivery
        $response = $this->sendDDeliveryUpdateOrderRequest([
            'id'             => $dd_order_data->id,
            'status'         => $order['order_status_id'],
            'cms_id'         => $order_id,
            'payment_method' => $order['payment_code'],
        ]);

        if ($response['status'] === 'ok')
        {
            if (isset($response['data']['cabinet_id']))
                $this->setOrderDDeliveryCabinetID($order_id, $response['data']['cabinet_id']);

            return true;
        }

        return false;
    }

    /**
     * Вызывается при изменении статуса заказа в CMS
     *
     * @param $order_id int|string ID заказа
     * @param $order_status_id int|string ID статуса заказа
     * @return boolean
     */
    public function onOrderStatusUpdate($order_id, $order_status_id)
    {
        $this->load->model('checkout/order');

        $order = $this->db->query("SELECT * FROM " . DB_PREFIX . "order WHERE order_id='" . $order_id . "'")->row;

        // Выполнять запрос к SDK DDelivery только если у заказа есть ID DDelivery
        // и заказ ещё не был передан в Личный кабинет
        if ($order['ddelivery_id'] && !$order['in_ddelivery_cabinet'])
        {
            // Отправка запроса к API SDK DDelivery
            $response = $this->sendDDeliveryUpdateOrderRequest([
                'id'             => $order['ddelivery_id'],
                'status'         => $order_status_id,
                'payment_method' => $order['payment_code'],
            ]);

            if ($response['status'] === 'ok')
            {
                if (isset($response['data']['cabinet_id']))
                    $this->setOrderDDeliveryCabinetID($order_id, $response['data']['cabinet_id']);

                return true;
            }
        }

        return false;
    }

    /**
     * @param $address array
     * @return array
     */
    public function getQuote($address)
    {
        $this->load->language('shipping/ddelivery');

        $query = $this->db->query(
            "SELECT * FROM " . DB_PREFIX . "zone_to_geo_zone WHERE geo_zone_id = '" . $this->config->get('ddelivery_geo_zone_id') . "' AND country_id = '" . $address['country_id'] . "' AND (zone_id = '" . $address['zone_id'] . "' OR zone_id = '0')"
        );

        if (!$this->config->get('ddelivery_geo_zone_id') || $query->num_rows)
        {
            $cost = 0;

            // Не знаю, нужно ли это. На всякий случай
            if (isset($_COOKIE['DDWidgetData']))
            {
                $dd_widget_data = json_decode(urldecode($_COOKIE['DDWidgetData']));

                // Курьерская и Почта России
                if (isset($dd_widget_data->delivery->total_price))
                    $cost = $dd_widget_data->delivery->total_price;
                // Самовывоз
                elseif (isset($dd_widget_data->delivery->point->price_delivery))
                    $cost = $dd_widget_data->delivery->point->price_delivery;
            }

            return [
                'code'  => 'ddelivery',
                'title' => '',
                'quote' => [
                    'ddelivery' => [
                        'code'         => 'ddelivery.ddelivery',
                        'title'        => $this->language->get('text_title'),
                        'cost'         => $cost,
                        'tax_class_id' => 0,
                        'ddelivery'	   => 'true',
                        'text'         => '',
                    ],
                ],
                'sort_order' => $this->config->get('ddelivery_sort_order'),
                'error' => false,
            ];
        }

        return [];
    }
}