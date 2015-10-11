<?php
/**
 * @package J2Store
 * @copyright Copyright (c)2014-17 Ramesh Elamathi / J2Store.org
 * @license GNU GPL v3 or later
 */
// No direct access to this file
defined('_JEXEC') or die;
require_once('cartsimple.php');
class J2StoreModelCartsBehaviorCartConfigurable extends J2StoreModelCartsBehaviorCartSimple {


	public function onBeforeAddCartItem(&$model, $product, &$json) {
		$app = JFactory::getApplication();
		$product_helper = J2Store::product();
		$values = $app->input->getArray($_REQUEST);
		$errors = array();

		//run quantity check
		$quantity = $app->input->get('product_qty');
		if (isset($quantity )) {
			$quantity = $quantity;
		} else {
			$quantity = 1;
		}

		//get options
		//get the product options
		$options = $app->input->get('product_option', array(0), 'ARRAY');
		$check_option=$options;
		if (isset($options )) {
			$options =  array_filter($options );
		} else {
			$options = array();
		}	
			 
		$reloaded_product_options = F0FModel::getTmpInstance('ProductOptions', 'J2StoreModel')->product_id($product->j2store_product_id)->parent_id(null)->getList();

		unset($product->product_options);
		$product->product_options = $reloaded_product_options;
		
		$product_options = $product_helper->getProductOptions($product);
		$ommit_check = array();
		//iterate through stored options for this product and validate
		foreach($product_options as $product_option) {
			$check_require= F0FModel::getTmpInstance('ProductOptions', 'J2StoreModel')->getItem($product_option['productoption_id']);
			if($check_require->required && empty($check_option[$product_option['productoption_id']]) && $check_require->parent_id == 0 || in_array($product_option['productoption_id'], $ommit_check)){
				$errors['error']['option'][$product_option['productoption_id']] = JText::sprintf('J2STORE_ADDTOCART_PRODUCT_OPTION_REQUIRED', $product_option['option_name']);
			}
			else if(array_key_exists($product_option['productoption_id'],$options))
			{
				if(is_array($options[$product_option['productoption_id']])) {
					foreach($options[$product_option['productoption_id']] as $optionvalue) {
						
						$child= $product_helper->getChildProductOptions($product->j2store_product_id,$check_require->option_id,$optionvalue);
						if(!empty($child))
						{
							foreach($child as $index => $attr){
									
								if(count($attr['optionvalue']) > 0 && $attr['required'] && !array_key_exists($attr['productoption_id'],$options)) // if optionvalue exist or not. then only display form.otherwise form display only heading without option name
								{
									array_push($ommit_check,$attr['productoption_id']);
								}
							}
						}
						
					}
				} else {	
					$child= $product_helper->getChildProductOptions($product->j2store_product_id,$check_require->option_id,$options[$product_option['productoption_id']]);
					if(!empty($child))
					{		
						foreach($child as $index => $attr){
			
							if(count($attr['optionvalue']) > 0 && $attr['required'] && !array_key_exists($attr['productoption_id'],$options)) // if optionvalue exist or not. then only display form.otherwise form display only heading without option name
							{
								array_push($ommit_check,$attr['productoption_id']);
							}
						}
					}
				}	
			}
		}
			
		if(!$errors) {
			//before validating, get the total quantity of this variant in the cart
			$cart_total_qty = $product_helper->getTotalCartQuantity($product->variants->j2store_variant_id);

			//validate minimum / maximum quantity
			$error = $product_helper->validateQuantityRestriction($product->variants, $cart_total_qty, $quantity);
			if(!empty($error)) {
				$errors['error']['stock'] = $error;
			}

			//validate inventory
			if($product_helper->check_stock_status($product->variants, $cart_total_qty+$quantity) === false) {
				$errors['error']['stock'] = JText::_('J2STORE_OUT_OF_STOCK');
			}
		}
		if(!$errors) {
			//all good. Add the product to cart
			// create cart object out of item properties
			$item = new JObject;
			$item->user_id     = JFactory::getUser()->id;
			$item->product_id  = (int) $product->j2store_product_id;
			$item->variant_id  = (int) $product->variants->j2store_variant_id;
			$item->product_qty = J2Store::utilities()->stock_qty($quantity);
			$item->product_options = base64_encode(serialize($options));
			$item->product_type = $product->product_type;
			$item->vendor_id   = isset($product->vendor_id) ? $product->vendor_id : '0';
			// onAfterCreateItemForAddToCart: plugin can add values to the item before it is being validated /added
			// once the extra field(s) have been set, they will get automatically saved

			$results = J2Store::plugin()->event("AfterCreateItemForAddToCart", array( $item, $values ) );

			foreach ($results as $result)
			{
				foreach($result as $key=>$value)
				{
					$item->set($key,$value);
				}
			}

			// no matter what, fire this validation plugin event for plugins that extend the checkout workflow
			$results = array();
			$results =  J2Store::plugin()->event("BeforeAddToCart", array( $item, $values, $product, $product_options) );
			foreach($results as $result) {
				if (! empty ( $result['error'] )) {
					$errors['error']['general'] = $result['error'];
				}
			}
			// when there is some error from the plugin then the cart item should not be added
			if(!$errors){
				//add item to cart
				$cartTable = $model->addItem($item);

				if($cartTable === false) {
					//adding to cart is failed
					$errors['success'] = 0;
				} else {
					//adding cart is successful
					$errors['success'] = 1;
					$errors['cart_id'] = $cartTable->j2store_cart_id;
				}
			}
		}

		$json->result = $errors;

	}

}

