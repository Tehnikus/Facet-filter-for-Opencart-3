<?php
class ModelExtensionModuleFacetFilter extends Model {

  public function categoryExists($category_id = null) : bool {
		if ($category_id === null) {
			return false;
		}

    $query = $this->db->query("
      SELECT c2s.category_id AS category_id
      FROM " . DB_PREFIX . "category_to_store c2s
      JOIN " . DB_PREFIX . "category c
        ON c.category_id = c2s.category_id
      WHERE c2s.category_id = '" . (int) $category_id . "'
				AND c2s.store_id = '" . (int) $this->config->get('config_store_id') . "'
      LIMIT 1
    ");

    if ($query->row['category_id']) {
      return true;
    }
    return false;
  }

	public function getCategoryProducts($category_id = null) : array {
		$result = [];
		if ($category_id === null) {
			return $result;
		}
		$query = $this->db->query("
			SELECT
				p2c.product_id AS product_id
			FROM " . DB_PREFIX . "product_to_category p2c
			WHERE p2c.category_id = '" . (int) $category_id . "'
				AND p2c.store_id = '" . (int) $this->config->get('config_store_id') . "'
		");

		foreach ($query->rows as $row) {
			$result[] = $row['product_id'];
		}

		return $result;
	}

	public function getSpecialProducts() : array {
		$result = [];

		$query = $this->db->query("
			SELECT 
				product_id
			FROM " . DB_PREFIX . "product_special
			WHERE store_id = '" . (int) $this->config->get('config_store_id') . "'
			UNION
			SELECT
				product_id
			FROM " . DB_PREFIX . "product_discount
			WHERE store_id = '" . (int) $this->config->get('config_store_id') . "'
		");

		foreach ($query->rows as $row) {
			$result[] = $row['product_id'];
		}

		return $result;
	}

	public function getOptionsByProductSet($products = []) : array {
		$result = [];

		if (empty($products)) {
			return $result;
		}

		$query = $this->db->query("
			SELECT
				pov.option_id AS filter_group_id,
				pov.option_value_id AS filter_id,
				o2s.sort_order AS group_sort_order,
				ov.sort_order AS filter_sort_order,
				od.name AS group_name,
				ovd.name AS filter_name
			FROM " . DB_PREFIX . "product_option_value pov
			JOIN " . DB_PREFIX . "option_to_store o2s
				ON o2s.option_id = pov.option_id
				AND o2s.store_id = '" . (int) $this->config->get('config_store_id') . "'
			JOIN " . DB_PREFIX . "option_value ov
				ON ov.option_value_id = pov.option_value_id
				AND ov.store_id = '" . (int) $this->config->get('config_store_id') . "'
			JOIN " . DB_PREFIX . "option_description od
				ON od.option_id = pov.option_id
				AND od.language_id = '" . (int) $this->config->get('config_language_id') . "'
				AND od.store_id = '" . (int) $this->config->get('config_store_id') . "'
			JOIN " . DB_PREFIX . "option_value_description ovd
				ON ovd.option_value_id = pov.option_value_id
				AND ovd.language_id = '" . (int) $this->config->get('config_language_id') . "'
				AND ovd.store_id = '" . (int) $this->config->get('config_store_id') . "'
			WHERE pov.product_id IN(" . implode(',', $products) . ")
			GROUP BY filter_id
		");

		foreach ($query->rows as $row) {
			$result[$row['filter_group_id']] = [
				'group_name' 				=> $row['group_name'],
				'filter_group_id' 	=> $row['filter_group_id'],
				'group_sort_order' 	=> $row['group_sort_order'],
			];
		}

		foreach ($query->rows as $row) {
			$result[$row['filter_group_id']]['filters'][$row['filter_id']] = [
				'filter_id' 					=> $row['filter_id'],
				'name' 								=> $row['filter_name'],
				'filter_sort_order' 	=> $row['filter_sort_order'],
			];
		}

		if (!empty($result)) {	
			usort($result, fn ($a, $b) =>  $a['group_sort_order'] <=> $b['group_sort_order'] );
			foreach ($result as &$group) {
				if (isset($group['filters'])) {
					usort(array: $group['filters'], callback: fn ($a, $b) =>  $a['filter_sort_order'] <=> $b['filter_sort_order'] );
				}
			}
		}

		return $result;
	}

	public function getAttributesByProductSet($products = []) : array {
		$result = [];

		if (empty($products)) {
			return $result;
		}

		$query = $this->db->query("
			SELECT
				pa.attribute_group_id AS filter_group_id,
				pa.attribute_id AS filter_id,
				ag2s.sort_order AS group_sort_order,
				a2s.sort_order AS filter_sort_order,
				agd.name AS group_name,
				ad.name AS filter_name
			FROM " . DB_PREFIX . "product_attribute pa
			JOIN " . DB_PREFIX . "attribute_to_store a2s
				ON a2s.attribute_id = pa.attribute_id
				AND a2s.store_id = '" . (int) $this->config->get('config_store_id') . "'
			JOIN " . DB_PREFIX . "attribute_group_to_store ag2s
				ON ag2s.attribute_group_id = pa.attribute_group_id
				AND ag2s.store_id = '" . (int) $this->config->get('config_store_id') . "'
			JOIN " . DB_PREFIX . "attribute_description ad
				ON ad.attribute_id = pa.attribute_id
				AND ad.language_id = '" . (int) $this->config->get('config_language_id') . "'
				AND ad.store_id = '" . (int) $this->config->get('config_store_id') . "'
			JOIN " . DB_PREFIX . "attribute_group_description agd
				ON agd.attribute_group_id = pa.attribute_group_id
				AND agd.language_id = '" . (int) $this->config->get('config_language_id') . "'
				AND agd.store_id = '" . (int) $this->config->get('config_store_id') . "'
			WHERE pa.product_id IN(" . implode(',', $products) . ")
			GROUP BY filter_id
		");

		foreach ($query->rows as $row) {
			$result[$row['filter_group_id']] = [
				'group_name' 				=> $row['group_name'],
				'filter_group_id' 	=> $row['filter_group_id'],
				'group_sort_order' 	=> $row['group_sort_order'],
			];
		}

		foreach ($query->rows as $row) {
			$result[$row['filter_group_id']]['filters'][$row['filter_id']] = [
				'filter_id' 					=> $row['filter_id'],
				'name' 								=> $row['filter_name'],
				'filter_sort_order' 	=> $row['filter_sort_order'],
			];
		}

		if (!empty($result)) {	
			usort($result, fn ($a, $b) =>  $a['group_sort_order'] <=> $b['group_sort_order'] );
			foreach ($result as &$group) {
				if (isset($group['filters'])) {
					usort(array: $group['filters'], callback: fn ($a, $b) =>  $a['filter_sort_order'] <=> $b['filter_sort_order'] );
				}
			}
		}

		return $result;
	}

	public function getManufacturersByProductSet($products = []) : array {
		$result = [];

		if (empty($products)) {
			return $result;
		}

		$query = $this->db->query("
			SELECT
				p.manufacturer_id AS filter_id,
				md.name AS filter_name,
				m.sort_order AS sort_order 
			FROM " . DB_PREFIX . "product p
			JOIN " . DB_PREFIX . "manufacturer m
				ON m.manufacturer_id = p.manufacturer_id
			JOIN " . DB_PREFIX . "manufacturer_description md
				ON md.manufacturer_id = p.manufacturer_id
				AND md.language_id = '" . (int) $this->config->get('config_language_id') . "'
				AND md.store_id = '" . (int) $this->config->get('config_store_id') . "'
			WHERE p.product_id IN(" . implode(',', $products) . ")
			GROUP BY filter_id
		");

		foreach ($query->rows as $row) {
			$result[0]['filters'][$row['filter_id']] = [
				'filter_id' => $row['filter_id'],
				'name' 			=> $row['filter_name'],
				'sort_order' => $row['sort_order'],
			];
		}

		$this->language->load('extension/module/facet_filter');
		$result['0']['group_name'] = $this->language->get('text_manufacturers');
		$result['0']['filter_group_id'] = 1;

		if (isset($result[0]['filters'])) {	
			usort($result[0]['filters'], fn ($a, $b) =>  $a['sort_order'] <=> $b['sort_order'] );
		}

		return $result;
	}

	public function getCategoryFilters($category_id) {

		$sql = "
			SELECT 
				f.filter_id,
				fd.name AS filter_name,
				f.filter_group_id,
				f.sort_order AS filter_sort,
				fg2s.sort_order AS group_sort,
				fgd.name AS group_name
			FROM " . DB_PREFIX . "category_filter cf
			
			JOIN " . DB_PREFIX . "filter f 
				ON cf.filter_id = f.filter_id 
				AND f.store_id = cf.store_id

			JOIN " . DB_PREFIX . "filter_group fg
				ON fg.filter_group_id = f.filter_group_id

			JOIN " . DB_PREFIX . "filter_description fd 
				ON 	f.filter_id 		= fd.filter_id 
				AND fd.language_id 	= '" . (int) $this->config->get('config_language_id') . "'
				AND fd.store_id 		= cf.store_id 

			JOIN " . DB_PREFIX . "filter_group_to_store fg2s
				ON f.filter_group_id 	= fg2s.filter_group_id 
				AND fg2s.store_id 		= cf.store_id

			JOIN " . DB_PREFIX . "filter_group_description fgd
				ON  f.filter_group_id  	= fgd.filter_group_id
				AND fgd.language_id 		= '" . (int) $this->config->get('config_language_id') . "'
				AND fgd.store_id 				= cf.store_id

			WHERE cf.category_id 	= '" . (int) $category_id . "'
				AND cf.store_id 		= '" . (int) $this->config->get('config_store_id') . "'

			ORDER BY fg2s.sort_order, 
				LCASE(group_name),
				f.sort_order,
				LCASE(filter_name)
		";

		$query = $this->db->query($sql);

		$filter_group_data = [];

		foreach ($query->rows as $row) {
			$group_id = $row['filter_group_id'];

			if (!isset($filter_group_data[$group_id])) {
				$filter_group_data[$group_id] = array(
					'filter_group_id' => $group_id,
					'group_name'      => $row['group_name'],
				);
			}

			$filter_group_data[$group_id]['filters'][] = array(
				'filter_id' => $row['filter_id'],
				'name'      => $row['filter_name']
			);
		}

		// Reset keys to start from zero
		return array_values($filter_group_data);
	}

	public function getFiltersByProductSet($products = []) : array {
		$result = [];

		if (empty($products)) {
			return $result;
		}

		$query = $this->db->query("
			SELECT
				pf.filter_id AS filter_id,
				pf.filter_group_id AS filter_group_id,
				fg2s.sort_order AS group_sort_order,
				f.sort_order AS filter_sort_order,
				fd.name AS filter_name,
				fgd.name AS group_name
			FROM " . DB_PREFIX . "product_filter pf
			JOIN " . DB_PREFIX . "filter_group_to_store fg2s
				ON fg2s.filter_group_id = pf.filter_group_id
				AND fg2s.store_id = '" . (int) $this->config->get('config_store_id') . "'
			JOIN " . DB_PREFIX . "filter f
				ON f.filter_id = pf.filter_id
				AND f.store_id = '" . (int) $this->config->get('config_store_id') . "'
			JOIN " . DB_PREFIX . "filter_description fd
				ON fd.filter_id = pf.filter_id
				AND fd.language_id = '" . (int) $this->config->get('config_language_id') . "'
				AND fd.store_id = '" . (int) $this->config->get('config_store_id') . "'
			JOIN " . DB_PREFIX . "filter_group_description fgd
				ON fgd.filter_group_id = pf.filter_group_id
				AND fgd.language_id = '" . (int) $this->config->get('config_language_id') . "'
				AND fgd.store_id = '" . (int) $this->config->get('config_store_id') . "'
			WHERE pf.product_id IN(" . implode(',', $products) . ")
				AND pf.store_id = '" . (int) $this->config->get('config_store_id') . "'
		");

		foreach ($query->rows as $row) {
			$result[$row['filter_group_id']] = [
				'group_name' 				=> $row['group_name'],
				'filter_group_id' 	=> $row['filter_group_id'],
				'group_sort_order' 	=> $row['group_sort_order'],
			];
		}

		foreach ($query->rows as $row) {
			$result[$row['filter_group_id']]['filters'][$row['filter_id']] = [
				'filter_id' 					=> $row['filter_id'],
				'name' 								=> $row['filter_name'],
				'filter_sort_order' 	=> $row['filter_sort_order'],
			];
		}

		if (!empty($result)) {	
			usort($result, fn ($a, $b) =>  $a['group_sort_order'] <=> $b['group_sort_order'] );
			foreach ($result as &$group) {
				if (isset($group['filters'])) {
					usort(array: $group['filters'], callback: fn ($a, $b) =>  $a['filter_sort_order'] <=> $b['filter_sort_order'] );
				}
			}
		}

		return $result;
	}
}
