<?php


require_once DIR_SYSTEM . 'library/saferoute/SafeRouteWidgetApi.php';


class ControllerModuleSaferoute extends Controller
{
    /**
     * Отправляет в браузер данные в формате JSON
     *
     * @param $data array Данные для отправки
     */
    private function sendJSON($data = [])
    {
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($data));
    }

    /**
     * Возвращает значение GET-параметра
     *
     * @param $name string Имя параметра
     * @return mixed
     */
    private function getParam($name)
    {
        return isset($this->request->get[$name]) ? $this->request->get[$name] : null;
    }

    /**
     * Возвращает значение POST-параметра
     *
     * @param $name string Имя параметра
     * @return mixed
     */
    private function postParam($name)
    {
        return isset($this->request->post[$name]) ? $this->request->post[$name] : null;
    }

    /**
     * Проверяет, совпадает ли переданный API-ключ c API-ключом, указанным в настройках модуля SafeRoute
     *
     * @param $key string API-ключ для проверки
     * @return boolean
     */
    private function checkApiKey($key)
    {
        return ($key && $key === $this->config->get('saferoute_api_key'));
    }

    /**
     * Наполняет массив атрибутами товара
     *
     * @param $product_id int ID товара
     * @param $attributes array Массив атрибутов
     */
    private function getProductAttributes($product_id, &$attributes)
    {
        $this->load->model('catalog/product');

        $attrs = $this->model_catalog_product->getProductAttributes($product_id);

        foreach($attrs as $attrs_group)
        {
            foreach($attrs_group['attribute'] as $attr)
            {
                if (isset($attributes[$attr['name']]))
                    $attributes[$attr['name']] = trim($attr['text']);
            }
        }
    }


    /**
     * Возвращает настройки сайта / модуля, необходимые для работы виджета
     */
    public function get_settings()
    {
        $data = [];

        // Язык сайта
        $data['lang'] = $this->language->get('code');

        $this->sendJSON($data);
    }

    /**
     * Возвращает содержимое корзины для передачи в виджет
     */
    public function get_cart()
    {
        $data = [];

        // Массив товаров корзины
        $data['products'] = [];
        // Общий вес товаров корзины
        $data['weight'] = $this->cart->getWeight();

        foreach ($this->cart->getProducts() as $product)
        {
            $attributes = [
                'sku'     => '', // Артикул
                'barcode' => '', // Штрих-код
                'vat'     => '', // НДС
            ];

            $this->getProductAttributes($product['product_id'], $attributes);

            $data['products'][] = [
                'name'       => $product['name'],
                'vendorCode' => $attributes['sku'],
                'barcode'    => $attributes['barcode'],
                'nds'        => $attributes['vat'] ? (int) $attributes['vat'] : null,
                'price'      => $product['price'],
                'count'      => (int) $product['quantity'],
            ];
        }

        $this->sendJSON($data);
    }

    /**
     * API для виджета
     */
    public function widget_api()
    {
        $widgetApi = new SafeRouteWidgetApi();

        $widgetApi->setApiKey($this->config->get('saferoute_api_key'));

        $widgetApi->setMethod($_SERVER['REQUEST_METHOD']);
        $widgetApi->setData(isset($_REQUEST['data']) ? $_REQUEST['data'] : []);

        $this->response->setOutput($widgetApi->submit($_REQUEST['url']));
    }

    /**
     * API для взаимодействия с SDK SafeRoute
     */
    public function api()
    {
        // Проверка API-ключа, передаваемого в запросе
        if ($this->checkApiKey($this->getParam('k')))
        {
            $r = $this->request->get['route'];

            // Список статусов заказа
            if (strpos($r, 'statuses.json'))
            {
                $this->load->model('shipping/saferoute');
                $this->sendJSON($this->model_shipping_saferoute->getOrderStatuses());
            }
            // Список способов оплаты
            elseif (strpos($r, 'payment-methods.json'))
            {
                $this->load->model('extension/extension');

                $payment_extensions = $this->model_extension_extension->getExtensions('payment');
                $payment_methods = [];

                foreach ($payment_extensions as $payment_extension)
                {
                    $this->load->language('extension/payment/' . $payment_extension['code']);
                    $payment_methods[$payment_extension['code']] = $this->language->get('text_title');
                }

                $this->sendJSON($payment_methods);
            }
            // Уведомления об изменениях статуса заказа в SafeRoute
            elseif (strpos($r, 'traffic-orders.json'))
            {
                $this->load->model('checkout/order');
                $this->load->language('api/order');

                // Данные запроса
                $id = $this->postParam('id');
                $status_cms = $this->postParam('status_cms');
                $track_number = $this->postParam('track_number');

                // id и status_cms обязательно должны быть переданы
                if ($id && $status_cms)
                {
                    // Сохранение трекинг-номера заказа
                    $this->db->query("UPDATE `" . DB_PREFIX . "order` SET tracking='$track_number' WHERE saferoute_id='$id'");

                    // Получение ID заказа в CMS
                    $order_id = $this->db->query("SELECT order_id FROM `" . DB_PREFIX . "order` WHERE saferoute_id='$id'")->row['order_id'];

                    // Добавление нового статуса в историю статусов заказа
                    $this->model_checkout_order->addOrderHistory($order_id, $status_cms, '', true);
                }
                else
                {
                    header($this->request->server['SERVER_PROTOCOL'] . ' 400 Bad Request');
                }
            }
            // Неправильный запрос
            else
            {
                header($this->request->server['SERVER_PROTOCOL'] . ' 404 Not Found');
            }
        }
        // Неправильный API-ключ
        else
        {
            header($this->request->server['SERVER_PROTOCOL'] . ' 401 Unauthorized');
        }
    }
}