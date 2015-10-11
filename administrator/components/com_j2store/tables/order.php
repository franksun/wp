<?php
/**
 * @package J2Store
* @copyright Copyright (c)2014-17 Ramesh Elamathi / J2Store.org
* @license GNU GPL v3 or later
*/
// No direct access to this file
defined ( '_JEXEC' ) or die ();


class J2StoreTableOrder extends F0FTable
{

	/* OrderItems table object */
	private $_items = array ();

	private $_shipping_methods = '';

	/** @var array holding country id and zone id for billing */
	protected $_billing_address = null;

	/** @var array holding country id and zone id for billing */
	protected $_shipping_address = null;

	/** @var array      tax & shipping geozone objects */
	protected $_billing_geozones = array();
	protected $_shipping_geozones = array();

	/** @var array      The shipping totals JObjects */
	protected $_shipping_totals = null;

	/** @var boolean Has the recurring item been added to the order?
	 * This is used exclusively during orderTotal calculation
	*/
	protected $_recurringItemExists = false;

	/** @var object And OrderItem Object, only populated if the orderitem recurs
	 */
	protected $_recurringItem = false;

	/** @var array An array of J2StoreTableTaxRates objects (the unique taxrates for this order) */
	public $_taxrates = array();

	/** @var array (Base tax rates for this order) This is not the same as the item tax rates. This is based on the shop address*/
	public $_shop_taxrates = array();

	/** @var array An array of tax amounts, indexed by tax_rate_id */
	protected $_taxrate_amounts = array();

	/** @var array An array of J2StoreTableTaxRates objects (the unique taxclasses for this order) */
	protected $_taxclasses = array();

	/** @var array An array of tax amounts, indexed by tax_class_id */
	protected $_taxclass_amounts = array();

	protected $_ordertaxes = array();

	/** @var array An array of J2StoreTableCoupons objects */
	protected $_coupons = array();

	/** @var array An array of J2StoreTableOrderCoupons objects */
	protected $_ordercoupons = array();

	/** @var array An array of J2StoreTableOrderVouchers objects */
	protected $_ordervouchers = array();

	/** @var array An array of J2StoreTableOrderInfo object*/
	protected $_orderinfo = null;

	/** @var array An array of J2StoreTableOrderVouchers objects */
	protected $_orderdownloads = array();

	protected $_orderhistory = array();



	public function setItems($items) {
		foreach ($items as $item) {
			$this->addItem($item);
		}
	}

	public function getItems() {

		if (empty($this->_items) && !empty($this->order_id))
		{
			// TODO Do this?  How will this impact Site::TiendaControllerCheckout->saveOrderItems()?
			//retrieve the order's items
			$model = F0FModel::getTmpInstance( 'OrderItems', 'J2StoreModel' )
					->order_id($this->order_id);
			$model->setState( 'order', 'tbl.orderitem_name' );
			$model->setState( 'direction', 'ASC' );
			$orderitems = $model->getList();
			foreach ($orderitems as $orderitem)
			{
				unset($table);
				$table = F0FTable::getInstance('OrderItem', 'J2StoreTable' )->getClone();
					$table->load( $orderitem->j2store_orderitem_id);
					$table->orderitem_quantity = J2Store::utilities()->stock_qty($table->orderitem_quantity);
					$table->orderitemattributes = $orderitem->orderitemattributes;
					$this->addItem( $table );
			}
		}

        $items = $this->_items;
		if (!is_array($items))
		{
			$items = array();
			$this->_items = $items;
		}

		return $this->_items;
	}

	public function getItemCount() {
		if(isset($this->_items)) {
			return count ($this->_items);
		} else {
			return 0;
		}
	}

	public function addItem($item) {

		$orderItem = F0FTable::getAnInstance('OrderItem', 'J2StoreTable')->getClone();
		if (is_array($item))
		{
			$orderItem->bind( $item );
		}
		elseif (is_object($item) && is_a($item, 'J2StoreTableOrderItem'))
		{
			$orderItem = $item;
		}
		elseif (is_object($item))
		{
			$orderItem->product_id = $item->product_id;
			$orderItem->variant_id = $item->variant_id;
			$orderItem->orderitem_quantity = $item->orderitem_quantity;
			$orderItem->vendor_id  = $item->vendor_id;
			$orderItem->orderitemattributes = $item->orderitemattributes;
			$orderItem->orderitem_attributes = $item->orderitem_attributes;
		}
		else
		{
			$orderItem->product_id = $item;
			$orderItem->orderitem_quantity = '1';
			$orderItem->vendor_id  = '0';
			$orderItem->orderitem_attributes = '';
		}

		// Use hash to separate items when customer is buying the same product from multiple vendors
		// and with different attribs


		//$hash = intval($orderItem->product_id).".".intval($orderItem->variant_id).".".intval($orderItem->vendor_id).".".@$orderItem->orderitem_raw_attributes;
		$hash = intval($orderItem->cartitem_id).".".intval($orderItem->product_id).".".intval($orderItem->variant_id).".".intval($orderItem->vendor_id).".".$orderItem->orderitem_attributes;

		if (!empty($this->_items[$hash]))
		{
			// merely update quantity if item already in list
			$this->_items[$hash]->orderitem_quantity += $orderItem->orderitem_quantity;
		}
		else
		{
			$this->_items[$hash] = $orderItem;
		}

	}

	/**
	 * Method to validate stock in an order. Called only before placing the order.
	 * @return boolean True if successful | False if a condition does not match
	 */

	public function validate_order_stock() {

		$product_helper = J2Store::product ();
		$utilities = J2Store::utilities();

		$items = $this->getItems();
		if(count($items) < 1) return true;

		$quantity_in_cart = $this->get_orderitem_stock($items);
		foreach ( $items as $item) {

			// check quantity restrictions
			if ($item->cartitem->quantity_restriction && J2Store::isPro()) {
				// get quantity restriction
				$product_helper->getQuantityRestriction ( $item->cartitem);

				$quantity = $quantity_in_cart [$item->variant_id];
				$min = $item->cartitem->min_sale_qty;
				$max = $item->cartitem->max_sale_qty;

				if ($max && $max > 0) {
					if ($quantity > $max) {
						JFactory::getApplication ()->enqueueMessage ( JText::sprintf ( "J2STORE_CART_ITEM_MAXIMUM_QUANTITY_REACHED", $item->orderitem_name, $utilities->stock_qty($max), $utilities->stock_qty($quantity) ) );
						return false;
					}
				}
				if ($min && $min > 0) {
					if ($quantity < $min) {
						JFactory::getApplication ()->enqueueMessage ( JText::sprintf ( "J2STORE_CART_ITEM_MINIMUM_QUANTITY_REQUIRED", $item->orderitem_name, $utilities->stock_qty($min), $utilities->stock_qty($quantity) ) );
						return false;
					}
				}
			}

			if ($product_helper->managing_stock ( $item->cartitem ) && $product_helper->backorders_allowed ( $item->cartitem ) == false) {
				$productQuantity = F0FTable::getInstance ( 'ProductQuantity', 'J2StoreTable' )->getClone ();
				$productQuantity->load ( array (
						'variant_id' => $item->variant_id
				) );

				// no stock, right now?
				if ($productQuantity->quantity < 1) {
					JFactory::getApplication ()->enqueueMessage ( JText::sprintf ( "J2STORE_CART_ITEM_STOCK_NOT_AVAILABLE", $item->orderitem_name) );
					return false;
				}

				// not enough stock ?
				if ($productQuantity->quantity > 0 && $quantity_in_cart [$item->variant_id] > $productQuantity->quantity) {
					JFactory::getApplication ()->enqueueMessage ( JText::sprintf ( "J2STORE_CART_ITEM_STOCK_NOT_ENOUGH_STOCK", $item->orderitem_name, $utilities->stock_qty($productQuantity->quantity) ) );
					return false;
				}
			}
		}
		return true;
	}

	public function get_orderitem_stock($items) {
		//sort by variant
		$quantities = array();
		foreach($items as $item) {
			if(!isset($quantities[$item->variant_id])) {
				$quantities[$item->variant_id] = 0;
			}
			$quantities[$item->variant_id] += $item->orderitem_quantity;
		}
		return $quantities;
	}

	/**
	 * Gets cross sells based on the items in the cart.
	 *
	 * @return array cross_sells (item ids)
	 */
	public function get_cross_sells() {
		$cross_sells = array();
		$in_cart = array();
		if ( sizeof( $this->getItems() ) > 0 ) {
			foreach ( $this->getItems() as $item ) {
				if ( $item->orderitem_quantity > 0 ) {
					$item_cross_sells = implode(',', $item->cartitem->cross_sells);
					if(count($item_cross_sells)) {
						$cross_sells = array_merge($item_cross_sells, $cross_sells );
						$in_cart[] = $item->product_id;
					}
				}
			}
		}
		$cross_sells = array_diff( $cross_sells, $in_cart );
		return $cross_sells;
	}

	public function getTotals($taxes=true) {

		$params = J2Store::config();


		$this->order_discount = 0;

		//set the order information
		$this->setOrderInformation();

		// initialise tax
		if($taxes) {
			$this->setOrderTaxTotals();
		}

		$this->getOrderProductTotals();

		// then calculate shipping total
		$this->getOrderShippingTotals();

		// discount
		$this->getOrderDiscountTotals();

		// then calculate the tax
		$this->getOrderTaxTotals();

		// this goes last, to be sure it gets the fully adjusted figures
		//	$this->calculateVendorTotals();

		// sum totals
		$subtotal =
		$this->order_subtotal
		+ $this->order_shipping
		+ $this->order_shipping_tax
		;

	//	if($params->get('config_including_tax', 0) != 1 ) {
			$subtotal = $subtotal + $this->order_tax;
	//	}

		$discount_total =
		$this->order_discount
		+ $this->order_credit
		;

		if ($discount_total > $subtotal) {
			$discount_total = $subtotal;
		}

		$total = $subtotal - $discount_total;

		//if surcharge is set add that as well
		if(isset($this->order_surcharge)) {
			$total = $total + $this->order_surcharge;
		}
		// set object properties
		$this->order_total      = $total;

		// We fire just a single plugin event here and pass the entire order object
		// so the plugins can override whatever they need to
		J2Store::plugin()->event("CalculateOrderTotals", array( $this ) );

	}

	/**
	 * Calculates the product total (aka subtotal)
	 * using the array of items in the order object
	 *
	 * @return unknown_type
	 */
	function getOrderProductTotals()
	{
		$subtotal = 0.00;
		$subtotal_ex_tax = 0.00;

		// TODO Must decide what we want these methods to return; for now, null
		$items = $this->getItems();
		if (!is_array($items))
		{
			$this->order_subtotal = $subtotal;
			$this->order_subtotal_ex_tax = $subtotal_ex_tax;
			return;
		}
		// calculate product subtotal
		foreach ($items as $item)
		{
			$subtotal += $item->orderitem_finalprice;
			$subtotal_ex_tax += $item->orderitem_finalprice_without_tax;
		}

		// set object properties
		$this->order_subtotal   = $subtotal;
		$this->order_subtotal_ex_tax  = $subtotal_ex_tax;
		J2Store::plugin()->event("CalculateProductTotals", array( $this) );
	}

	function getSubtotal() {
		return $this->order_subtotal;
	}

	function getOrderTaxRates() {
		if(count($this->_ordertaxes) < 1 && !empty($this->order_id)) {
			$this->_ordertaxes = F0FModel::getTmpInstance('OrderTaxes', 'J2StoreModel')->order_id($this->order_id)->getList();
		}
		return $this->_ordertaxes;
	}


	function setOrderTaxTotals() {
		$items = $this->getItems();

		$params = J2Store::config();

		foreach($items as $item) {

			$price = $item->orderitem_price + $item->orderitem_option_price;
			$line_price = $price * $item->orderitem_quantity;

			if(!isset($item->orderitem_taxprofile_id) || $item->orderitem_taxprofile_id < 1) {

				//product not taxable.
				$item->orderitem_finalprice = $line_price;
				$item->orderitem_finalprice_with_tax =  $line_price;
				$item->orderitem_finalprice_without_tax = $line_price;
				$item->orderitem_per_item_tax = 0;
				$item->orderitem_tax = 0;

			}elseif($item->orderitem_taxprofile_id) {


				/** Price includes tax. So calculate base prices and then item price
				 *
				 */
				if($params->get('config_including_tax', 0)) {

					//get the base rates. This is based on the shop address.
					$shop_taxrates = F0FModel::getTmpInstance('Taxprofiles', 'J2StoreModel')->getBaseTaxRates($line_price, $item->orderitem_taxprofile_id, 1);
					$item_taxrates = F0FModel::getTmpInstance('Taxprofiles', 'J2StoreModel')->getTaxwithRates($line_price, $item->orderitem_taxprofile_id, 1);
					/**
					 * ADJUST TAX - Calculations when base tax is not equal to the item tax
					 */
					if($shop_taxrates->taxtotal !== $item_taxrates->taxtotal) {

						// Work out a new base price without the shop's base tax
						// Now we have a new item price (excluding TAX)
						$line_subtotal     = $line_price - $shop_taxrates->taxtotal;

						//now get the item taxes based on the adjusted line total
						$taxrates = F0FModel::getTmpInstance('Taxprofiles', 'J2StoreModel')->getTaxwithRates($line_subtotal, $item->orderitem_taxprofile_id, 0);
						$taxes = $taxrates->taxes;
						$item->orderitem_per_item_tax = 0;
						if(count($taxes)) {
							foreach ($taxes as $taxrate_id=>$tax_rate) {
								if (!isset($this->_taxrates[$taxrate_id])) {
									$this->_taxrates[$taxrate_id]['name'] = $tax_rate['name'];
									$this->_taxrates[$taxrate_id]['rate'] = $tax_rate['rate'];
									$this->_taxrates[$taxrate_id]['total'] = ($tax_rate['amount']);
								} else {
									$this->_taxrates[$taxrate_id]['name'] = $tax_rate['name'];
									$this->_taxrates[$taxrate_id]['rate'] = $tax_rate['rate'];
									$this->_taxrates[$taxrate_id]['total'] += ($tax_rate['amount']);
								}
							}
							$item->orderitem_per_item_tax = $taxrates->taxtotal / $item->orderitem_quantity;
						}

						$line_subtotal_tax = $taxrates->taxtotal;

						// Adjusted price (this is the price including the new tax rate)
						$adjusted_price    = ( $line_subtotal + $line_subtotal_tax ) / $item->orderitem_quantity;

						$item->orderitem_total_price = $adjusted_price;
						/*
						$item->orderitem_finalprice = $adjusted_price * $item->orderitem_quantity;
						$item->orderitem_finalprice_with_tax =  $adjusted_price *  $item->orderitem_quantity;
						$item->orderitem_finalprice_without_tax =  ($adjusted_price * $item->orderitem_quantity) - $line_subtotal_tax; */

					/**
					 * Regular tax calculation (customer inside base and the tax class is unmodified
					 */
					} else {

						// Work out a new base price without the item tax
						$taxrates = $item_taxrates;

						$taxes = $taxrates->taxes;
						$item->orderitem_per_item_tax = 0;
						if(count($taxes)) {
							foreach ($taxes as $taxrate_id=>$tax_rate) {
								if (!isset($this->_taxrates[$taxrate_id])) {
									$this->_taxrates[$taxrate_id]['name'] = $tax_rate['name'];
									$this->_taxrates[$taxrate_id]['rate'] = $tax_rate['rate'];
									$this->_taxrates[$taxrate_id]['total'] = ($tax_rate['amount']);
								} else {
									$this->_taxrates[$taxrate_id]['name'] = $tax_rate['name'];
									$this->_taxrates[$taxrate_id]['rate'] = $tax_rate['rate'];
									$this->_taxrates[$taxrate_id]['total'] += ($tax_rate['amount']);
								}
							}
							$item->orderitem_per_item_tax = $taxrates->taxtotal / $item->orderitem_quantity;
						}

						// Now we have a new item price (excluding TAX)
						$line_subtotal     = $line_price - $taxrates->taxtotal;
						$line_subtotal_tax = $taxrates->taxtotal;
						$item->orderitem_total_price = $line_subtotal;

						/* $item->orderitem_finalprice = $line_price;
						$item->orderitem_finalprice_with_tax =  $line_price;
						$item->orderitem_finalprice_without_tax =  $line_subtotal; */
					}

				} else {
					/**
					 * Prices exclude tax
					 */

					//get the taxes
					$taxrates = F0FModel::getTmpInstance('Taxprofiles', 'J2StoreModel')->getTaxwithRates($line_price, $item->orderitem_taxprofile_id, 0);
					$taxes = $taxrates->taxes;
					$item->orderitem_per_item_tax = 0;
					if(count($taxes)) {
						foreach ($taxes as $taxrate_id=>$tax_rate) {
							if (!isset($this->_taxrates[$taxrate_id])) {
								$this->_taxrates[$taxrate_id]['name'] = $tax_rate['name'];
								$this->_taxrates[$taxrate_id]['rate'] = $tax_rate['rate'];
								$this->_taxrates[$taxrate_id]['total'] = ($tax_rate['amount']);
							} else {
								$this->_taxrates[$taxrate_id]['name'] = $tax_rate['name'];
								$this->_taxrates[$taxrate_id]['rate'] = $tax_rate['rate'];
								$this->_taxrates[$taxrate_id]['total'] += ($tax_rate['amount']);
							}
						}
						$item->orderitem_per_item_tax = $taxrates->taxtotal / $item->orderitem_quantity;
					}

					$line_subtotal = $line_price;
					$line_subtotal_tax = $taxrates->taxtotal;

					/* $item->orderitem_finalprice = $line_subtotal;
					$item->orderitem_finalprice_with_tax =  ($line_subtotal + $line_subtotal_tax);
					$item->orderitem_finalprice_without_tax =  $line_subtotal; */
				}

				$tax = 0;
				$tax = $item->orderitem_per_item_tax * $item->orderitem_quantity;
				$tax  = isset($item->orderitem_discount_tax) ?  $tax + $item->orderitem_discount_tax : $tax;
				$item->orderitem_tax = $tax;

				//final price always holds the total without tax.
				$item->orderitem_finalprice = $line_subtotal;
				$item->orderitem_finalprice_with_tax =  ($line_subtotal + $item->orderitem_tax);
				$item->orderitem_finalprice_without_tax =  $line_subtotal;

			}
		}

		//vat exempted customer ? remove the taxes
		$customer = F0FTable::getAnInstance ( 'Customer', 'J2StoreTable' );
		if ($customer->is_vat_exempt ()) {
			$this->removeOrderTaxes();
		}


	}

	public function removeOrderTaxes() {
		$items = $this->getItems();

		foreach($items as $item) {
			$item->orderitem_finalprice_with_tax = $item->orderitem_finalprice_with_tax - $item->orderitem_tax;
			$item->orderitem_per_item_tax = 0;
			$item->orderitem_tax = 0;
		}
		//reset tax rates array
		$this->_taxrates = array();
		$this->order_tax = 0;

	}

	function get_formatted_lineitem_price($item, $including_tax=false) {
		$product_helper = J2Store::product();
		if($including_tax) {
			//including tax
			$price = $product_helper->get_price_including_tax(($item->orderitem_price + $item->orderitem_option_price), $item->orderitem_taxprofile_id);
		} else {
			$price =  $product_helper->get_price_excluding_tax(($item->orderitem_price + $item->orderitem_option_price), $item->orderitem_taxprofile_id);
			//$price = $item->orderitem_price + $item->orderitem_option_price;
		}
		J2Store::plugin()->event('LineItemPrice', array($price, $item));
		return $price;
	}

	function get_formatted_lineitem_total($item, $including_tax=false) {

		if($including_tax) {
			return $item->orderitem_finalprice_with_tax;
		} else {
			return $item->orderitem_finalprice_without_tax;
		}
	}

	function get_formatted_subtotal($including_tax = false) {
		$params = J2Store::config();
		if($including_tax) {
			return $this->order_subtotal + $this->order_tax;
		}else {
			return $this->order_subtotal;
		}
	}

	function get_formatted_grandtotal() {
		J2Store::plugin()->event('GetFormattedGrandTotal', array(&$this));
		return $this->order_total;
	}

	function getOrderTaxTotals() {

		if(isset($this->_taxrates) && count($this->_ordertaxes) < 1 ) {
			foreach($this->_taxrates as $tax) {
				$ordertax = F0FTable::getAnInstance('Ordertax', 'J2StoreTable')->getClone();
				$ordertax->ordertax_title = $tax['name'];
				$ordertax->ordertax_percent = $tax['rate'];
				$ordertax->ordertax_amount = $tax['total'];
				$this->_ordertaxes[] = $ordertax;
			}
		}


		$items = $this->getItems();
		foreach($items as $item) {
			if($item->orderitem_taxprofile_id) {
				$tax = 0;
				$tax = $item->orderitem_per_item_tax * $item->orderitem_quantity;
				$tax  = isset($item->orderitem_discount_tax) ?  $tax + $item->orderitem_discount_tax : $tax;
				$item->orderitem_tax = $tax;
				//we need to re-set this because the discount tax alters the tax totals.
				$item->orderitem_finalprice_with_tax = ($item->orderitem_finalprice +$item->orderitem_tax);
			}
		}

		$taxtotal = 0;
		 if(isset($this->_ordertaxes) && count($this->_ordertaxes)) {
			foreach($this->_ordertaxes as $ordertax) {
				$taxtotal += $ordertax->ordertax_amount;
			}
		}
		$this->order_tax = $taxtotal;

		J2Store::plugin()->event("CalculateTaxTotals", array( $this) );

	}

	public function getOrderDiscountTotals() {

		$discount_total = 0;

		$app = JFactory::getApplication();
		$session = JFactory::getSession();

		//coupons
		if($session->has('coupon', 'j2store')) {
			$discount_total += $this->getCouponTotals();
		}

		//vouchers
		if($session->has('voucher', 'j2store')) {
			$discount_total += $this->getVoucherTotals();
		}

		$total_without_disc=0.00;
		$total_without_disc = $this->order_subtotal + $this->order_tax + $this->order_shipping + $this->order_shipping_tax;

		//set the total as equal to the order_subtotal + order_tax if its greater than the sum of the two
		$this->order_discount = $discount_total > ($total_without_disc) ? $total_without_disc : $discount_total;
		J2Store::plugin()->event("CalculateDiscountTotals", array( $this) );
	}

	public function getOrderCoupons() {

		if(count($this->_ordercoupons) < 1 && !empty($this->order_id)) {
			$this->_ordercoupons = F0FModel::getTmpInstance('Ordercoupons', 'J2StoreModel')->order_id($this->order_id)->getList();
		}
		return $this->_ordercoupons;
	}

	public function getCouponTotals() {
		$app = JFactory::getApplication();
		$cart_helper = J2store::cart();
		$params = J2Store::config();
		$items = $this->getItems();
		$session = JFactory::getSession();

		$coupon_model = F0FModel::getTmpInstance('Coupons', 'J2StoreModel');

		$coupon_info = $coupon_model->getCoupon($session->get('coupon', '', 'j2store'), $items);
		$coupontotals = 0;
		if($coupon_info === false) {
			$session->clear('coupon', 'j2store');
			$app->enqueueMessage(JText::_('J2STORE_INVALID_COUPON'), 'notice');
		}

		if ($coupon_info) {

			$var = 'orderitem_finalprice_without_tax';
			if($params->get('config_discount_before_tax', 1) != 1) {
				//discount applied after tax
				$var = 'orderitem_finalprice_with_tax';
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

			//maximum value restriction. If set then we need to check
			if ($coupon_info->value_type == 'P' && !empty($coupon_info->max_value) && (float) $coupon_info->max_value > 0) {
				//calculate the actual discount
				$actual_discount = $coupon_model->getTotalCouponDiscount($coupon_info, $items);
				//is the actual discount greater than the max value
				if($actual_discount > 0 && $actual_discount > (float) $coupon_info->max_value) {
					$coupon_info->value = (float) $coupon_info->max_value;
					$coupon_info->value_type = 'F';
				}
			}

			$product_array2 = array();
			foreach ($items as &$item) {
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
				$item_discount_taxtotal = 0;
				if ($status) {
					if ($coupon_info->value_type == 'F') {
						$discount = $coupon_info->value * ($item->$var / $sub_total);
					} elseif ($coupon_info->value_type == 'P') {
						$discount = $item->$var / 100 * $coupon_info->value;
					}

					 if ($item->orderitem_taxprofile_id && $params->get('config_discount_before_tax', 1) == 1) {
						$taxModel = F0FModel::getTmpInstance('TaxProfiles', 'J2StoreModel');
						$tax_rates = $taxModel->getTaxwithRates($item->$var - ($item->$var - $discount), $item->orderitem_taxprofile_id, 0);
						foreach ($tax_rates->taxes as $taxrate_id=>$tax_rate) {
							$this->_taxrates[$taxrate_id]['total'] -= $tax_rate['amount'];
							$item_discount_taxtotal -= $tax_rate['amount'];
						}
					}
				}
				$item->orderitem_discount = $discount;
				$item->orderitem_discount_tax = $item_discount_taxtotal;
				$coupontotals += $discount;
			}

			if($coupon_info->free_shipping && $session->has('shipping_values', 'j2store')) {
				$shipping = $session->get('shipping_values', array(), 'j2store');
				$shipping_cost = 0;
				if(isset($shipping['shipping_price']) || isset($shipping['shipping_extra']) || isset($shipping['shipping_tax'])) {
					$shipping_cost = $shipping['shipping_price']+$shipping['shipping_extra']+$shipping['shipping_tax'];
				}
				$coupontotals += $shipping_cost;
			}

			$couponTable = F0FTable::getAnInstance('OrderCoupon', 'J2StoreTable');
			$couponTable->coupon_id = $coupon_info->j2store_coupon_id;
			$couponTable->coupon_code = $coupon_info->coupon_code;
			$couponTable->value = $coupon_info->value;
			$couponTable->value_type = $coupon_info->value_type;
			$couponTable->amount = $coupontotals;

			$this->_ordercoupons[$coupon_info->coupon_code] = $couponTable;
			J2Store::plugin()->event("CalculateCouponTotals", array( $this) );
		}
		return $coupontotals;
	}


	public function getOrderVouchers() {
		if(count($this->_ordervouchers) < 1 && !empty($this->order_id)) {

			$this->_ordervouchers = F0FModel::getTmpInstance('Voucherhistories', 'J2StoreModel')->order_id($this->order_id)->getList();
		}
		return $this->_ordervouchers;
	}


	public function getVoucherTotals() {

		$app = JFactory::getApplication();
		$session = JFactory::getSession();
		$cart_helper = J2store::cart();
		$params = J2Store::config();
		$items = $this->getItems();

		$voucher = FOFModel::getTmpInstance('Vouchers', 'J2StoreModel')->getVoucher($session->get('voucher', '', 'j2store'));

		if($voucher === false) {
			$session->clear('voucher', 'j2store');
			$app->enqueueMessage(JText::_('J2STORE_INVALID_VOUCHER'), 'notice');
		}

	/* 	//check agianst the email
		if(JString::strtolower($voucher->voucher_to_email) != JString::strtolower($this->user_email) && $session->has('voucher', 'j2store')) {
			$voucher = false;
			$app->enqueueMessage(JText::_('J2STORE_VOUCHER_WILL_APPLY_AFTER_LOGIN_OR_PROVIDING_BILLING_ADDRESS'), 'notice');
		} */

		//voucher is validated. Apply it
		$voucher_total = 0;
		if ($voucher) {
			$voucher_total = $voucher->amount >  ($this->order_subtotal + $this->order_tax) ? $this->order_subtotal + $this->order_tax : $voucher->amount;

			$voucherHistoryTable = F0FTable::getAnInstance('Voucherhistories', 'J2StoreTable');
			$voucherHistoryTable->voucher_id = $voucher->voucher_id;
			$voucherHistoryTable->voucher_code = $voucher->voucher_code;
			$voucherHistoryTable->voucher_to_email = $voucher->voucher_to_email;
			$voucherHistoryTable->amount = -$voucher_total;

			$this->_ordervouchers[$voucher->voucher_code] = $voucherHistoryTable;
			J2Store::plugin()->event("CalculateVoucherTotals", array( $this) );
		}
		return $voucher_total;
	}

	public function getOrderShippingTotals() {

		$app = JFactory::getApplication();
		$order_shipping     = 0.00;
		$order_shipping_tax = 0.00;

		$session = JFactory::getSession();

		$items = $this->getItems();

		if (!is_array($items))
		{
			$this->order_shipping       = $order_shipping;
			$this->order_shipping_tax   = $order_shipping_tax;
			return;
		}

		$showShipping = false;
		if ($isShippingEnabled = $this->isShippingEnabled())
		{
			$this->is_shippable = 1;
			$showShipping = true;
		}
		//assign a single selected method if it had been selected
		$force = $session->get('force_calculate_shipping', 0, 'j2store');
		$session->clear('force_calculate_shipping', 'j2store');
		$shipping_values = $session->get('shipping_values', array(), 'j2store');
		$view = $app->input->getString('view', '');
		//run the shipping only in the cart views. Do not run automatically in other views.
		if(($showShipping && count($shipping_values)
				&& ($view=='cart' || $view=='carts' || $view=='checkout' || $view=='checkouts' ) ) || $force) {

			$shipping_totals = array();

			//get exisitng values
			$shipping_values = $session->get('shipping_values', array(), 'j2store');
			$rates = F0FModel::getTmpInstance('Shippings', 'J2StoreModel')->getShippingRates($this);
			$session->set('shipping_methods', $rates, 'j2store');
			$is_same = false;
			foreach($rates as $rate) {

				if(isset($shipping_values['shipping_name']) && $shipping_values['shipping_name'] == $rate['name']) {
					$shipping_values['shipping_price']    = isset($rate['price']) ? $rate['price'] : 0;
					$shipping_values['shipping_extra']   = isset($rate['extra']) ? $rate['extra'] : 0;
					$shipping_values['shipping_code']     = isset($rate['code']) ? $rate['code'] : '';
					$shipping_values['shipping_name']     = isset($rate['name']) ? $rate['name'] : '';
					$shipping_values['shipping_tax']      = isset($rate['tax']) ? $rate['tax'] : 0;
					$shipping_values['shipping_plugin']     = isset($rate['element']) ? $rate['element'] : '';
					$session->set('shipping_method', $shipping_values['shipping_plugin'], 'j2store');
					$session->set('shipping_values', $shipping_values, 'j2store');
					$is_same = true;
				}
			}
			if($is_same === false ) {
				//sometimes the previously selected method may not apply. In those cases, we will have remove the selected shipping.
				$session->set('shipping_values', array(), 'j2store');
			}
		}

		if($session->has('shipping_values', 'j2store') && $showShipping) {
			$shipping = $session->get('shipping_values', array(), 'j2store');
			if(count($shipping) && isset($shipping['shipping_name']) ) {
				$this->setOrderShippingRate($shipping);
				$order_shipping      = $this->_shipping_totals->ordershipping_price + $this->_shipping_totals->ordershipping_extra;
				$order_shipping_tax  = $this->_shipping_totals->ordershipping_tax;
			}
		}
		$this->order_shipping       = $order_shipping;
		$this->order_shipping_tax   = $order_shipping_tax;

		J2Store::plugin()->event("CalculateShippingTotals", array( $this) );
	}

	function getOrderInformation() {
		if (!isset($this->_orderinfo) && !empty($this->order_id))
		{
			$this->_orderinfo = F0FTable::getInstance('Orderinfo', 'J2StoreTable');
			$this->_orderinfo->load(array('order_id'=>$this->order_id));
		}
		return $this->_orderinfo;
	}

	function setOrderInformation() {

		$user = JFactory::getUser();
		$session = JFactory::getSession();
		$address_model = F0FModel::getTmpInstance('Addresses', 'J2StoreModel');

		//set shiping address
		if($user->id && $session->has('shipping_address_id', 'j2store')) {
			$shipping_address = $address_model->getAddressById($session->get('shipping_address_id', '', 'j2store'));
		} elseif($session->has('guest', 'j2store')) {
			$guest = $session->get('guest', array(), 'j2store');
				$shipping_address = isset($guest['shipping']) ? $guest['shipping'] : array();
		}else{
			$shipping_address = array();
		}


		$billing_address = array();
		if ($user->id && $session->has('billing_address_id', 'j2store')) {
			$billing_address = $address_model->getAddressById($session->get('billing_address_id', '', 'j2store'));
		} elseif ($session->has('guest', 'j2store')) {
			$guest = $session->get('guest', array(), 'j2store');
			$billing_address = isset($guest['billing']) ? $guest['billing'] : array();
		}

		$orderinfo = array();
		if($billing_address) {
			foreach ($billing_address as $key=>$value) {
				$orderinfo['billing_'.$key] = $value;
			}

			//custom fields
			$orderinfo['all_billing']= $this->processCustomFields('billing', $billing_address);
		}

		if($shipping_address) {
			foreach ($shipping_address as $key=>$value) {
				$orderinfo['shipping_'.$key] = $value;
			}

			$orderinfo['all_shipping']= $this->processCustomFields('shipping', $shipping_address);
		}

		if($session->has('payment_values', 'j2store')) {
			$pay_values = $session->get('payment_values', array(), 'j2store');
			$orderinfo['all_payment']= $this->processCustomFields('payment', $pay_values);
		}


		if($user->id) {
			$user_email = $user->email;
		} else {
			$user_email =  isset($billing_address['email']) ? $billing_address['email'] : '';
		}

		$this->user_email = $user_email;

		$orderinfoTable = F0FTable::getAnInstance('OrderInfo', 'J2StoreTable');
		$orderinfoTable->bind($orderinfo);
		$this->_orderinfo = $orderinfoTable;

		J2Store::plugin()->event("PrepareOrderInformation", array( $this) );

	}


	/**
	 * Gets the shipping rate object
	 */
	public function getOrderShippingRate()
	{
		if (!isset($this->_shipping_totals) && !empty($this->order_id))
		{
			$this->_shipping_totals = F0FTable::getAnInstance('Ordershipping', 'J2StoreTable');
			$this->_shipping_totals->load(array('order_id'=>$this->order_id));
		}
		return $this->_shipping_totals;
	}

	/**
	 * Sets the shipping object for the order from a shipping_rate array,
	 * a standard array created by all shipping plugins as a valid shipping rate option during checkout
	 *
	 * @param array $rate
	 */
	public function setOrderShippingRate( $values)
	{

		$ordershipping_table = F0FTable::getAnInstance('Ordershipping', 'J2StoreTable');

		$ordershipping_table->ordershipping_price      = $values['shipping_price'];
		$ordershipping_table->ordershipping_extra      = $values['shipping_extra'];
		$ordershipping_table->ordershipping_tax        = $values['shipping_tax'];
		$ordershipping_table->ordershipping_code       = $values['shipping_code'];
		$ordershipping_table->ordershipping_name       = $values['shipping_name'];
		$ordershipping_table->ordershipping_type	   = $values['shipping_plugin'];
		$ordershipping_table->ordershipping_total	   = $values['shipping_price']+$values['shipping_extra']+$values['shipping_tax'];
		$this->_shipping_totals = $ordershipping_table;

	}

	function isShippingEnabled() {
		$items = $this->getItems();

		$status = false;
		foreach ($items as $item) {
			$registry = new JRegistry;
			$registry->loadString($item->orderitem_params);
			if($registry->get('shipping')) {
				$status = true;
				continue;
			}
		}
		return $status;
	}

	function needShipping($item) {
		$registry = new JRegistry;
		$registry->loadString($item->orderitem_params);
		return $registry->get('shipping', 0);
	}

	function getTotalShippingWeight() {
		$items = $this->getItems();
		$total = 0;
		if(count($items) < 1) return;

		foreach($items as $item) {
			if($this->needShipping($item)) {
				$total += $item->orderitem_weight_total;
			}
		}
		return $total;
	}

	function setAddress($override='no') {
		$session = JFactory::getSession();
		$storeaddress = J2Store::storeProfile();
		if ($session->has('shipping_country_id', 'j2store') || $session->has('shipping_zone_id', 'j2store') || $session->get('shipping_postcode', '', 'j2store')) {
			$this->setShippingAddress($session->get('shipping_country_id', '', 'j2store'), $session->get('shipping_zone_id', '', 'j2store'), $session->get('shipping_postcode', '', 'j2store'));
		} else {
			//	$this->setShippingAddress($storeaddress->country_id, $storeaddress->zone_id, $storeaddress->store_zip);
		}

		if ($session->has('billing_country_id', 'j2store') || $session->has('billing_zone_id', 'j2store') || $session->get('billing_postcode', '', 'j2store')) {
			$this->setBillingAddress($session->get('billing_country_id', '', 'j2store'), $session->get('billing_zone_id', '', 'j2store'), $session->get('billing_postcode', '', 'j2store'));
		} else {
			$this->setBillingAddress($storeaddress->get('country_id'), $storeaddress->get('zone_id'), $storeaddress->get('store_zip'));
		}
		$this->setStoreAddress($storeaddress->get('country_id'), $storeaddress->get('zone_id'), $storeaddress->get('store_zip'));
		//address override
		if($override == 'store') {
			$this->setShippingAddress($storeaddress->get('country_id'), $storeaddress->get('zone_id'), $storeaddress->get('store_zip'));
		}

		$this->setGeozones();

	}

	/**
	 * Based on the object's addresses,
	 * sets the shipping and billing geozones
	 *
	 * @return unknown_type
	 */
	function setGeozones( $geozones=null, $type='billing' )
	{
		if (!empty($geozones))
		{
			switch ($type)
			{
				case "shipping":
				default:
					$this->_shipping_geozones = $geozones;
					break;
				case "billing":
					$this->_billing_geozones = $geozones;
					break;
			}
		}
		else
		{
			require_once(JPATH_ADMINISTRATOR.'/components/com_j2store/library/shipping.php');
			if (!empty($this->_billing_address))
			{
				$this->_billing_geozones = $this->getGeoZones( $this->_billing_address['country_id'], $this->_billing_address['zone_id'], '1');
			}
			if (!empty($this->_shipping_address))
			{
				$this->_shipping_geozones = $this->getGeoZones( $this->_shipping_address['country_id'], $this->_shipping_address['zone_id'], '2', $this->_shipping_address['postal_code'] );
			}
		}
	}


	public function setShippingAddress($country_id, $zone_id, $postal_code) {
		$this->_shipping_address = array(
				'country_id' => $country_id,
				'zone_id'    => $zone_id,
				'postal_code'    => $postal_code
		);
	}

	public function setBillingAddress($country_id, $zone_id, $postal_code) {
		$this->_billing_address = array(
				'country_id' => $country_id,
				'zone_id'    => $zone_id,
				'postal_code'    => $postal_code
		);
	}

	public function setStoreAddress($country_id, $zone_id, $postal_code) {
		$this->_store_address = array(
				'country_id' => $country_id,
				'zone_id'    => $zone_id,
				'postal_code'    => $postal_code
		);
	}

	/**
	 * Gets the order billing address
	 * @return unknown_type
	 */
	function getBillingAddress()
	{
		// TODO If $this->_billing_address is null, attempt to populate it with the orderinfo fields, or using the billing_address_id (if present)
		return $this->_billing_address;
	}

	/**
	 * Gets the order shipping address
	 * @return unknown_type
	 */
	function getShippingAddress()
	{
		// TODO If $this->_shipping_address is null, attempt to populate it with the orderinfo fields, or using the shipping_address_id (if present)
		return $this->_shipping_address;
	}

	public function getGeoZones( $country_id, $zone_id, $geozonetype='2', $zip_code = null, $update = false )
	{
		$return = array();
		if (empty($zone_id) && empty($country_id))
		{
			return $return;
		}

		static $geozones = null; // static array for caching results
		if( $geozones === null )
			$geozones = array();

		if( $zip_code === null )
			$zip_code = 0;

		if( isset( $geozones[$geozonetype][$zone_id][$zip_code] ) && !$update )
			return $geozones[$geozonetype][$zone_id][$zip_code];


		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select('gz.*,gzr.*')->from('#__j2store_geozones AS gz')
		->leftJoin('#__j2store_geozonerules AS gzr ON gzr.geozone_id = gz.j2store_geozone_id')
		->where('gzr.country_id='.$db->q($country_id).' AND (gzr.zone_id=0 OR gzr.zone_id='.$db->q($zone_id).')');

		if($zip_code)
		{
			//TODO add filter by postcode
		}
		$db->setQuery($query);
		$items = $db->loadObjectList();

		if (!empty($items))
		{
			$return = $items;
		}
		$geozones[$geozonetype][$zone_id][$zip_code] = $return;
		return $return;
	}

	function getGeozone($country_id, $zone_id, $zip_code = null, $geozone_id=null) {
		$return = array ();

		if (empty ( $zone_id ) && empty ( $country_id )) {
			return $return;
		}

		static $geozone = null; // static array for caching results
		if ($geozone === null)
			$geozone = array ();

		if ($zip_code === null)
			$zip_code = 0;

		if($geozone_id == null || empty($geozone_id))
			$geozone_id = 0;

		if (! isset ( $geozone [$country_id] [$zone_id] [$zip_code] [$geozone_id] )) {
			$items = array();
			$db = JFactory::getDbo ();
			$query = $db->getQuery ( true );
			$query->select ( 'gz.*,gzr.*' )->from ( '#__j2store_geozones AS gz' )
			->leftJoin ( '#__j2store_geozonerules AS gzr ON gzr.geozone_id = gz.j2store_geozone_id' )
			->where ( 'gz.j2store_geozone_id=' . $geozone_id )
			->where ( 'gzr.country_id=' . $db->q ( $country_id ) . ' AND (gzr.zone_id=0 OR gzr.zone_id=' . $db->q ( $zone_id ) . ')' );
			$db->setQuery ( $query );
			try {
				$items = $db->loadObjectList ();
			}catch(Exception $e) {
				//do nothing.
			}

			if (! empty ( $items )) {
				$return = $items;
			}

			$geozone [$country_id] [$zone_id] [$zip_code] [$geozone_id] = $return;
		}

		return $geozone [$country_id] [$zone_id] [$zip_code] [$geozone_id];
	}

	/**
	 * Gets the order's shipping geozones
	 *
	 * @return unknown_type
	 */
	function getShippingGeoZones()
	{
		return $this->_shipping_geozones;
	}

	public function processCustomFields($type, $data) {
		$selectableBase = J2Store::getSelectableBase();
		$address = F0FTable::getAnInstance('Address', 'J2StoreTable');
		$orderinfo = F0FTable::getAnInstance('Orderinfo', 'J2StoreTable');

		$fields = $selectableBase->getFields($type,$address,'address');

		if(is_array($data)) {
			$data = JArrayHelper::toObject($data);
		}

		$values = array();
		foreach ($fields as $fieldName => $oneExtraField) {
			if(isset($data->$fieldName)) {
				if(!property_exists($orderinfo, $type.'_'.$fieldName) && !property_exists($orderinfo, 'user_'.$fieldName ) && $fieldName !='country_id' && $fieldName != 'zone_id' && $fieldName != 'option' && $fieldName !='task' && $fieldName != 'view' ) {
					$values[$fieldName]['label'] =$oneExtraField->field_name;
					$values[$fieldName]['value'] = $data->$fieldName;
				}
			}
		}
		$registry = new JRegistry();
		$registry->loadArray($values);
		$json = $registry->toString('JSON');
		return $json;

	}

	function saveOrder() {

		$app = JFactory::getApplication();
		$user = JFactory::getUser();
		$lang = JFactory::getLanguage();
		$session = JFactory::getSession();
		$params = J2Store::config();

		//cart id
		$this->cart_id = F0FModel::getTmpInstance('Carts', 'J2StoreModel')->getCartId();

	//	if(!isset($this->order_id) || empty($this->order_id) || $this->is_update != 1) {
		//	$this->order_id = time().$this->cart_id;
	//	}
		//set order values
		$this->user_id = $user->id;


		$this->ip_address = $_SERVER['REMOTE_ADDR'];

		
		$this->customer_note = $session->get('customer_note', '', 'j2store');
		$this->customer_language = $lang->getTag();
		//$this->customer_group = implode(',', JAccess::getGroupsByUser($user->id, false));
		$this->customer_group = implode(',', JAccess::getAuthorisedViewLevels($user->id, false));


		//set a default order status.
		$default_order_state = 5;

		$this->order_state_id = $default_order_state;

		//get currency id, value and code and store it
		$currency = J2Store::currency();
		$this->currency_id = $currency->getId();
		$this->currency_code = $currency->getCode();
		$this->currency_value = $currency->getValue($currency->getCode());

		$this->is_including_tax = $params->get('config_including_tax', 0);

		//sanity check for user email
		if(empty($this->user_email)) {

			if($user->id) {
				$user_email = $user->email;
			} else {
				$guest = $session->get('guest', array(), 'j2store');
				$billing_address = isset($guest['billing']) ? $guest['billing'] : array();
				$user_email =  isset($billing_address['email']) ? $billing_address['email'] : '';
			}
			$this->user_email = $user_email;
		}
		
		//trigger on before save
		J2Store::plugin()->event('BeforeSaveOrder', array(&$this));

		if($this->is_update == 1) {
			//trigger on before update
			J2Store::plugin()->event('BeforeUpdateOrder', array(&$this));
		}else{
			//trigger on before create a new order
			J2Store::plugin()->event('BeforeCreateNewOrder', array(&$this));
		}

		try {
				if($this->store()) {

					if(!isset($this->order_id) || empty($this->order_id) || !isset($this->is_update) || $this->is_update != 1) {
						$this->order_id = time().$this->j2store_order_id;
	
						//generate invoice number					
						$this->generateInvoiceNumber();
						
						//generate a unique hash
						$this->token = JApplicationHelper::getHash($this->order_id);
						
						//save again so that the unique order id is saved.
						$this->store();
					}
									
					//saved.
					//save all related tables as well
					$this->saveOrderItems();
	
					$this->saveOrderInfo();
	
					$this->saveOrderShipping();
	
					$this->saveOrderTax();
	
					$this->saveOrderCoupons();
	
					$this->saveOrderVouchers();
	
					$this->saveOrderDiscount();
	
					$this->saveOrderFiles();
	
					//trigger on before save
					J2Store::plugin()->event('AfterSaveOrder', array(&$this));
	
					if($this->is_update == 1) {
						$this->add_history(JText::_('J2STORE_ORDER_UPDATED_BY_CUSTOMER'));
						//trigger on before update
						J2Store::plugin()->event('AfterUpdateOrder', array(&$this));
					}else{
						$this->add_history(JText::_('J2STORE_NEW_ORDER_CREATED'));
						//trigger on before update
						J2Store::plugin()->event('AfterCreateNewOrder', array(&$this));
					}
				}

		} catch (Exception $e) {
			throw new Exception ($e->getMessage());
			return false;
		}

		return $this;
	}

	/**
	 * Update an existing order. This normally happens during the checkout.
	 * Customer will reach final step. The order will be saved. if he then changes something before proceeding to payment
	 * Then only exisitng order will get updated.
	 *
	 */


	function updateOrder() {

		$this->is_update = 1;
		//its an existing order. So remove already saved information.
		$this->deleteChildren($this->j2store_order_id, $this);
		// we need to reset certain totals
		$this->resetTotals();
	}

	function resetTotals() {
		$this->order_surcharge = 0;
	}

	function saveOrderItems() {

		$items = $this->getItems();
		foreach($items as $item) {
			unset($orderitem);
			$orderitem = F0FTable::getAnInstance('Orderitem', 'J2StoreTable')->getClone();
			$orderitem->bind($item);
			$orderitem->order_id = $this->order_id;
			$orderitem->store();

			//save order attributes
			if(isset($item->orderitemattributes)) {
			$this->saveOrderItemAttributes($item->orderitemattributes, $orderitem);
			}
		}

	}

	function saveOrderItemAttributes($attributes, $orderitem) {
		foreach ($attributes as $attribute) {
			unset($orderitemattribute);
			$orderitemattribute = F0FTable::getAnInstance('OrderItemAttribute', 'J2StoreTable')->getClone();
			$orderitemattribute->bind($attribute);
			$orderitemattribute->orderitem_id = $orderitem->j2store_orderitem_id;
			$orderitemattribute->store();
		}

	}

	function saveOrderInfo() {
		$orderinfo = $this->getOrderInformation();
		$orderinfo->order_id = $this->order_id;
		$orderinfo->store();
	}

	function saveOrderShipping() {
		if(isset($this->_shipping_totals) && is_object($this->_shipping_totals)) {
			$this->_shipping_totals->order_id = $this->order_id;
			$this->_shipping_totals->store($this->_shipping_totals);
		}
	}

	function saveOrderTax() {
		if(isset($this->_ordertaxes) && count($this->_ordertaxes)) {
			foreach($this->_ordertaxes as $ordertax) {
				$ordertax->order_id = $this->order_id;
				$ordertax->store();
			}
		}
	}

	function saveOrderCoupons() {
		if(isset($this->_ordercoupons) && count($this->_ordercoupons)) {
			foreach ($this->_ordercoupons as $coupon) {
				$coupon->order_id = $this->order_id;
				$coupon->customer_email = $this->user_email;
				$coupon->customer_id = $this->user_id;
				$coupon->store();
			}
		}
	}

	function saveOrderVouchers() {
		if(isset($this->_ordervouchers) && count($this->_ordervouchers)) {
			foreach ($this->_ordervouchers as $voucher) {
				$voucher->order_id = $this->order_id;
				$voucher->store();
			}
		}

	}

	function saveOrderDiscount() {

	}

	function saveOrderFiles() {

		$db = JFactory::getDbo();
		$items = $this->getItems();
		foreach($items as $item) {
			//get the list of files based on
			if($item->product_type == 'downloadable') {
				unset($orderdownloads);
				$orderdownloads = F0FTable::getAnInstance('Orderdownload', 'J2StoreTable')->getClone();
				$orderdownloads->order_id = $this->order_id;
				$orderdownloads->product_id = $item->product_id;
				$orderdownloads->user_id = $this->user_id;
				$orderdownloads->user_email = $this->user_email;
				$orderdownloads->access_granted == $db->getNullDate();
				$orderdownloads->access_expires == $db->getNullDate();
				$orderdownloads->store();
			}
		}
	}

	public function getOrderDownloads() {
		if(count($this->_orderdownloads) < 1) {
			$model = F0FModel::getTmpInstance('Orderdownloads', 'J2StoreModel');
			$model->setState('order_id', $this->order_id);
			$model->setState('email', $this->user_email);
			$this->_orderdownloads = $model->getList();
		}
		return $this->_orderdownloads;
	}

	public function getOrderHistory(){
		if(count($this->_orderhistory) < 1) {
			$model = F0FModel::getTmpInstance('Orderhistories', 'J2StoreModel');
			$model->setState('order_id', $this->order_id);
			$this->_orderhistory = $model->getList();
		}
		return $this->_orderhistory;
	}

	public function add_history($note='') {
		F0FModel::getTmpInstance('Orderhistories', 'J2StoreModel')->setOrderHistory($this, $note);
	}

	/**
	 * The event which runs before deleting a record
	 *
	 * @param   integer  $oid  The PK value of the record to delete
	 *
	 * @return  boolean  True to allow the deletion
	 */
	protected function onBeforeDelete($oid)
	{

		$status = true;
		// Load the post record
		$item = clone $this;
		$item->load($oid);
		if($oid){
			//make sure that any product using this options before delete the
			$this->deleteChildren($oid,$item);
		}
		return $status;
	}

	private function deleteChildren($oid,$order){

		if(empty($order->order_id)) return;
		$db = JFactory::getDbo();

		$orderItems = F0FModel::getTmpInstance('Orderitems', 'J2StoreModel')->getItemsByOrder($order->order_id);

		//loop all the orderitem
		foreach($orderItems as $item){
			// check orderitem row exists
			//will delete orderitem and children table orderitemattribute
			F0FTable::getAnInstance('Orderitem', 'J2StoreTable')->delete($item->j2store_orderitem_id);
		}
		//delete orderinfo table
		$orderinfo = F0FTable::getAnInstance('Orderinfo','J2StoreTable');
		if($orderinfo->load(array('order_id'=>$order->order_id))){
			$orderinfo->delete();
		}

		//order downloads
		$query = $db->getQuery(true)->delete('#__j2store_orderdownloads')->where('order_id = '.$db->q($order->order_id));
		$db->setQuery($query)->execute();

		//order history
		if(!isset($order->is_update) || $order->is_update != 1) {
			$query = $db->getQuery(true)->delete('#__j2store_orderhistories')->where('order_id = '.$db->q($order->order_id));
			$db->setQuery($query)->execute();
		}

		//shipping
		$ordershipping = F0FTable::getAnInstance('Ordershipping', 'J2StoreTable');
		if($ordershipping->load(array('order_id'=>$order->order_id))){
			$ordershipping->delete();
		}

		//coupon
		$ordercoupon = F0FTable::getAnInstance('Ordercoupon', 'J2StoreTable');
		if($ordercoupon->load(array('order_id'=>$order->order_id))){
			$ordercoupon->delete();
		}

		//voucher histories
		$query = $db->getQuery(true)->delete('#__j2store_voucherhistories')->where('order_id = '.$db->q($order->order_id));
		$db->setQuery($query)->execute();

		//order taxes
		$query = $db->getQuery(true)->delete('#__j2store_ordertaxes')->where('order_id = '.$db->q($order->order_id));
		$db->setQuery($query)->execute();
		J2Store::plugin()->event('AfterDeleteOrderChildren', array($order));	
		return true;
	}

	public function payment_complete( ) {

		$app = JFactory::getApplication();

		//event before marking an order complete
		J2Store::plugin()->event('BeforePaymentComplete', array($this));

		//valid order statuses.
		//3 = failed, 4 = pending, 5=new or incomplete
		$valid_order_statuses = array(3,4,5,6);
		$old_status = $this->order_state_id;

		if ( !empty($this->order_id) && $this->has_status( $valid_order_statuses ) ) {

			$order_needs_processing = true;

			//set status to confirmed
			$this->update_status( 1 );
			if($old_status != 4) {  //Pending orders have their stock already reduced. So no need to reduce again
				$this->reduce_order_stock(); // Payment is complete so reduce stock levels
			}

			//notify customer
			$this->notify_customer ();

			//grant permissions to file download
			$this->grant_download_permission();

			J2Store::plugin()->event('AfterPaymentComplete', array($this));

		}
	}

	/**
	 * Checks the order status against a passed in status.
	 *
	 * @return bool
	 */
	public function has_status( $status ) {
		$result = ((is_array( $status ) && in_array( $this->get_status(), $status ) ) || $this->get_status() === $status ) ? true : false;
		J2Store::plugin()->event('OrderHasStatus', array($result, $this, &$status));
		return $result;
	}

	public function get_status() {
		return !empty($this->order_state_id) ? $this->order_state_id : $this->order_state_id;
	}

	public function update_status($new_status, $force_notify_customer=false) {
		if (empty ( $this->order_id ))
			return;
		$app = JFactory::getApplication();
		$old_status = $this->get_status ();
		// update only when the status is new
		if ($new_status !== $old_status) {

			//trigger event before update
			J2Store::plugin()->event('BeforeOrderstatusUpdate', array($this, $new_status));

			// first update the order
			$this->order_state_id = $new_status;
			$this->store ();

			$this->add_history ( JText::sprintf ( 'J2STORE_ORDER_STATUS_CHANGED', $old_status, $new_status ) );

			//trigger event after update
			J2Store::plugin()->event('AfterOrderstatusUpdate', array($this, $new_status));

			//process more triggers

			switch ($new_status) {

				case '1' :
					// Record the sales
					$this->record_product_sales ();

					// Increase coupon usage counts
					//$this->increase_coupon_usage_counts ();
					break;
				case '6' :
					// Increase coupon usage counts
					$this->reduce_coupon_usage_counts();
					break;
			}
		}

		if($force_notify_customer) {
			$this->notify_customer();
		}
	}

	public function record_product_sales() {
		if (sizeof ( $this->getItems () ) > 0) {

			foreach ( $this->getItems () as $item ) {

				if ($item->variant_id > 0) {
					$table = F0FTable::getAnInstance ( 'Variant', 'J2StoreTable' )->getClone ();
					if ($table->load ( $item->variant_id )) {

						$sales = ( int ) $table->sold;
						$sales += ( int ) $item->orderitem_quantity;

						if ($sales) {
							$table->sold = $sales;
							try {
							$table->store ();
							} catch (Exception $e) {
								//do nothing.
							}
							unset ( $table );
						}
					}
				}
			}
		}
	}

	public function increase_coupon_usage_counts() {

	}

	public function reduce_coupon_usage_counts() {
		//remove coupon from the cancelled order
		if(empty($this->order_id)) return;
		$table = F0FTable::getInstance('Ordercoupon', 'J2StoreTable')->getClone();
		if($table->load(array('order_id'=>$this->order_id))) {
			$table->delete();
		}
	}

	public function notify_customer() {
		if (empty ( $this->order_id ))
			return;

		$app = JFactory::getApplication ();
		$config = JFactory::getConfig ();
		$params = J2Store::config ();

		$sitename = $config->get ( 'sitename' );

		$emailHelper = J2Store::email ();

		$orderinfo = $this->getOrderInformation ();

		$mailer = $emailHelper->getEmail ( $this );

		J2Store::plugin ()->event ( 'BeforeOrderNotification', array (
				$this,
				&$mailer
		) );

		$mailfrom = $config->get ( 'mailfrom' );
		$fromname = $config->get ( 'fromname' );
		$mailer->setSender ( array (
				$mailfrom,
				$fromname
		) );

		// clone the mailer object so that we can send the same email to the administrators.
		$admin_mailer = clone $mailer;

		if (isset ( $this->user_email ) && ! empty ( $this->user_email ) && $mailer != false) {
			$mailer->addRecipient ( $this->user_email );
			try {
				if ($mailer->send ()) {
					$this->add_history ( JText::_ ( 'J2STORE_CUSTOMER_NOTIFIED' ) );
					J2Store::plugin ()->event ( 'AfterOrderNotification', array (
							$this
					) );
				}
			} catch ( Exception $e ) {
				$this->add_history ( $e->getMessage () );
			}

			$mailer = null;
		}
		// send emails to store administrators. Some servers does not like to send BCC. So its better we send the notifications seperately.
		$admin_emails = $params->get ( 'admin_email' );
		$admin_emails = explode ( ',', $admin_emails );
		if (count ( $admin_emails )) {
			$admin_mailer->addRecipient ( $admin_emails );
			try {
				if ($admin_mailer->send ()) {
					$this->add_history ( JText::_ ( 'J2STORE_ADMINISTRATORS_NOTIFIED' ) );
				}
			} catch ( Exception $e ) {
				$this->add_history ( $e->getMessage () );
			}

			$admin_mailer = null;
		}
	}

	public function reduce_order_stock() {

		$app = JFactory::getApplication();

		foreach($this->getItems() as $item) {

			if($item->product_id > 0 && $item->variant_id > 0) {
				//get the variant
				$variant_model = F0FModel::getTmpInstance('Variants', 'J2StoreModel')->getClone();
				$variant = $variant_model->getItem($item->variant_id);
				if($variant && J2Store::product()->managing_stock($variant)) {
					J2Store::plugin()->event('BeforeStockReduction', array($this, &$item));
					$new_stock = $variant->reduce_stock($item->orderitem_quantity);
					$this->add_history(JText::sprintf('J2STORE_ORDERITEM_STOCK_REDUCED', $item->orderitem_name, $new_stock+$item->orderitem_quantity, $new_stock));
					$this->send_stock_notifications( $variant, $new_stock, $item->orderitem_quantity);
				}
			}

		}
	}

	/**
	 * Method to restore order stock (Called when orders are cancelled)
	 */

	public function restore_order_stock() {
		$app = JFactory::getApplication();

		foreach ( $this->getItems () as $item ) {

			if($item->product_id > 0 && $item->variant_id > 0) {
				$variant_model = F0FModel::getTmpInstance('Variants', 'J2StoreModel')->getClone();
				$variant = $variant_model->getItem($item->variant_id);
				if($variant && J2Store::product()->managing_stock($variant)) {
					$old_stock = $variant->quantity;
					J2Store::plugin()->event('BeforeStockRestore', array($this, &$item));
					$new_quantity = $variant->increase_stock ( $item->orderitem_quantity );

					$this->add_history(JText::sprintf('J2STORE_ORDERITEM_STOCK_INCREASED', $item->orderitem_name, $old_stock, $new_quantity));
					$this->send_stock_notifications( $variant, $new_quantity, $item->orderitem_quantity);
				}
			}
		}
	}

	public function send_stock_notifications( $variant, $new_stock, $qty_ordered ) {

		$app = JFactory::getApplication();
		// Backorders
		if ( $new_stock < 0 ) {
			J2Store::plugin()->event('ProductOnBackorder', array($variant, $this->order_id, $qty_ordered));
		}

		// stock status notifications
		$notification_sent = false;

		if ( $new_stock <= 0) {
			J2Store::plugin()->event('NotifyNoStock', array($variant));
			$notification_sent = true;
		}

		J2Store::product()->getQuantityRestriction($variant);
		if ( ! $notification_sent && $variant->notify_qty >= $new_stock ) {
			J2Store::plugin()->event('NotifyLowStock', array($variant, $new_stock));
			$notification_sent = true;
		}
	}

	public function empty_cart() {
		if(!isset($this->order_id) || empty($this->order_id)) return;

		$cart = F0FTable::getAnInstance('Carts', 'J2StoreTable');
		if($cart->load($this->cart_id)) {
			$cartobject = $cart;
			J2Store::plugin()->event('BeforeEmptyCart', array($cartobject));
				$cart->delete();
			J2Store::plugin()->event('AfterEmptyCart', array($cartobject));
		}
	}

	public function generateInvoiceNumber() {
		if(empty($this->order_id)) return;

		$db = JFactory::getDbo();
		$status = true;
		$store = J2Store::storeProfile();
		$store_invoice_prefix = $store->get('invoice_prefix');
		if(!isset($store_invoice_prefix) || empty($store_invoice_prefix)) {
			//backward compatibility. If no prefix is set, retain the invoice number is the table primary key.
			$status = false;
		}

		if($status) {
			//get the last row
			$query = $db->getQuery(true)->select('MAX(invoice_number) AS invoice_number')
			->from('#__j2store_orders')->where('invoice_prefix='.$db->q($store->get('invoice_prefix')));
			$db->setQuery($query);
			$row = $db->loadObject();
			if(isset($row->invoice_number) && $row->invoice_number) {
				$invoice_number = $row->invoice_number+1;
			}else {
				$invoice_number =1;
			}
			$this->invoice_number = $invoice_number;
			$this->invoice_prefix = $store->get('invoice_prefix');
		}
	}

	public function getInvoiceNumber() {
		if(empty($this->order_id)) return;
			if(isset($this->invoice_number) && $this->invoice_number > 0) {
				$invoice_number = $this->invoice_prefix.$this->invoice_number;
			}else {
				$invoice_number = $this->j2store_order_id;
			}
		return $invoice_number;
	}

	public function has_downloadable_item() {

		if (empty ( $this->order_id ))
			return false;

		$has_item = false;
		$items = $this->getItems ();
		foreach ( $items as $item ) {
			// check if product exists
			$product = F0FTable::getInstance ( 'Product', 'J2StoreTable' )->getClone ();
			$product->load ( $item->product_id );

			if ($product->is_valid_product () && $product->is_downloadable() && $product->has_file ()) {
				$has_item = true;
			}
		}

		return $has_item;
	}

	public function grant_download_permission() {
		if(empty($this->order_id)) return;
		F0FModel::getTmpInstance('Orderdownloads', 'J2StoreModel')->setDownloads($this, $override_status=true);
		J2Store::plugin()->event('GrantDownloadPermission', array($this));
		return true;
	}

	public function get_customer_language() {
		if(empty($this->order_id)) return;

		$lang_data = JFactory::getLanguage()->getMetadata($this->customer_language);
		if(isset($lang_data['name'])) {
			$customer_language = $lang_data['name'];
		}else {
			$customer_language = $this->customer_language;
		}
		return $customer_language;
	}

}
