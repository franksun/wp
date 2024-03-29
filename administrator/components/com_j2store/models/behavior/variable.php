<?php
/**
 * @package J2Store
 * @copyright Copyright (c)2014-17 Ramesh Elamathi / J2Store.org
 * @license GNU GPL v3 or later
 */
// No direct access to this file
defined('_JEXEC') or die;

class J2StoreModelProductsBehaviorVariable extends F0FModelBehavior {

	private $_rawData = array();

	public function onAfterGetItem(&$model, &$record) {

		//we just have the products. Get the variants
			$variantModel = F0FModel::getTmpInstance('Variants', 'J2StoreModel');
			$variantModel->setState('product_type', $record->product_type);


			$record->lengths =$variantModel->getDimesions('lengths', 'j2store_length_id','length_title');
			$record->weights = $variantModel->getDimesions('weights', 'j2store_weight_id','weight_title');

		try {
			//first load master variant

			$variant_table = F0FTable::getAnInstance('Variant', 'J2StoreTable');
			$variant_table->load(array('product_id'=>$record->j2store_product_id, 'is_master'=>1));
			$record->variant = $variant_table;
			$global_config = JFactory::getConfig();
			$limit = $global_config->get('list_limit',20);
			//now load variants
/* 			$record->variants = $variantModel
			->product_id($record->j2store_product_id)
			->is_master(0)
			->getList();
 */
			//now load variants
			$record->variants = $variantModel
			->product_id($record->j2store_product_id)
			->limit($limit)
			->is_master(0)
			->getList();
			//TODO pagination to be set
			$record->variant_pagination = $variantModel->getPagination();

		}catch (Exception $e) {
			//there may not be a variant set.
			echo 'No variant set';
		}

			//lets load product options as well
			$record->product_options = F0FModel::getTmpInstance('ProductOptions', 'J2StoreModel')
			->product_id($record->j2store_product_id)
			->limit(0)
			->parent_id(null)
			->limitstart(0)
			->getList();

	}

	public function onBeforeSave(&$model, &$data)
	{
			$utility_helper = J2Store::utilities();

			if(isset($data['cross_sells'])) {
				$data['cross_sells'] = $utility_helper->to_csv($data['cross_sells']);
			}else{
				$data['cross_sells'] ='';
			}
			if(isset($data['up_sells'])) {
				$data['up_sells'] = $utility_helper->to_csv($data['up_sells']);
			}else{
				$data['up_sells'] ='';
			}

			if(isset($data['shippingmethods']) && !empty($data['shippingmethods'])){
				$data['shippingmethods'] = implode(',',$data['shippingmethods']);
			}

			if(isset($data['item_options']) && count($data['item_options']) > 0){
				$data['has_options'] = 1;
			}

			$this->_rawData = $data;
	}

	public function onAfterSave(&$model) {

		if($this->_rawData) {

			$table = $model->getTable();
			//save variant
			//since post has too much of information, this could do the job
			$variant = F0FTable::getInstance('Variant', 'J2StoreTable');
			$variant->bind($this->_rawData);
			//by default it is treated as master product.
			$variant->is_master = 1;
			$variant->product_id = $table->j2store_product_id;
			$variant->store();

			//save product options
			if(isset($this->_rawData['item_options'])) {

				foreach($this->_rawData['item_options'] as $item){
					$poption = F0FTable::getInstance('Productoption', 'J2StoreTable');
					$item->product_id = $table->j2store_product_id;
					try {
						$poption->save($item);
					}catch (Exception $e) {
						throw new Exception($e->getMessage());
					}
				}
			}


			//save variable values
			if(isset($this->_rawData['variable'])){
				foreach($this->_rawData['variable'] as $key => $item){

					if(isset($item->use_store_config_max_sale_qty) && $item->use_store_config_max_sale_qty =='on'){
						$item->use_store_config_max_sale_qty= 1;
					}else{
						$item->use_store_config_max_sale_qty= 0;
					}

					if(isset($item->use_store_config_min_sale_qty) && $item->use_store_config_min_sale_qty =='on' ){
						$item->use_store_config_min_sale_qty= 1;
					}else{
						$item->use_store_config_min_sale_qty= 0;
					}

					if(isset($item->use_store_config_notify_qty) && $item->use_store_config_notify_qty =='on'){
						$item->use_store_config_notify_qty= 1;
					}else{

						$item->use_store_config_notify_qty= 0;
					}


					$variantChild = F0FTable::getInstance('Variant', 'J2StoreTable')->getClone();
					$variantChild->is_master = 0;
					$item->product_id = $table->j2store_product_id;
					$quantity_item = $item->quantity;
					$quantity_item->variant_id = $key;
					$quantity = F0FTable::getAnInstance('Productquantity','J2StoreTable')->getClone();
					$quantity->load(array('variant_id'=>$key));
					try {
						if($variantChild->save($item)){
							
							if(!$quantity->save($quantity_item)){
								$quantity->getError();
							}
						}
					}catch (Exception $e) {
						throw new Exception($e->getMessage());
					}
				}
			}


			//save product images
			$images = F0FTable::getInstance('ProductImage', 'J2StoreTable');
			if(isset($this->_rawData['additional_images']) && !empty($this->_rawData['additional_images'] )){
				if(is_object($this->_rawData['additional_images'])){
					$this->_rawData['additional_images'] = json_encode(JArrayHelper::fromObject($this->_rawData['additional_images']));
				}else{
					$this->_rawData['additional_images'] = json_encode($this->_rawData['additional_images']);
				}
			}
			$this->_rawData['product_id'] = $table->j2store_product_id;

			//just make sure that we do not have a double entry there
			$images->load(array('product_id'=>$table->j2store_product_id));
			$images->save($this->_rawData);

			//finally run indexes to get the min - max price
			$this->runIndexes($table);

			//save product filters
			F0FTable::getAnInstance('ProductFilter', 'J2StoreTable' )->addFilterToProduct ( $this->_rawData ['productfilter_ids'], $table->j2store_product_id );
		}
	}

	public function runIndexes($table) {
		//first get all the variants for the product
		$variants = F0FModel::getTmpInstance('variants', 'J2StoreModel')->product_id($table->j2store_product_id)->is_master(0)->getList();
			$min_price            = null;
			$max_price            = null;

			foreach ( $variants as $variant) {
				// Skip non-priced variations
				if ( $variant->price === '' || $variant->price == 0 ) {
					continue;
				}

				// Find min price
				if ( is_null( $min_price ) || $variant->price < $min_price ) {
					$min_price  = $variant->price;
				}

				// Find max price
				if ( $variant->price > $max_price ) {
					$max_price   = $variant->price;
				}
			}
			//load the price index table and set the min - max price
			$db = JFactory::getDbo();
			$values = array();
			$product_id = $table->j2store_product_id;
			$values['product_id'] = $product_id;
			$values['min_price'] = $min_price;
			$values['max_price'] = $max_price;
			$price_index = F0FTable::getInstance('ProductPriceIndex', 'J2StoreTable');
			$object = (object) $values;
			if($price_index->load($table->j2store_product_id)) {
				$db->updateObject('#__j2store_productprice_index', $object , 'product_id');
			} else {
				$db->insertObject('#__j2store_productprice_index', $object);
			}

	}

	public function onBeforeDelete(&$model) {
		$id = $model->getId();

		$variantModel = F0FModel::getTmpInstance('Variants', 'J2StoreModel');

		//get variants
		$variants = $variantModel->limit(0)->limitstart(0)->product_id($id)->getList();
		foreach($variants as $variant) {
			$variantModel->setIds(array($variant->j2store_variant_id))->delete();
		}
	}

	public function onAfterGetProduct(&$model, &$product) {
		//sanity check
		if($product->product_type != 'variable') return;

		$j2config = J2Store::config();
		$product_helper = J2Store::product();
		//links
		$product_helper->getAddtocartAction($product);
		$product_helper->getCheckoutLink($product);
		$product_helper->getProductLink( $product );
		//we just have the products. Get the variants
		$variantModel = F0FModel::getTmpInstance('Variants', 'J2StoreModel');
		$variantModel->setState('product_type', $product->product_type);

		try {
			//first load master variant

			$variant_table = F0FTable::getAnInstance('Variant', 'J2StoreTable');
			$variant_table->load(array('product_id'=>$product->j2store_product_id, 'is_master'=>1));
			$product->variant = $variant_table;


			//now load variants
			$product->variants = $variantModel
			->product_id($product->j2store_product_id)
			->is_master(0)
			->getList();
		}catch (Exception $e) {
			//there may not be a variant set.
			echo 'No variant set';
		}

		//no variants found. Exit processing
		if(!$product->variants) {
			$product->visibility = 0;
			return false;
		}
			// process variant
		$product->variant = $product_helper->getDefaultVariant($product->variants);

		$variant_ids = array();
		foreach($product->variants as $variant) {
			//get quantity restrictions
			$product_helper->getQuantityRestriction($variant);
			$variant_ids[] = $variant->j2store_variant_id;
		}

		if($product->variant->quantity_restriction && $product->variant->min_sale_qty > 0) {
			$product->quantity = $product->variant->min_sale_qty;
		} else {
			$product->quantity = 1;
		}

		foreach($product->variants as &$variant) {

			if($product_helper->check_stock_status($variant, $product->quantity)) {
				//reset the availability
				$variant->availability = 1;
			}else {
				$variant->availability = 0;
			}

		}

		//process pricing. returns an object
		$product->pricing = $product_helper->getPrice($product->variant, $product->quantity);

		$product->options = array();
		//only if the product has options and variations
		if($product->has_options && $product->variants) {
			try {

				//lets load product options as well
				$product->product_options = F0FModel::getTmpInstance('ProductOptions', 'J2StoreModel')
				->product_id($product->j2store_product_id)
				->limit(0)
				->parent_id(null)
				->limitstart(0)
				->getList();

				$product->options = $product_helper->getProductOptions($product);

				if($product_helper->validateVariants($product->variants, $product->options) === false) {
					$product->visibility = 0;
				}

				$db = JFactory::getDbo();
				//get all the variants
				$query = $db->getQuery(true)->select('#__j2store_product_variant_optionvalues.variant_id as variant_id, #__j2store_product_variant_optionvalues.product_optionvalue_ids')->from('#__j2store_product_variant_optionvalues')
											->where('variant_id IN ('.implode(',', $variant_ids).')' );

				$db->setQuery($query);
				$csvs = $db->loadAssocList('variant_id');

				$variant_csvs = array();
				foreach($csvs as $variant_id=>$csv) {
					$variant_csvs[$variant_id] = $csv['product_optionvalue_ids'];
				}
				$product->variant_json = json_encode($variant_csvs);

				//get the default variant
				$default_optionvalue_ids = $variant_csvs[$product->variant->j2store_variant_id];

				/* $query = $db->getQuery(true)->select('#__j2store_product_variant_optionvalues.product_optionvalue_ids')->from('#__j2store_product_variant_optionvalues')
				->where('variant_id='.$db->q($product->variant->j2store_variant_id));
				$db->setQuery($query);
				$row = $db->loadObject();
				 */

				if(isset($default_optionvalue_ids)) {
					$value_array = explode(',', $default_optionvalue_ids);
				} else {
					$value_array = array();
				}
				foreach($product->options as &$option) {
					if ($option['type'] == 'select' || $option['type'] == 'radio') {
						foreach($option['optionvalue'] as &$optionvalue) {
							if(in_array($optionvalue['product_optionvalue_id'], $value_array)) {
								$optionvalue['product_optionvalue_default'] = 1;
							}
						}
					}
				}

			}catch (Exception $e) {
				//do nothing
			}
		}

	}

	public function onUpdateProduct(&$model, &$product) {

		$app = JFactory::getApplication();
		$product_helper = J2Store::product();
		$params = J2Store::config();
		//first get the correct variant
		$options = $app->input->get('product_option', array(0), 'ARRAY');
		if (isset($options )) {
			$options =  array_filter($options );
		} else {
			$options = array();
		}

		//no options found. so just return an empty array
		if(count($options) < 1) return false;

		//options found. Get the correct variant

		$variant = $product_helper->getVariantByOptions($options, $product->j2store_product_id);

		if($variant === false) return false;

		//now we have the variant. Process.

			//get quantity restrictions
			$product_helper->getQuantityRestriction($variant);

			$quantity = $app->input->getFloat('product_qty', 1);

			if($variant->quantity_restriction && $variant->min_sale_qty > 0 ) {
				$quantity = ($variant->min_sale_qty > $quantity) ? $variant->min_sale_qty : $quantity;
			}

			//check stock status
			if($product_helper->check_stock_status($variant, $quantity)) {
				//reset the availability
				$variant->availability = 1;
			}else {
				$variant->availability = 0;
			}

			//process pricing. returns an object
			$variant->pricing = $product_helper->getPrice($variant, $quantity);

			//prepare return values
			$return = array();
			$return['variant_id'] = $variant->j2store_variant_id;
			$return['sku'] = $variant->sku;
			$return['quantity'] = floatval($quantity);
			$return['price'] = $variant->price;
			$return['availability'] = $variant->availability;
			$return['manage_stock'] = $variant->manage_stock;
			$return['allow_backorder'] = $variant->allow_backorder;

			if($variant->availability) {
				$return['stock_status'] = J2Store::product()->displayStock($variant, $params);
			}else {
				$return['stock_status'] = JText::_('J2STORE_OUT_OF_STOCK');
			}
			$return['pricing'] = array();
			$return['pricing']['base_price'] = J2Store::product()->displayPrice($variant->pricing->base_price, $product, $params);
			$return['pricing']['price'] = J2Store::product()->displayPrice($variant->pricing->price, $product, $params);

			//dimensions
			$return['dimensions'] = round($variant->length,2).' x '.round($variant->height,2).' x '.round($variant->width,2).' '.$variant->length_title;
			$return['weight'] = round($variant->weight,2).' '.$variant->weight_title;

		return $return;

	}

}