<?php
class ControllerExtensionModuleFacetFilter extends Controller {
	private $error = [];

	public function index() {
		$this->document->addScript('view/javascript/niftyAutocomplete.js');
		$this->document->addStyle('view/stylesheet/niftyAutocomplete.css');
		$this->load->language('extension/module/facet_filter');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/setting');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {

			// Delete previous cache
			$store_id = $this->session->data['store_id'];
			$this->load->model('localisation/language');
			$languages = $this->model_localisation_language->getLanguages();

			// Delete existing category cache
			foreach ($this->request->post['module_facet_filter_settings']['category'] ?? [] as $category_id => $category) {
				foreach ($languages as $language) {
					$language_id = $language['language_id'];
					$cacheName = "category.store_{$store_id}.language_{$language_id}." . (floor($category_id / 100)) . ".filters_{$category_id}";
					$this->cache->delete($cacheName);
				}
			}

			// Delete category cache if category was removed from settings
			$settings = $this->config->get('module_facet_filter_settings');
			if (isset($settings['settings']['category'])) {
				foreach ($settings['settings']['category'] as $category_id => $category) {
					if (!isset($this->request->post['module_facet_filter_settings']['category'][$category_id])) {
						foreach ($languages as $language) {
							$language_id = $language['language_id'];
							$cacheName = "category.store_{$store_id}.language_{$language_id}." . (floor($category_id / 100)) . ".filters_{$category_id}";
							$this->cache->delete($cacheName);
						}
					}
				}
			}

			// Save new settings
			$this->model_setting_setting->editSetting('module_facet_filter', $this->request->post, (int) $this->session->data['store_id']);
			// Show success message
			$this->session->data['success'] = $this->language->get('text_success');
			// Redirect to extensions list
			// $this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true));
		}

		// Errors
		$data['error_warning'] = $this->error['warning'] ?? '';

		// Form data
		$data['module_facet_filter_status'] = $this->request->post['module_facet_filter_status'] ?? $this->config->get('module_facet_filter_status');
		$data['settings'] 									= $this->request->post['module_facet_filter_settings'] ?? $this->config->get('module_facet_filter_settings');
		$data['user_token'] 								= $this->session->data['user_token'];

		// Get category name for saved categories
		if (isset($data['settings']['category'])) {
			$this->load->model('catalog/category');
			foreach ($data['settings']['category'] as $category_id => &$category) {
				$categoryData 		= $this->model_catalog_category->getCategory($category_id);
				$category['name'] = $categoryData['name'];
			}
		}
		
		// Buttons
		$data['action'] 		 = $this->url->link('extension/module/facet_filter', 'user_token=' . $this->session->data['user_token'], true);
		$data['cancel'] 		 = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true);
		// Common interface
		$data['header'] 		 = $this->load->controller('common/header');
		$data['footer'] 		 = $this->load->controller('common/footer');
		$data['column_left'] = $this->load->controller('common/column_left');

		$this->response->setOutput($this->load->view('extension/module/facet_filter', $data));
	}

	protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/module/facet_filter')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		return !$this->error;
	}
}