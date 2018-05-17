<?php


class ControllerExtensionShippingDdelivery extends Controller
{
    private $error = array();

    public function index()
    {
        $this->load->language('shipping/ddelivery');

        $this->load->model('setting/setting');
        $this->load->model('localisation/geo_zone');


        if (($this->request->server['REQUEST_METHOD'] === 'POST') && $this->validate())
        {
            $this->model_setting_setting->editSetting('ddelivery', $this->request->post);

            $this->session->data['success'] = $this->language->get('text_success');

            $this->response->redirect($this->url->link('extension/extension', 'token=' . $this->session->data['token'] . '&type=shipping', true));
        }


        $this->document->setTitle($this->language->get('heading_title'));


        $data = [];

        $data['heading_title'] = $this->language->get('heading_title');

        $data['text_edit']        = $this->language->get('text_edit');
        $data['text_enabled']     = $this->language->get('text_enabled');
        $data['text_disabled']    = $this->language->get('text_disabled');
        $data['text_all_zones']   = $this->language->get('text_all_zones');

        $data['entry_geo_zone']   = $this->language->get('entry_geo_zone');
        $data['entry_status']     = $this->language->get('entry_status');
        $data['entry_sort_order'] = $this->language->get('entry_sort_order');
        $data['entry_api_key']    = $this->language->get('entry_api_key');

        $data['button_save']      = $this->language->get('button_save');
        $data['button_cancel']    = $this->language->get('button_cancel');
        $data['error_warning']    = isset($this->error['warning']) ? $this->error['warning'] : '';

        $data['breadcrumbs'] = [
            [
                'text' => $this->language->get('text_home'),
                'href' => $this->url->link('common/dashboard', 'token=' . $this->session->data['token'], true),
            ],
            [
                'text' => $this->language->get('text_extensions'),
                'href' => $this->url->link('extension/extension', 'token=' . $this->session->data['token'] . '&type=shipping', true),
            ],
            [
                'text' => $this->language->get('heading_title'),
                'href' => $this->url->link('extension/shipping/ddelivery', 'token=' . $this->session->data['token'], true),
            ],
        ];

        $data['action'] = $this->url->link('extension/shipping/ddelivery', 'token=' . $this->session->data['token'], true);
        $data['cancel'] = $this->url->link('extension/extension', 'token=' . $this->session->data['token'] . '&type=shipping', true);

        $data['geo_zones'] = $this->model_localisation_geo_zone->getGeoZones();

        $data['ddelivery_geo_zone_id'] = isset($this->request->post['ddelivery_geo_zone_id'])
            ? $this->request->post['ddelivery_geo_zone_id']
            : $this->config->get('ddelivery_geo_zone_id');

        $data['ddelivery_api_key'] = isset($this->request->post['ddelivery_api_key'])
            ? $this->request->post['ddelivery_api_key']
            : $this->config->get('ddelivery_api_key');

        $data['ddelivery_status'] = isset($this->request->post['ddelivery_status'])
            ? $this->request->post['ddelivery_status']
            : $this->config->get('ddelivery_status');

        $data['ddelivery_sort_order'] = isset($this->request->post['ddelivery_sort_order'])
            ? $this->request->post['ddelivery_sort_order']
            : $this->config->get('ddelivery_sort_order');

        $data['header']      = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer']      = $this->load->controller('common/footer');


        $this->response->setOutput($this->load->view('extension/shipping/ddelivery', $data));
    }

    protected function validate()
    {
        if (!$this->user->hasPermission('modify', 'extension/shipping/ddelivery'))
            $this->error['warning'] = $this->language->get('error_permission');

        return !$this->error;
    }
}