<?php
class ControllerExtensionModuleFacetFilter extends Controller {

	public function __construct($registry) {
		parent::__construct($registry);
	}

	public function index() {

		$this->load->model('extension/module/facet_filter');
		$this->load->language('extension/module/facet_filter');
		$settings = $this->config->get('module_facet_filter_settings');

		$route 				= (string) $this->request->get['route'];
		$path 				= $this->request->get['category_id'] ?? $this->request->get['path'] ?? '';
		$category_id 	= explode('_', (string) $path);
		$category_id 	= end($category_id) ?? null;
		
		// Interface data
		if (isset($settings['cache']) && $settings['cache'] === '1' && $route !== 'product/search') {
			// Cache except search page
			$store_id 		= (int) $this->config->get('config_store_id');
			$language_id 	= (int) $this->config->get('config_language_id');
			$cachePrefix = explode('/', $route)[1] ?? $route;
			$cachePostfix = "filters";
			if ($category_id) {
				$cachePostfix = (floor($category_id / 100)) . "00.filters_{$category_id}";
			}
			$cacheName 	= "{$cachePrefix}.store_{$store_id}.language_{$language_id}.{$cachePostfix}";
			$data['filter_sets'] 	= $this->cache->get($cacheName);
			if (!$data['filter_sets']) {
				$data['filter_sets'] = $this->getFilterSets();
				$this->cache->set($cacheName, $data['filter_sets']);
			}
		} else {
			// No cache 
			$data['filter_sets'] = $this->getFilterSets();
		}
		
		// Request data to check applied filters
		$data['requests'] = [
			'filter' 						=> explode(',', $this->request->get['filter'] ?? '') 					?? null,
			'option' 						=> explode(',', $this->request->get['option'] ?? '') 					?? null,
			'attribute' 				=> explode(',', $this->request->get['attribute'] ?? '') 				?? null,
			'manufacturer_id' 	=> explode(',', $this->request->get['manufacturer_id'] ?? '') 	?? null,
		];
			
		foreach ($data['filter_sets'] as $filter_type_key => &$filter_type) {
			foreach ($filter_type as &$filter_group) {
				foreach ($filter_group['filters'] as &$filter_item) {
					$query = $this->request->get;
					unset($query['route']);
					
					$current = [];
					
					if (!empty($query[$filter_type_key])) {
						$current = array_filter(
							array_map('intval', explode(',', $query[$filter_type_key]))
						);
					}
					
					$id = (int) $filter_item['filter_id'];
					
					if (in_array($id, $current, true)) {
						// remove
						$current = array_diff($current, [$id]);
					} else {
						// add
						$current[] = $id;
					}
					
					$current = array_values(array_unique($current));
					
					sort($current);

					if ($current) {
						$query[$filter_type_key] = implode(',', $current);
					} else {
						unset($query[$filter_type_key]);
					}
					
					$filter_item['href'] = $this->url->link($route, http_build_query($query));
				}
			}
		}

		return $this->load->view('extension/module/facet_filter', $data);
	}

	private function getFilterSets() : array {
		$filterSets = [];

		$this->load->model('extension/module/facet_filter');
		$this->load->language('extension/module/facet_filter');
		$settings = $this->config->get('module_facet_filter_settings');


		$route = (string) $this->request->get['route'];
		$path = $this->request->get['category_id'] ?? $this->request->get['path'] ?? '';
		$category_id = explode('_', (string) $path);
		$category_id = end($category_id) ?? null;

		// Switch case for different page types
		switch ($route) {
			case 'product/category':
				// Check if category exists

				$category_exists = $this->model_extension_module_facet_filter->categoryExists($category_id);
				if (!$category_exists) {
					return [];
				}
				// Get category products
				$products = $this->model_extension_module_facet_filter->getCategoryProducts($category_id);
				// Get category filters
				$filters = $this->model_extension_module_facet_filter->getCategoryFilters($category_id);
			break;

			case 'product/special':
				// Get special products
				$products = $this->model_extension_module_facet_filter->getSpecialProducts();
				// Get filters
				$filters = $this->model_extension_module_facet_filter->getFiltersByProductSet($products);
			break;

			case 'product/search':
				// Get search products
				$this->load->model('catalog/product');
				$products = [];
				$searchProducts = $this->model_catalog_product->getProducts($this->request->get['search'] ?? null) ?? [];
				foreach ($searchProducts as $product) {
					$products[] = $product['product_id'];
				}
				// Get filters
				$filters = $this->model_extension_module_facet_filter->getFiltersByProductSet($products);
			break;
			
			default:
			return [];
		}
	
		$options 				= $this->model_extension_module_facet_filter->getOptionsByProductSet($products);
		$attributes 		= $this->model_extension_module_facet_filter->getAttributesByProductSet($products);
		$manufacturers 	= $this->model_extension_module_facet_filter->getManufacturersByProductSet($products);

		// Interface data
		// Category settings
		if ($route === 'product/category' && $category_id) {
			if (isset($settings['category'][$category_id])) {
				// Individual category settings
				$filterSets = [
					'filter'						=> (isset($settings['category'][$category_id]['show_filters'])) 			? $filters : [],
					'option' 						=> (isset($settings['category'][$category_id]['show_options'])) 			? $options : [], 			
					'attribute' 				=> (isset($settings['category'][$category_id]['show_attributes'])) 		? $attributes : [], 	
					'manufacturer_id' 	=> (isset($settings['category'][$category_id]['show_manufacturers'])) ? $manufacturers : [],
				];
			} else {
				// Default category settings
				$filterSets = [
					'filter'						=> (isset($settings['default']['show_filters'])) 			 ? $filters : [],
					'option' 						=> (isset($settings['default']['show_options'])) 			 ? $options : [], 			
					'attribute' 				=> (isset($settings['default']['show_attributes'])) 	 ? $attributes : [], 	
					'manufacturer_id' 	=> (isset($settings['default']['show_manufacturers'])) ? $manufacturers : [],
				];
			}
		}
		
		// Special/discount products
		if ($route === 'product/special') {
			$filterSets = [
				'filter'						=> (isset($settings['special']['show_filters'])) 				? $filters : [],
				'option' 						=> (isset($settings['special']['show_options'])) 				? $options : [], 			
				'attribute' 				=> (isset($settings['special']['show_attributes'])) 		? $attributes : [], 	
				'manufacturer_id' 	=> (isset($settings['special']['show_manufacturers'])) 	? $manufacturers : [],
			];
		}

		// Search page
		if ($route === 'product/search') {
			$filterSets = [
				'filter'						=> (isset($settings['search']['show_filters'])) 				? $filters : [],
				'option' 						=> (isset($settings['search']['show_options'])) 				? $options : [], 			
				'attribute' 				=> (isset($settings['search']['show_attributes'])) 		  ? $attributes : [], 	
				'manufacturer_id' 	=> (isset($settings['search']['show_manufacturers'])) 	? $manufacturers : [],
			];
		}

		// Manufacturer page
		if ($route === 'product/manufacturer') {
			$filterSets = [
				'filter'						=> (isset($settings['manufacturer']['show_filters'])) 				? $filters : [],
				'option' 						=> (isset($settings['manufacturer']['show_options'])) 				? $options : [], 			
				'attribute' 				=> (isset($settings['manufacturer']['show_attributes'])) 		  ? $attributes : [], 	
				'manufacturer_id' 	=> (isset($settings['manufacturer']['show_manufacturers'])) 	? $manufacturers : [],
			];
		}

		return $filterSets;
	}
}