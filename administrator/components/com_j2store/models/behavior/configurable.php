<?php
/**
 * @package J2Store
 * @copyright Copyright (c)2014-17 Ramesh Elamathi / J2Store.org
 * @license GNU GPL v3 or later
 */
// No direct access to this file
defined('_JEXEC') or die;
class J2StoreModelProductsBehaviorConfigurable extends J2StoreModelProductsBehaviorSimple {

	public function onAfterGetItem(&$model, &$record) {
		//we just have the products. Get the variants
		$variantModel = F0FModel::getTmpInstance('Variants', 'J2StoreModel');
		$variantModel->setState('product_type', $record->product_type);
		$app = JFactory::getApplication();
		//Its a simple product. So we will have only one variant record
		try {
			$variants = $variantModel->product_id($record->j2store_product_id)->is_master(1)->getList();
			$record->variants = $variants[0];
		}catch(Exception $e) {
			$this->setError($e->getMessage());
			$record->variants = F0FTable::getInstance('Variants', 'J2StoreTable');
		}

		try {
			//lets load product options as well
			$option_model = F0FModel::getTmpInstance('ProductOptions', 'J2StoreModel')
									->clearState()
									->product_id($record->j2store_product_id)
									->limit(0)
									->limitstart(0);
			$view = $app->input->getCmd('view', '');
			//TODO we should find an alternative method. This is a quick fix.
			if($app->isSite() && $view != 'form') {
				$option_model->setState('parent_id', 0);
			}

			$record->product_options = $option_model->getList();

		}catch (Exception $e) {
			$this->setError($e->getMessage());
		}

	}

	public function onAfterGetProduct(&$model, &$product) {

		//sanity check
		if($product->product_type != 'configurable') return;

		$j2config = J2Store::config ();
		$product_helper = J2Store::product ();

		//we just have the products. Get the variants
		$variantModel = F0FModel::getTmpInstance('Variants', 'J2StoreModel');
		$variantModel->setState('product_type', $product->product_type);

		//Its a simple product. So we will have only one variant record
		try {
			$variants = $variantModel->product_id($product->j2store_product_id)->is_master(1)->getList();
			$product->variants = current($variants);
		}catch(Exception $e) {
			$this->setError($e->getMessage());
			$product->variants = F0FTable::getAnInstance('Variants', 'J2StoreTable');
		}

		// links
		$product_helper->getAddtocartAction ( $product );
		$product_helper->getCheckoutLink ( $product );
		$product_helper->getProductLink( $product );

		// process variant
		$product->variant = $product->variants;

		// get quantity restrictions
		$product_helper->getQuantityRestriction ( $product->variant );
		// now process the quantity

		if (isset($product->variant->quantity_restriction) && $product->variant->min_sale_qty > 0) {
			$product->quantity = $product->variant->min_sale_qty;
		} else {
			$product->quantity = 1;
		}

		//check stock status
		if ($product_helper->check_stock_status ( $product->variant, $product->quantity )) {
			// reset the availability
			$product->variant->availability = 1;
		} else {
			$product->variant->availability = 0;
		}

		// process pricing. returns an object
		$product->pricing = $product_helper->getPrice ( $product->variant, $product->quantity );

		$product->options = array ();
		if ($product->has_options) {

			try {
				//lets load product options as well
				$option_model = F0FModel::getTmpInstance('ProductOptions', 'J2StoreModel')
				->clearState()
				->product_id($product->j2store_product_id)
				->limit(0)
				->limitstart(0);
				if(JFactory::getApplication()->isSite()) {
					$option_model->setState('parent_id', 0);
				}

				$product->product_options = $option_model->getList();

			}catch (Exception $e) {
				$this->setError($e->getMessage());
			}

			try {
				$product->options = $product_helper->getProductOptions ( $product);
			} catch ( Exception $e ) {
				// do nothing
			}
		}
	}

	public function onUpdateProduct(&$model, &$product) {
		$app = JFactory::getApplication ();
		$params = J2Store::config ();
		$product_helper = J2Store::product ();

		$product_id = $app->input->getInt ( 'product_id', 0 );

		if (! $product_id)
			return false;

		// 1. fetch parent options (select box) and set default selected value

		$po_id = $app->input->getInt ( 'po_id', 0 );
		// echo $po_id;exit;
		$pov_id = $app->input->getInt ( 'pov_id', 0 );

		$html = '';
		if ($po_id && $pov_id) {
			// ~ now get the children for the above two
			$attributes = array ();
			$a = array ();

			// 2. fetch the children
			$db = JFactory::getDBO ();
			$query = $db->getQuery ( true )->select ( 'j2store_productoption_id, option_id' )->from ( '#__j2store_product_options' )->where ( 'j2store_productoption_id IN (' . $po_id . ')' );
			$db->setQuery ( $query );
			$parent_id = $db->loadObjectList ( 'j2store_productoption_id' );

			$a = array ();
			$child_opts = '';

			if($pov_id) {
				$child_opts = $product_helper->getChildProductOptions ( $product_id, $parent_id [$po_id]->option_id, $pov_id );
			}

			if (! empty ( $child_opts )) {
				$options = array ();
				foreach ( $child_opts as $index => $attr ) {
					if (count ( $attr ['optionvalue'] ) > 0) 					// if optionvalue exist or not. then only display form.otherwise form display only heading without option name
					{
						array_push ( $options, $attr );
					}
				}
				$product->options = $options;

				$controller = F0FController::getTmpInstance ( 'com_j2store', 'Products' );
				$view = $controller->getView ( 'Product', 'Html', 'J2StoreView' );

				if ($model = $controller->getModel ( 'Products', 'J2StoreModel' )) {
					// Push the model into the view (as default)
					$view->setModel ( $model, true );
				}
				$model->setState('task', 'read');
				$view->assign('product', $product );
				$view->assign( 'params', $params );
				$view->setLayout ( 'item_configurableoptions' );
				ob_start ();
				$view->display ( );
				$html = ob_get_contents ();
				ob_end_clean ();
			}
		}
		// get variant
		$variants = F0FModel::getTmpInstance ( 'Variants', 'J2StoreModel' )->product_id ( $product->j2store_product_id )->is_master ( 1 )->getList ();
		$product->variants = $variants [0];

		// process variant
		$product->variant = $product->variants;

		// get quantity restrictions
		$product_helper->getQuantityRestriction ( $product->variant );

		// now process the quantity
		$product->quantity = $app->input->getFloat ( 'product_qty', 1 );

		if ($product->variant->quantity_restriction && $product->variant->min_sale_qty > 0) {
			$product->quantity = $product->variant->min_sale_qty;
		}

		// process pricing. returns an object
		$pricing = $product_helper->getPrice ( $product->variant, $product->quantity );

		$parent_product_options = $app->input->get ( 'product_option', array (), 'ARRAY' );

		// get the selected option price
		if (count ( $parent_product_options )) {
			$product_option_data = $product_helper->getOptionPrice ( $parent_product_options, $product->j2store_product_id );

			$base_price = $pricing->base_price + $product_option_data ['option_price'];
			$price = $pricing->price + $product_option_data ['option_price'];
		} else {
			$base_price = $pricing->base_price;
			$price = $pricing->price;
		}

		$return = array ();
		$return ['pricing'] = array ();
		$return ['pricing'] ['base_price'] = J2Store::product ()->displayPrice ( $base_price, $product, $params );
		$return ['pricing'] ['price'] = J2Store::product ()->displayPrice ( $price, $product, $params );
		$return ['optionhtml'] = $html;
		return $return;
	}
}