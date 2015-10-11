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
$this->taxes = $this->order->getOrderTaxrates();
$this->shipping = $this->order->getOrderShippingRate();
$this->coupons = $this->order->getOrderCoupons();
$this->vouchers = $this->order->getOrderVouchers();
$currency = J2Store::currency();

?>
	<h3><?php echo JText::_('J2STORE_ORDER_SUMMARY')?></h3>
	<table class="j2store-cart-table table table-bordered">
		<thead>
			<tr>
				<th><?php echo JText::_('J2STORE_CART_LINE_ITEM'); ?></th>
				<th><?php echo JText::_('J2STORE_CART_LINE_ITEM_QUANTITY'); ?></th>
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
						<?php if($this->params->get('show_thumb_cart', 1) && !empty($thumb_image)): ?>
							<span class="cart-thumb-image">
								<?php if(JFile::exists(JPATH_SITE.'/'.$thumb_image)): ?>
									<img src="<?php echo JUri::root(true). '/'.$thumb_image; ?>" >
								<?php endif;?>
							</span>
						<?php endif; ?>
						<span class="cart-product-name">
							<?php echo $item->orderitem_name; ?>  
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

								<!--link to download for files-->
             				   <?php if(JFactory::getApplication()->isAdmin() && $attribute->orderitemattribute_type=='file' && JFactory::getApplication()->input->getString('task')!='printOrder'):?>

             					  <a target="_blank" class="btn btn-primary"
             					  href="<?php echo JRoute::_('index.php?option=com_j2store&view=orders&task=download&ftoken='.$attribute->orderitemattribute_value);?>"
             					  >
             					  <i class="icon icon-download"></i>
             					   <?php echo JText::_('J2STORE_DOWNLOAD');?>
             					   </a>
             				   	<?php endif;?>
             				   	<br />
							<?php endforeach;?>
							</span>
						<?php endif; ?>

						<?php if($this->params->get('show_price_field', 1)): ?>

							<span class="cart-product-unit-price">
								<span class="cart-item-title"><?php echo JText::_('J2STORE_CART_LINE_ITEM_UNIT_PRICE'); ?></span>								
								<span class="cart-item-value">
									<?php if($this->params->get('checkout_price_display_options', 1)): ?>
										<?php 
										$price = $item->orderitem_finalprice_with_tax / $item->orderitem_quantity;
										echo $currency->format( $price, $this->order->currency_code, $this->order->currency_value); 
										?>
									<?php else: ?>
										<?php 
										$price = $item->orderitem_finalprice_without_tax / $item->orderitem_quantity;
										echo $currency->format($price, $this->order->currency_code, $this->order->currency_value); ?>
									<?php endif; ?>
								</span>
							</span>
						<?php endif; ?>

						<?php if(!empty($item->orderitem_sku)): ?>
						<br />
							<span class="cart-product-sku">
								<span class="cart-item-title"><?php echo JText::_('J2STORE_CART_LINE_ITEM_SKU'); ?></span>
								<span class="cart-item-value"><?php echo $item->orderitem_sku; ?></span>
							</span>

						<?php endif; ?>
					</td>
					<td><?php echo $item->orderitem_quantity; ?></td>
					<td class="cart-line-subtotal">
						<?php echo $currency->format($this->order->get_formatted_lineitem_total($item, $this->params->get('checkout_price_display_options', 1)), $this->order->currency_code, $this->order->currency_value ); ?>					
					</td>
				</tr>
				<?php endforeach; ?>
			</tbody>
			
			<?php $colspan = '2';?>
			<tfoot class="cart-footer">
				<tr>
					<td colspan="<?php echo $colspan; ?>">
						<?php echo JText::_('J2STORE_CART_SUBTOTAL'); ?>
					</td>
					<td>
						<?php echo $currency->format($this->order->get_formatted_subtotal($this->params->get('checkout_price_display_options', 1)), $this->order->currency_code, $this->order->currency_value ); ?>
					</td>
				</tr>

				<!-- shipping -->
				<?php if(isset($this->order->order_shipping) && !empty($this->shipping->ordershipping_name)): ?>
                <tr>
                    <td colspan="<?php echo $colspan; ?>">
                        <?php echo JText::_(stripslashes($this->shipping->ordershipping_name)); ?>
                    </td>
                     <td>
                        <?php echo $currency->format($this->order->order_shipping, $this->order->currency_code, $this->order->currency_value); ?>
                     </td>
                </tr>
				<?php endif; ?>
				<!-- shipping tax -->
				<?php if(isset($this->order->order_shipping_tax) && $this->order->order_shipping_tax > 0): ?>
                <tr>
                    <td colspan="<?php echo $colspan; ?>">
                        <?php echo JText::_('J2STORE_ORDER_SHIPPING_TAX'); ?>
                    </td>
                     <td>
                        <?php echo $currency->format($this->order->order_shipping_tax, $this->order->currency_code, $this->order->currency_value); ?>
                     </td>
                </tr>
				<?php endif; ?>

				<!-- Surcharge -->
	        	<?php if ($this->order->order_surcharge > 0): ?>
	        	<tr>
	        		<td colspan="<?php echo $colspan; ?>"><?php echo JText::_("J2STORE_CART_SURCHARGE"); ?></td>
	        		<td><?php echo $currency->format($this->order->order_surcharge, $this->order->currency_code, $this->order->currency_value); ?></td>
	        	</tr>
	        	<?php endif; ?>


	        	<!-- Surcharge -->
	        	<?php if ($this->order->order_discount > 0): ?>
	        	<tr>
	        	<td colspan="<?php echo $colspan; ?>">
	        	<?php echo JText::_('J2STORE_MINUS_SYMBOL'); ?> <?php echo JText::_('J2STORE_CART_DISCOUNT'); ?>
				 <!-- coupon -->
	
               <?php if(isset($this->coupons)): ?>
               <table>
               <?php foreach($this->coupons as $coupon): ?>
               	<tr>
               		<td>
 							<?php echo JText::sprintf('J2STORE_COUPON_TITLE', $coupon->coupon_code); ?>
 					</td>
 					 <td>
 						 <?php echo $currency->format($coupon->amount, $this->order->currency_code, $this->order->currency_value); ?>
 					 </td>
 				</tr>
 				<?php endforeach; ?>
 				</table>
 				<?php endif;?>

 				<!-- voucher -->

               <?php if(count($this->vouchers)): ?>
               <table>
               <?php foreach($this->vouchers as $voucher): ?>
               	<tr>
               		<td>
 							<?php echo JText::sprintf('J2STORE_VOUCHER_TITLE', $voucher->voucher_code); ?>
 					</td>
 					 <td>
 						 <?php echo $currency->format($voucher->amount, $this->order->currency_code, $this->order->currency_value); ?>

 					 </td>
 				</tr>
 				<?php endforeach; ?>
 				</table>
 				<?php endif;?>
 				</td>
 					<td><?php echo $currency->format($this->order->order_discount, $this->order->currency_code, $this->order->currency_value); ?></td>
				</tr>
				<?php endif; ?>
				<!-- taxes -->
				<?php if(isset($this->taxes) && count($this->taxes) ): ?>
				<tr>
					<td colspan="<?php echo $colspan; ?>">
							<?php if($this->params->get('checkout_price_display_options', 1)): ?>
								<?php echo JText::_('J2STORE_CART_INCLUDING_TAX'); ?>
							<?php else: ?>
								<?php echo JText::_('J2STORE_CART_TAX'); ?>
							<?php endif; ?>
							<br />
							<?php foreach ($this->taxes as $tax): ?>
								<?php echo JText::_($tax->ordertax_title); ?> (<?php echo (float) $tax->ordertax_percent; ?> %)
								<br />
							<?php endforeach; ?>
					</td>
					<td>
					<?php foreach ($this->taxes as $tax): ?>
							<?php echo $currency->format($tax->ordertax_amount, $this->order->currency_code, $this->order->currency_value); ?>
							<br />
					<?php endforeach; ?>
					</td>
				</tr>
				<?php endif; ?>

				<tr class="j2store-grand-total">
					<td colspan="<?php echo $colspan; ?>">
							<?php echo JText::_('J2STORE_CART_GRANDTOTAL'); ?>
					</td>
					<td><?php echo $currency->format($this->order->get_formatted_grandtotal(), $this->order->currency_code, $this->order->currency_value); ?></td>
				</tr>
			</tfoot>	
			</table>