<?php
/**
 * @package J2Store
 * @copyright Copyright (c)2014-17 Ramesh Elamathi / J2Store.org
 * @license GNU GPL v3 or later
 */
// No direct access to this file
defined ( '_JEXEC' ) or die ();


class J2StoreModelOrders extends F0FModel {

	protected $_order = null;

	protected $_orders = array();

	protected function onAfterGetItem(&$record) {
		$status = F0FModel::getTmpInstance('Orderstatuses', 'J2StoreModel')->getItem($record->order_state_id);
		$record->orderstatus_name = $status->orderstatus_name;
		$record->orderstatus_cssclass = $status->orderstatus_cssclass;
	}

	public function populateOrder($cartitems = array(), $order_id = null) {

		$orderTable = F0FTable::getAnInstance('Order', 'J2StoreTable')->getClone();

		if ( $order_id > 0 && ( $orderTable->load(array('order_id'=>$order_id))) && $orderTable->has_status( array( 5 ) ) ) {
			$order = $orderTable;
			//Customer is resuming an order. So delete the children. We have to re-initialise the order object
			$order->updateOrder();
		}else{
			$order = F0FTable::getAnInstance('Order', 'J2StoreTable')->getClone();
			$order->is_update = 0;
		}

		//get the cart items
		if(is_null($this->_order)) {
			if(!$cartitems) {
				$cartitems = F0FModel::getTmpInstance('Carts', 'J2StoreModel')->getItems();
			}
			$items = F0FModel::getTmpInstance('OrderItems', 'J2StoreModel')->setItems($cartitems)->getItems();

			$order->setItems($items);
			$order->getTotals();
			$this->_order = $order;
		}
		return $this;
	}

	function getOrder() {
		return $this->_order;
	}

	function initOrder($order_id = null) {
		$items = F0FModel::getTmpInstance('Carts', 'J2StoreModel')->getItems();
		$this->populateOrder($items, $order_id);
		return $this;
	}

	function validateOrder(&$order) {

		$app = JFactory::getApplication();
		$user = JFactory::getUser();
		$session = JFactory::getSession();

		$address_model = F0FModel::getTmpInstance('Addresses', 'J2StoreModel');

		//check if items are in cart
		if($order->getItemCount() < 1) {
			throw new Exception(JText::_('J2STORE_CART_NO_ITEMS'));
			return false;
		}

		//validate shipping

		//set shiping address
		if($user->id && $session->has('shipping_address_id', 'j2store')) {
			$shipping_address = $address_model->getItem($session->get('shipping_address_id', '', 'j2store'));
		} elseif($session->has('guest', 'j2store')) {
			$guest = $session->get('guest', array(), 'j2store');
			if($guest['shipping']) {
				$shipping_address = $guest['shipping'];
			}
		}else{
			$shipping_address = array();
		}

		$showShipping = false;
		if ($isShippingEnabled = $order->isShippingEnabled())
		{
			if (empty($shipping_address)) {
				throw new Exception(JText::_('J2STORE_CHECKOUT_NO_SHIPPING_ADDRESS_FOUND'));
				return false;
			}
		}else {
			$session->clear('shipping_method', 'j2store');
			$session->clear('shipping_values', 'j2store');
		}

		// Validate if billing address has been set.

		if ($user->id && $session->has('billing_address_id', 'j2store')) {
			$billing_address = $address_model->getItem($session->get('billing_address_id', '', 'j2store'));
		} elseif ($session->has('guest', 'j2store')) {
			$guest = $session->get('guest', array(), 'j2store');
			$billing_address = $guest['billing'];
		}

		if (empty($billing_address)) {
			throw new Exception(JText::_('J2STORE_CHECKOUT_NO_BILLING_ADDRESS_FOUND'));
			return false;
		}

		return true;
	}

	function loadItemsTemplate($order) {

		static $sets;
		if ( !is_array( $sets ) )
		{
			$sets = array( );
		}
		if ( !isset( $sets[$order->order_id] ) )
		{

			$app = JFactory::getApplication();
			$html = ' ';

			if(!empty($order->customer_language)) {
				$lang = JFactory::getLanguage();
				$lang->load('com_j2store', JPATH_ADMINISTRATOR, $order->customer_language);
				$lang->load('com_j2store', JPATH_SITE, $order->customer_language);
			}
			$view = J2Store::view();

			$view->set( 'order', $order);
			$view->set( 'params', J2Store::config());
			$view->setDefaultViewPath(JPATH_SITE.'/components/com_j2store/views/myprofile/tmpl');
			$view->setTemplateOverridePath(JPATH_SITE.'/templates/'.$view->getTemplate().'/html/com_j2store/myprofile');

			$html = $view->getOutput('orderitems');
			$sets[$order->order_id] = $html;
		}
		return $sets[$order->order_id];
	}

	public function buildQuery($overrideLimites = false) {
		$db = JFactory::getDbo();
		$query = parent::buildQuery($overrideLimites);
		$query->select($this->_db->qn('#__j2store_orderstatuses.orderstatus_name'));
		$query->select($this->_db->qn('#__j2store_orderstatuses.orderstatus_cssclass'));
		$query->select("CASE WHEN #__j2store_orders.invoice_prefix IS NULL or #__j2store_orders.invoice_number = 0 THEN
						#__j2store_orders.j2store_order_id
  					ELSE
						CONCAT(#__j2store_orders.invoice_prefix, #__j2store_orders.invoice_number)
					END
				 	AS invoice");
		$query->join('LEFT OUTER', '#__j2store_orderstatuses ON #__j2store_orders.order_state_id = #__j2store_orderstatuses.j2store_orderstatus_id');
		$query->select($this->_db->qn('#__j2store_orderinfos.billing_first_name'));
		$query->select($this->_db->qn('#__j2store_orderinfos.billing_last_name'));
		$query->join('LEFT OUTER', '#__j2store_orderinfos ON #__j2store_orders.order_id = #__j2store_orderinfos.order_id');


		$limit_orderstatuses = $this->getState('orderstatuses', '*');
		$limit_orderstatuses = explode(',', $limit_orderstatuses);

		if(!in_array('*', $limit_orderstatuses)) {
			$query->where('#__j2store_orders.order_state_id IN ('.implode(',', $limit_orderstatuses).')');
		}

		return $query;
	}



	function getOrderList($overrideLimits = false, $group = '') {

		if (empty($this->_orders))
		{
			$query = $this->getOrderListQuery($overrideLimits);

			if (!$overrideLimits)
			{
				$limitstart = $this->getState('limitstart');
				$limit = $this->getState('limit');
				$this->_orders = $this->_getList((string) $query, $limitstart, $limit, $group);
			}
			else
			{
				$this->_orders = $this->_getList((string) $query, 0, 0, $group);
			}
		}

		return $this->_orders;
	}

	function getOrderListQuery($overrideLimits = false, $group = '') {
		$db = $this->_db;

		$query = $db->getQuery(true)->select('#__j2store_orders.*')->from('#__j2store_orders');

		$query->select($this->_db->qn('#__j2store_orderstatuses.orderstatus_name'));

		$query->select($this->_db->qn('#__j2store_orderstatuses.orderstatus_cssclass'));

		$query->select("CASE WHEN #__j2store_orders.invoice_prefix IS NULL or #__j2store_orders.invoice_number = 0 THEN
				#__j2store_orders.j2store_order_id
				ELSE
				CONCAT(#__j2store_orders.invoice_prefix, #__j2store_orders.invoice_number)
				END
				AS invoice");
		$query->join('LEFT OUTER', '#__j2store_orderstatuses ON #__j2store_orders.order_state_id = #__j2store_orderstatuses.j2store_orderstatus_id');

		//get orderinfo table columns.
		$fields = $db->gettableColumns('#__j2store_orderinfos');
		unset($fields['order_id']);
		unset($fields['j2store_orderinfo_id']);

		foreach (array_keys($fields) as $field) {
			$query->select('#__j2store_orderinfos.'.$field);
		}
		$query->join('LEFT OUTER', '#__j2store_orderinfos ON #__j2store_orders.order_id = #__j2store_orderinfos.order_id');

		$query->select(' ( SELECT #__j2store_countries.country_name FROM #__j2store_countries WHERE #__j2store_countries.j2store_country_id = #__j2store_orderinfos.billing_country_id ) as billingcountry_name');
		$query->select(' ( SELECT #__j2store_countries.country_name FROM #__j2store_countries WHERE #__j2store_countries.j2store_country_id = #__j2store_orderinfos.shipping_country_id ) as shippingcountry_name');
		$query->select(' ( SELECT #__j2store_zones.zone_name FROM #__j2store_zones WHERE #__j2store_zones.j2store_zone_id = #__j2store_orderinfos.billing_zone_id ) as billingzone_name');
		$query->select(' ( SELECT #__j2store_zones.zone_name FROM #__j2store_zones WHERE #__j2store_zones.j2store_zone_id = #__j2store_orderinfos.shipping_zone_id ) as shippingzone_name');

		$query->select($this->_db->qn('#__j2store_ordercoupons.coupon_code'));
		$query->join('LEFT OUTER', '#__j2store_ordercoupons ON #__j2store_orders.order_id = #__j2store_ordercoupons.order_id');

		$query->select($this->_db->qn('#__j2store_ordershippings.ordershipping_name'));
		$query->select($this->_db->qn('#__j2store_ordershippings.ordershipping_tracking_id'));
		$query->join('LEFT OUTER', '#__j2store_ordershippings ON #__j2store_orders.order_id = #__j2store_ordershippings.order_id');

		$this->_buildTotalQueryWhere($query);
		$this->_buildQueryOrderBy($query);
	//	echo $query;
		return $query;
	}

	function buildCountQuery() {
		$subquery = $this->getOrderListQuery();
		$subquery->clear('order');
		$query = $this->_db->getQuery(true)
		->select('COUNT(*)')
		->from("(" . (string) $subquery . ") AS a");
		return $query;
	}

	function getOrdersTotal() {
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$state = $this->getFilterValues();

		if($state->moneysum == 1) {
			$query->select('SUM(#__j2store_orders.order_total)');
		} else {
			$query->select('COUNT(*)');
		}
		$query->from('#__j2store_orders');
		$this->_buildTotalQueryWhere($query);
		//echo $query;
		$db->setQuery($query);
		return $db->loadResult();
	}

	protected function _buildQueryOrderBy(&$query){
		$db =$this->_db;
		if(!empty($this->state->filter_order)) {
			$query->order($this->state->filter_order.' '.$this->state->filter_order_Dir);
		}
		$query->order('#__j2store_orders.created_on DESC');
	}

	private function getFilterValues()
	{
		return (object)array(
				'search'		=> $this->getState('search',null,'string'),
				'title'			=> $this->getState('title',null,'string'),
				'user_id'		=> $this->getState('user_id',null,'int'),
				'order_id'		=> $this->getState('order_id',null,'int'),
				'orderstate'		=> $this->getState('orderstate',null,'int'),
				'processor'		=> $this->getState('processor',null,'string'),
				'paykey'		=> $this->getState('paykey',null,'string'),
				'since'			=> $this->getState('since',null,'string'),
				'until'			=> $this->getState('until',null,'string'),
				'groupbydate'	=> $this->getState('groupbydate',null,'int'),
				'groupbylevel'	=> $this->getState('groupbylevel',null,'int'),
				'moneysum'		=> $this->getState('moneysum',null,'float'),
				'coupon_id'		=> $this->getState('coupon_id',null,'int'),
				'coupon_code'		=> $this->getState('coupon_code',null,'string'),
				'nozero'		=> $this->getState('nozero',null,'int'),
				'frominvoice'		=> $this->getState('frominvoice',null,'int'),
				'toinvoice'		=> $this->getState('toinvoice',null,'int'),
				'orderstatus'		=> $this->getState('orderstatus',array()),
		);
	}

	function _buildTotalQueryWhere(&$query){
		$db =$this->_db;
		jimport('joomla.utilities.date');
		$state = $this->getFilterValues();

		if(isset($state->orderstatus) && !empty($state->orderstatus) && is_array($state->orderstatus)) {
			if(!in_array('*' ,$state->orderstatus)){
				$query->where($db->qn('#__j2store_orders').'.'.$db->qn('order_state_id').' IN ('.
					implode(',',$state->orderstatus).')');
			}
		}
		//order status
		if($state->orderstate) {
			$states_temp = explode(',', $state->orderstate);
			$states = array();
			foreach($states_temp as $s) {
				$s = strtoupper($s);
				//5=incomplete, 4=pending, 3=failed, 1=confirmed
			//	if(!in_array($s, array(1,3,4,5))) continue;
				$states[] = $db->q($s);
			}
			if(!empty($states)) {
				$query->where(
						$db->qn('#__j2store_orders').'.'.$db->qn('order_state_id').' IN ('.
						implode(',',$states).')'
				);
			}
		}


		if($state->paykey) {
			$query->where(
					$db->qn('#__j2store_orders').'.'.$db->qn('orderpayment_type').' LIKE '.
					$db->q('%'.$state->paykey.'%')
			);
		}


		if($state->user_id) {
			$query->where(
					$db->qn('#__j2store_orders').'.'.$db->qn('user_id').'='.$db->q($state->user_id)
			);
		}

		//since
		$since = trim($state->since);
		if(empty($since) || ($since == '0000-00-00') || ($since == '0000-00-00 00:00:00')) {
			$since = '';
		} else {
			$regex = '/^\d{1,4}(\/|-)\d{1,2}(\/|-)\d{2,4}[[:space:]]{0,}(\d{1,2}:\d{1,2}(:\d{1,2}){0,1}){0,1}$/';
			if(!preg_match($regex, $since)) {
				$since = '2001-01-01';
			}
			$jFrom = new JDate($since);
			$since = $jFrom->toUnix();
			if($since == 0) {
				$since = '';
			} else {
				$since = $jFrom->toSql();
			}
			// Filter from-to dates
			$query->where(
					$db->qn('#__j2store_orders').'.'.$db->qn('created_on').' >= '.
					$db->q($since)
			);
		}

		// "Until" queries
		$until = trim($state->until);
		if(empty($until) || ($until == '0000-00-00') || ($until == '0000-00-00 00:00:00')) {
			$until = '';
		} else {
			$regex = '/^\d{1,4}(\/|-)\d{1,2}(\/|-)\d{2,4}[[:space:]]{0,}(\d{1,2}:\d{1,2}(:\d{1,2}){0,1}){0,1}$/';
			if(!preg_match($regex, $until)) {
				$until = '2037-01-01';
			}
			$jFrom = new JDate($until);
			$until = $jFrom->toUnix();
			if($until == 0) {
				$until = '';
			} else {
				$until = $jFrom->toSql();
			}
			$query->where(
					$db->qn('#__j2store_orders').'.'.$db->qn('created_on').' <= '.
					$db->q($until)
			);
		}
		// No-zero toggle
		if(!empty($state->nozero)) {
			$query->where(
					$db->qn('#__j2store_orders').'.'.$db->qn('order_total').' > '.
					$db->q('0')
			);
		}

	/* 	if(!empty($state->moneysum)) {
			$query->where(
					$db->qn('#__j2store_orders').'.'.$db->qn('order_total').' = '.
					$db->q($state->moneysum)
			);
		} */

		//from invoice number
		if($state->frominvoice) {
			$query->where(
					$db->qn('#__j2store_orders').'.'.$db->qn('invoice_number').' >= '.$db->q($state->frominvoice)
			);
		}

		//to invoice number
		if($state->toinvoice) {
			$query->where(
					$db->qn('#__j2store_orders').'.'.$db->qn('invoice_number').' <= '.$db->q($state->toinvoice)
			);
		}

		if($state->search){
			$search = '%'.trim($state->search).'%';
			 $query->where(
			 				$db->qn('#__j2store_orders').'.'.$db->qn('order_id').' LIKE '.$db->q($search).' OR '.
			 				$db->qn('#__j2store_orders').'.'.$db->qn('j2store_order_id').' LIKE '.$db->q($search).'OR '.
			 				$db->qn('#__j2store_orders').'.'.$db->qn('user_email').' LIKE '.$db->q($search).'OR '.
			 				$db->qn('#__j2store_orders').'.'.$db->qn('order_state').' LIKE '.$db->q($search).'OR '.
	 		 				$db->qn('#__j2store_orders').'.'.$db->qn('orderpayment_type').' LIKE '.$db->q($search).'OR'.
			 				' CONCAT ('.$db->qn('#__j2store_orderinfos').'.'.$db->qn('billing_first_name').', " ", '.$db->qn('#__j2store_orderinfos').'.'.$db->qn('billing_last_name').') LIKE '.$db->q($search).'OR '.
			 				$db->qn('#__j2store_orderinfos').'.'.$db->qn('billing_first_name').' LIKE '.$db->q($search).'OR'.
			 				$db->qn('#__j2store_orderinfos').'.'.$db->qn('billing_last_name').' LIKE '.$db->q($search)
			 		) ;
		}

		if($state->coupon_code){
			$query->where(
					$db->qn('#__j2store_ordercoupons').'.'.$db->qn('coupon_code').' LIKE '.
					$db->q('%'.$state->coupon_code.'%')
			);
		}
	}

	public function export($data=array(), $auto=false) {
		$app = JFactory::getApplication();
		$dispatcher = JDispatcher::getInstance();
		JPluginHelper::importPlugin ('j2store');
		$currency = J2Store::currency();


		if($auto) {
			//trigger the plugin event
			$results = $dispatcher->trigger( "onJ2StoreBeforeOrderExport", array() );

			if (is_array($results) && count($results))
			{
				$data = array_merge($data, $results[0]);
			}

		}


		$paystate = '';
		//order status filter
		if(isset($data['export_status']) && is_array($data['export_status'])) {
			$paystate = implode(',' ,$data['export_status']);
		}

	/* 	$rows = $this->clearState()
		->frominvoice($data['export_from'])
		->toinvoice($data['export_to'])
		->since($data['export_from_date'])
		->until($data['export_to_date'])
		->paystate($paystate)
		->getOrdersExport(); */

		$rows = $this->getOrderList();
		if(count($rows) > 0) {
			//process the totals
			$max = 1;

			$new_orders =array();
			foreach ($rows as $key => $order) {
				$orderTable = F0FTable::getAnInstance('Order','J2StoreTable')->getClone();
				$orderTable->load($order->j2store_order_id);
				$orderitems = $orderTable->getItems();
				$new_order = array();
				$all_values = JArrayHelper::fromObject($order);
				$new_order = array_merge($new_order, $all_values );
				$new_order['billing_country_name'] = $order->billingcountry_name;
				$new_order['shipping_country_name'] = $order->shippingcountry_name;

				$new_order['billing_zone_name'] = $order->billingzone_name;
				$new_order['shipping_zone_name'] = $order->shippingzone_name;

				$processed_variables = array();

				$processed_variables['invoice'] = $order->invoice;
				$processed_variables['order_id'] = $order->order_id;
				$processed_variables['created_on'] = $order->created_on;
				$processed_variables['customer_name'] = $order->billing_first_name .' '.$order->billing_last_name;
				$processed_variables['customer_email'] = $order->user_email;
				$processed_variables['currency_code'] = $order->currency_code;
				$processed_variables['order_subtotal'] = $currency->format( $order->order_subtotal, $order->currency_code, $order->currency_value, false);
				$processed_variables['order_tax'] = $currency->format( $order->order_tax, $order->currency_code, $order->currency_value, false);
				$processed_variables['order_shipping'] = $currency->format( $order->order_shipping, $order->currency_code, $order->currency_value, false);
				$processed_variables['order_shipping_tax'] = $currency->format( $order->order_shipping_tax, $order->currency_code, $order->currency_value, false);
				$processed_variables['order_surcharge'] = $currency->format( $order->order_surcharge, $order->currency_code, $order->currency_value, false);
				$processed_variables['order_discount'] = $currency->format( $order->order_discount, $order->currency_code, $order->currency_value, false);
				$processed_variables['order_total'] = $currency->format( $order->order_total, $order->currency_code, $order->currency_value, false);

				$new_order = array_merge($processed_variables, $new_order);

				$new_order['orderstatus_name'] = JText::_($order->orderstatus_name);
				$new_order['orderpayment_type'] = JText::_($order->orderpayment_type);

				//now process order items
				$i = 1;
				//$new_order['orderitems'] = $orderitems;
				foreach ($orderitems as $item)
				{
					//prepare the array
					$new_order['product_id_'.$i] =$item->product_id;
					//$new_order['product_type_'.$i] =$item->product_type;
					$new_order['product_sku_'.$i] =$item->orderitem_sku;
					$new_order['product_name_'.$i] =$item->orderitem_name;
					$new_order['product_options_'.$i] =$this->getItemDescription($item);
					$new_order['product_quantity_'.$i] =$item->orderitem_quantity;
					$new_order['product_tax_'.$i] =$currency->format($item->orderitem_tax, $order->currency_code,$order->currency_value, false);
					$new_order['product_total_with_tax_'.$i] =$currency->format($item->orderitem_finalprice_with_tax, $order->currency_code,$order->currency_value, false);
					$new_order['product_total_without_tax_'.$i] =$currency->format($item->orderitem_finalprice_without_tax, $order->currency_code,$order->currency_value, false);
					$i++;
				}

				//unset variables
				$unset_variables = array(
						'billingcountry_name',
						'billingzone_name',
						'shippingcountry_name',
						'shippingzone_name',
						'invoice_prefix',
						'invoice_number',
						'order_state',
						'orderstatus_cssclass',
						'all_billing',
						'all_shipping',
						'all_payment',
						'j2store_order_id',
						'user_email'
				);

				$this->formatCustomFields('billing', $new_order['all_billing'], $new_order);
				$this->formatCustomFields('shipping', $new_order['all_shipping'], $new_order);
				$this->formatCustomFields('payment', $new_order['all_payment'], $new_order);

				foreach($unset_variables as $var) {
					unset($new_order[$var]);
				}
				$new_orders[] = JArrayHelper::toObject($new_order);
			}
			return $new_orders;
		}
		return true;
	}


	function formatCustomFields($type, $data_field, &$order) {

		$address = F0FTable::getAnInstance('Address', 'J2StoreTable');
		$fields = J2Store::getSelectableBase()->getFields($type, $address, 'address', '', true);
		foreach($fields as $field) {
			$order[$type.'_'.JString::strtolower($field->field_namekey)] = '';
		}

		$registry = new JRegistry();
		$registry->loadString(stripslashes($data_field), 'JSON');
		$custom_fields = $registry->toObject();

		$row = F0FTable::getAnInstance('Orderinfo','J2StoreTable');
		if(isset($custom_fields) && count($custom_fields)) {
			foreach($custom_fields as $namekey=>$field) {
				if(!property_exists($row, $type.'_'.strtolower($namekey)) && !property_exists($row, 'user_'.$namekey) && $namekey !='country_id' && $namekey != 'zone_id' && $namekey != 'option' && $namekey !='task' && $namekey != 'view' && $namekey !='email' ) {
					if(is_object($field)) {
						$string = '';
						if(is_array($field->value)) {
							$k = count($field->value); $i = 1;
							foreach($field->value as $value) {
								$string .=JText::_($value);
								if($i != $k) {
									$string .='|';
								}
								$i++;
							}

						}elseif(is_object($field->value)) {
                            //convert the object into an array
                            $obj_array = JArrayHelper::fromObject($field->value);
                            $k = count($obj_array); $i = 1;
                            foreach($obj_array as $value) {
                                $string .=JText::_($value);
                                if($i != $k) {
                                    $string .='|';
                                }
                                $i++;
                            }

						}elseif(J2Store::utilities()->isJson(stripslashes($field->value))) {

							$json_values = json_decode(stripslashes($field->value));

							if(is_array($json_values)) {
								$k = count($json_values ); $i = 1;
								foreach($json_values as $value){
									$string .= JText::_($value);
									if($i != $k) {
										$string .='|';
									}
									$i++;
								}
							} else {
								$string .= JText::_($field->value);
							}

						} else {

							$string = JText::_($field->value);
						}
						if(!empty($string)) {
							$order[$type.'_'.JString::strtolower($namekey)] = $string;
						}
					}
				}
			}
		}
	}


	function getItemDescription($item) {
		$desc = '';

		//productoptions
		if (!empty($item->orderitemattributes)) {
			//first convert from JSON to array

			/* $registry = new JRegistry;
			$registry->loadString(stripslashes($item->orderitem_attribute_names), 'JSON'); */
			$product_options =$item->orderitemattributes;
			if(count($product_options) >0 ) {
				$first = true;
				foreach ($product_options as $option) {

					if($first) {
						$desc .= '';
					} else {
						$desc .= ' | ';
					}
					$desc .=$option->orderitemattribute_name.':'.$option->orderitemattribute_value;

					$first = false;
				}
			}

		}
		return $desc;
	}

	/**
	 * Method to cancel unpaid orders
	 */
	public function cancel_unpaid_orders() {

		$config = J2Store::config();

		// Get today's date
		JLoader::import('joomla.utilities.date');
		$jNow	 = new JDate();
		$now	 = $jNow->toUnix();

		$held_duration = $config->get('hold_stock');

		if ( $held_duration < 1 || $config->get('enable_inventory', 0) != 1 ) 	return;

		$date = date( "Y-m-d H:i:s", strtotime( '-' . abs( intval( $held_duration )) . ' MINUTES', $now) );

		$db = JFactory::getDbo();
		$query = $db->getQuery(true)->select('order_id')->from('#__j2store_orders')->where('modified_on <'.$db->q($date))
							->where('order_state_id IN (4,5)');
		$db->setQuery($query);
		$unpaid_orders = $db->loadObjectList();
		if ( $unpaid_orders ) {
			foreach ( $unpaid_orders as $unpaid_order ) {

				$order = F0FTable::getInstance('Order', 'J2StoreTable')->getClone();
				if($order->load(array('order_id'=>$unpaid_order->order_id)) ) {

					if ( !empty($order->order_id) ) {
						//set order status as cancelled
						//first restore order stock

						$old_status = $order->order_state_id;

						$order->update_status(6);

						//if status is new, then stock may not got reduced.
						if($old_status == 4) {
							$order->restore_order_stock();
						}
						$order->add_history(JText::_('J2STORE_ORDER_CANCELLED_TIME_LIMIT_EXPIRED'));
					}
				}

			}
		}
	}

}
