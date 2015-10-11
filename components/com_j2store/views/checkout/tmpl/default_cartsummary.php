<?php
/**
 * @package J2Store
 * @copyright Copyright (c)2014-17 Ramesh Elamathi / J2Store.org
 * @license GNU GPL v3 or later
 */
// No direct access to this file
defined ( '_JEXEC' ) or die ();
$order = $this->order;
$items = $this->order->getItems();
$this->taxes = $order->getOrderTaxrates();
$this->shipping = $order->getOrderShippingRate();
$this->coupons = $order->getOrderCoupons();
//plugin trigger
$this->other_discounts = J2Store::plugin()->eventWithArray('BeforeDisplayCheckoutSummary',array($order));
$this->vouchers = $order->getOrderVouchers();
$currency = J2Store::currency();

?>
	<h3><?php echo JText::_('J2STORE_ORDER_SUMMARY')?></h3>
	<table class="j2store-cart-table table table-bordered table-striped">
		<thead>
			<tr>
				<th><?php echo JText::_('J2STORE_CART_LINE_ITEM'); ?></th>
				<th><?php echo JText::_('J2STORE_CART_LINE_ITEM_TOTAL'); ?></th>
			</tr>
			</thead>
			<tbody>

				<?php foreach ($items as $item): ?>
				<?php
					$registry = new JRegistry;
					$registry->loadString($item->orderitem_params);
					$item->params = $registry;
					$thumb_image = $item->params->get('thumb_image', '');
				?>
				<tr>
					<td>
						<?php if($this->params->get('show_thumb_cart', 1) && !empty($thumb_image) && JFile::exists(JPATH_SITE.JPath::clean('/'.$thumb_image))): ?>
							<span class="cart-thumb-image">
								<img alt="<?php echo $item->orderitem_name; ?>" src="<?php echo JURI::root(true).JPath::clean('/'.$thumb_image); ?>" >
							</span>
						<?php endif; ?>
						<span class="cart-product-name">
							<?php echo $item->orderitem_name; ?>  x <?php echo $item->orderitem_quantity; ?>
						</span>
						<br />
						<?php if(isset($item->orderitemattributes)): ?>
							<span class="cart-item-options">
							<?php foreach ($item->orderitemattributes as $attribute):
								if($attribute->orderitemattribute_type == 'file') {
									unset($table);
									$table = F0FTable::getInstance('Upload', 'J2StoreTable')->getClone();
									if($table->load(array('mangled_name'=>$attribute->orderitemattribute_value))) {
										$attribute_value = $table->original_name;
									}
								}else {
									$attribute_value = $attribute->orderitemattribute_value;
								}
							?>
								<small>
								- <?php echo JText::_($attribute->orderitemattribute_name); ?> : <?php echo $attribute_value; ?>
								</small>						
             				   	<br />
							<?php endforeach;?>
							</span>
						<?php endif; ?>

						<?php if($this->params->get('show_price_field', 1)): ?>

							<span class="cart-product-unit-price">
								<span class="cart-item-title"><?php echo JText::_('J2STORE_CART_LINE_ITEM_UNIT_PRICE'); ?></span>								
								<span class="cart-item-value">
								<?php echo $currency->format($this->order->get_formatted_lineitem_price($item, $this->params->get('checkout_price_display_options', 1))); ?>
								</span>
							</span>
						<?php endif; ?>

						<?php if($this->params->get('show_sku', 1)): ?>
						<br />
							<span class="cart-product-sku">
								<span class="cart-item-title"><?php echo JText::_('J2STORE_CART_LINE_ITEM_SKU'); ?></span>
								<span class="cart-item-value"><?php echo $item->orderitem_sku; ?></span>
							</span>

						<?php endif; ?>
					</td>
					<td class="cart-line-subtotal">
						<?php echo $currency->format($this->order->get_formatted_lineitem_total($item, $this->params->get('checkout_price_display_options', 1))); ?>					
					</td>
				</tr>
				<?php endforeach; ?>
			</tbody>
			</table>
			<table class="cart-footer table table-bordered">
				<tr>
					<td>
						<?php echo JText::_('J2STORE_CART_SUBTOTAL'); ?>
					</td>
					<td>
						<?php echo $currency->format($this->order->get_formatted_subtotal($this->params->get('checkout_price_display_options', 1))); ?>
					</td>
				</tr>

				<!-- shipping -->
				<?php if(isset($this->order->order_shipping) && !empty($this->shipping->ordershipping_name)): ?>
                <tr>
                    <td>
                        <?php echo JText::_(stripslashes($this->shipping->ordershipping_name)); ?>
                    </td>
                     <td>
                        <?php echo $currency->format($this->order->order_shipping); ?>
                     </td>
                </tr>
				<?php endif; ?>
				<!-- shipping tax -->
				<?php if(isset($this->order->order_shipping_tax) && $this->order->order_shipping_tax > 0): ?>
                <tr>
                    <td>
                        <?php echo JText::_('J2STORE_ORDER_SHIPPING_TAX'); ?>
                    </td>
                     <td>
                        <?php echo $currency->format($this->order->order_shipping_tax); ?>
                     </td>
                </tr>
				<?php endif; ?>

				<!-- Surcharge -->
	        	<?php if ($order->order_surcharge > 0): ?>
	        	<tr>
	        		<td><?php echo JText::_("J2STORE_CART_SURCHARGE"); ?></td>
	        		<td><?php echo $currency->format($order->order_surcharge); ?></td>
	        	</tr>
	        	<?php endif; ?>


				 <!-- coupon -->

               <?php if(isset($this->coupons)): ?>
               <?php foreach($this->coupons as $coupon): ?>
               	<tr>
               		<td>
 							<?php echo JText::sprintf('J2STORE_COUPON_TITLE', $coupon->coupon_code); ?>
 					</td>
 					 <td>
 						 <?php echo $currency->format($coupon->amount); ?>
 					 </td>
 				</tr>
 				<?php endforeach; ?>
 				<?php endif;?>

 				<!-- other discount -->
 				<?php if(isset($this->other_discounts)): ?>
				<?php foreach($this->other_discounts as $other_discount): ?>
				<tr>
					<td>
						<?php echo JText::_($other_discount->name);?>
					</td>
					<td>
						<?php echo $currency->format($other_discount->value);?>
					</td>
				</tr>
				<?php endforeach; ?>
				<?php endif;?>
 				
 				<!-- voucher -->

               <?php if(count($this->vouchers)): ?>
               <?php foreach($this->vouchers as $voucher): ?>
               	<tr>
               		<td>
 							<?php echo JText::sprintf('J2STORE_VOUCHER_TITLE', $voucher->voucher_code); ?>
 					</td>
 					 <td>
 						 <?php echo $currency->format($voucher->amount); ?>

 					 </td>
 				</tr>
 				<?php endforeach; ?>
 				<?php endif;?>

				<!-- taxes -->
				<?php if(isset($this->taxes) && count($this->taxes) ): ?>
				<tr>
					<td>
							<?php if($this->params->get('checkout_price_display_options', 1)): ?>
								<?php echo JText::_('J2STORE_CART_INCLUDING_TAX'); ?>
							<?php else: ?>
								<?php echo JText::_('J2STORE_CART_TAX'); ?>
							<?php endif; ?>
							<br />
							<?php foreach ($this->taxes as $tax): ?>
								<?php echo JText::_($tax->ordertax_title); ?> (<?php echo (float) $tax->ordertax_percent; ?> %)
							<?php endforeach; ?>
					</td>
					<td>
					<?php foreach ($this->taxes as $tax): ?>
							<?php echo $currency->format($tax->ordertax_amount); ?>
					<?php endforeach; ?>
					</td>
				</tr>
				<?php endif; ?>

				<tr class="j2store-grand-total">
					<td>
							<?php echo JText::_('J2STORE_CART_GRANDTOTAL'); ?>
					</td>
					<td><?php echo $currency->format($this->order->get_formatted_grandtotal()); ?></td>
				</tr>
			</table>