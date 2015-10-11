<?php
/**
 * @package J2Store
 * @copyright Copyright (c)2014-17 Ramesh Elamathi / J2Store.org
 * @license GNU GPL v3 or later
 */
// No direct access to this file
defined('_JEXEC') or die;
class J2StoreModelCoupons extends F0FModel {
	protected function onBeforeSave(&$data, &$table) {

		if(isset($data['products']) && !empty($data['products'])){
			$data['products'] =implode(',' , $data['products']);
		}else{
			$data['products'] ='';
		}

		if(isset($data['product_category']) && !empty($data['product_category'])){
			$data['product_category'] =implode(',' , $data['product_category']);
		}else{
			$data['product_category']='';
		}

		if(isset($data['brand_ids']) && !empty($data['brand_ids'])){
			$data['brand_ids'] =implode(',' , $data['brand_ids']);
		}else{
			$data['brand_ids']='';
		}

		return true;
	}

	public function getCoupon($code, $items) {
		$status = true;

		$db = JFactory::getDbo ();
		$user = JFactory::getUser ();

		$query = $db->getQuery ( true );
		$nullDate = $db->getNullDate ();
		$query->select ( '*' )->from ( '#__j2store_coupons' )->where ( 'coupon_code=' . $db->q ( $code ) )->where ( 'enabled=1' );
		$query->where ( "((valid_from = " . $db->q ( $nullDate ) . " OR valid_from < NOW()) AND (valid_to = " . $db->q ( $nullDate ) . " OR valid_to > NOW()))" );
		$db->setQuery ( $query );
		$row = $db->loadObject ();

		// now validate
		if ($row) {
			$params = J2Store::config ();
			// now get coupon history total
			$query = $db->getQuery ( true );
			$query->select ( 'COUNT(*) AS total' )->from ( '#__j2store_ordercoupons' )->where ( 'coupon_id=' . $db->q ( $row->j2store_coupon_id ) );
			$db->setQuery ( $query );
			$coupon_history = $db->loadResult ();

			$product_data = array ();

			$var = 'orderitem_finalprice_without_tax';
			if (! $params->get ( 'config_discount_before_tax', 1 )) {
				// discount applied after tax
				$var = 'orderitem_finalprice_with_tax';
			}

			// is used coupons count exceeds max use?
			if ($row->max_uses > 0 && ($coupon_history >= $row->max_uses)) {
				$status = false;
			}

			// is subtotal above min subtotal restriction.
			if (isset ( $row->min_subtotal ) && ( float ) $row->min_subtotal > 0) {

				// check subtotal
				$sub_total = 0;
				foreach ( $items as $item ) {
					$sub_total += $item->$var;
				}
				//echo round($row->min_subtotal,0);
				if (!empty($row->min_subtotal) && (float) $row->min_subtotal  > (float) $sub_total) {
					$status = false;
					JFactory::getApplication()->enqueueMessage(JText::_('J2STORE_COUPON_MINIMUM_SUBTOTAL_REQUIRED'), 'notice');

				}
			}

			//echo $status; exit;
			// is customer loged
			if ($row->logged && ! $user->id) {
				$status = false;
			}

			if ($user->id) {
				$query = $db->getQuery ( true );
				$query->select ( 'COUNT(*) AS total' )->from ( '#__j2store_ordercoupons' )->where ( 'coupon_id=' . $db->q ( $row->j2store_coupon_id ) )->where ( 'customer_id=' . $db->q ( $user->id ) );
				$db->setQuery ( $query );
				$customer_total = $db->loadResult ();
				if ($row->max_customer_uses > 0 && ($customer_total >= $row->max_customer_uses)) {
					$status = false;
				}
			}

			if($row->users){
				$users = explode(',',$row->users);
				if(!in_array($user->id , $users)){
					$status = false;
				}
			}

			// categories
			$coupon_categories_data = array ();
			if ($row->product_category) {
				$coupon_categories_data = explode ( ',', $row->product_category );
			}

			// products
			$coupon_products_data = array ();

			if ($row->products) {
				$coupon_products_data = explode ( ',', $row->products );
			}

			$product_data = array ();
			// products and categories
			if (count ( $coupon_categories_data ) || count ( $coupon_products_data )) {

				// products data
				foreach ( $items as $item ) {

					// first get the products
					if (in_array ( $item->product_id, $coupon_products_data )) {
						$product_data [] = $item->product_id;
						continue;
					}

					// now get the product data from categories. Applies only to article categories
					if ($item->product_source == 'com_content') {
						foreach ( $coupon_categories_data as $category_id ) {
							$query = $db->getQuery ( true );
							$query->select ( 'COUNT(*) AS total' )->from ( '#__content' )->where ( 'id=' . $db->q ( $item->product_id ) )->where ( 'catid=' . $db->q ( $category_id ) );

							$db->setQuery ( $query );
							if ($db->loadResult ()) {
								$product_data [] = $item->product_id;
							}
							continue;
						}
					}

				}
				if (! $product_data) {
					$status = false;
				}
			}
			
			//check brand id matches with the products brand id
			if(!empty($row->brand_ids)){
				$brand_ids = explode(',' ,$row->brand_ids);
				$manufacturer_data = array ();
				if(count($brand_ids)) {
					foreach ( $items as $item ) {	
						if(isset($item->cartitem->manufacturer_id) && !empty($item->cartitem->manufacturer_id) && in_array($item->cartitem->manufacturer_id , $brand_ids)){
							$manufacturer_data[] = $item->product_id;
							continue;
						}
					}
					if (! $manufacturer_data) {
						$status = false;
					}
				}	
			}
			
		} else {
			$status = false;
		}
		// if true
		if ($status) {
			$data = $row;
			if ($product_data) {
				$data->product = $product_data;
			} else {
				$data->product = array ();
			}
			return $data;
		}

		return false;
	}

	public function getTotalCouponDiscount($coupon_info, $items) {

		$app = JFactory::getApplication();
		$params = J2Store::config();
		$session = JFactory::getSession();
		$cart_helper = J2Store::cart();
		$discount_total = 0;

		if($session->has('coupon', 'j2store')) {

			$var = 'orderitem_finalprice_without_tax';
			if(!$params->get('config_discount_before_tax', 1)) {
				//discount applied after tax
				$var = 'orderitem_finalprice_without_tax';
			}

			if (!$coupon_info->product) {
				$sub_total = 0;

				foreach ($items as $item) {
					$sub_total += $item->$var;
				}

			} else {
				$sub_total = 0;
				foreach ($items as $item) {
					if (in_array($item->product_id, $coupon_info->product)) {
						$sub_total += $item->$var;
					}
				}
			}

			if ($coupon_info->value_type == 'F') {
				$coupon_info->value = min($coupon_info->value, $sub_total);
			}
			$product_array2 = array();
			foreach ($items as $item) {
				$discount = 0;

				if (!$coupon_info->product) {
					$status = true;
				} else {
					if (in_array($item->product_id, $coupon_info->product)) {
						$status = true;
					} else {
						$status = false;
					}
				}

				if ($status) {
					if ($coupon_info->value_type == 'F') {
						$discount = $coupon_info->value * ($item->$var / $sub_total);
					} elseif ($coupon_info->value_type == 'P') {
						$discount = $item->$var / 100 * $coupon_info->value;
					}

				}

				$discount_total += $discount;
			}

			if($coupon_info->free_shipping && $session->has('shipping_values', 'j2store')) {
				$shipping = $session->get('shipping_values', array(), 'j2store');
				$shipping_cost = $shipping['shipping_price']+$shipping['shipping_extra']+$shipping['shipping_tax'];
				$discount_total += $shipping_cost;
			}

		}

		return $discount_total;
	}

	protected function onAfterGetItem(&$record)
	{
		$record->product_category = explode(',',$record->product_category);
		$record->brand_ids = explode(',',$record->brand_ids);

	}


}

