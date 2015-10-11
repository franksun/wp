<?php
/**
 * @package J2Store
 * @copyright Copyright (c)2014-17 Ramesh Elamathi / J2Store.org
 * @license GNU GPL v3 or later
 */
// No direct access to this file
defined('_JEXEC') or die;
?>
<h3><?php echo JText::_('J2STORE_CART_TOTALS'); ?></h3>
<table class="cart-footer table table-bordered">
				<tr>
					<td>
						<?php echo JText::_('J2STORE_CART_SUBTOTAL'); ?>
					</td>
					<td>
						<?php echo $this->currency->format($this->order->get_formatted_subtotal($this->params->get('checkout_price_display_options', 1))); ?>
					</td>
				</tr>

				<!-- shipping -->
				<?php if(isset($this->order->order_shipping) && !empty($this->shipping->ordershipping_name)): ?>
                <tr>
                    <td>
                        <?php echo JText::_(stripslashes($this->shipping->ordershipping_name)); ?>
                    </td>
                     <td>
                        <?php echo $this->currency->format($this->order->order_shipping); ?>
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
                        <?php echo $this->currency->format($this->order->order_shipping_tax); ?>
                     </td>
                </tr>
				<?php endif; ?>

				 <!-- coupon -->

               <?php if(isset($this->coupons)): ?>
               <?php foreach($this->coupons as $coupon): ?>
               	<tr>
               		<td>
 							<?php echo JText::sprintf('J2STORE_COUPON_TITLE', $coupon->coupon_code); ?>
 							<a class="j2store-remove remove-icon" href="javascript:void(0)" onClick="jQuery('#j2store-cart-form #j2store-cart-task').val('removeCoupon'); jQuery('#j2store-cart-form').submit();" >X</a>
 					</td>
 					 <td>
 						 <?php echo $this->currency->format($coupon->amount); ?>

 					 </td>
 				</tr>
 				<?php endforeach; ?>
 				<?php endif;?>

 				<!-- other discount -->
				<?php if(isset($this->other_discounts)):?>
				<?php foreach($this->other_discounts as $other_discount):?>
				<tr>
					<td>
						<?php echo JText::_($other_discount->name);?>
						<a class="j2store-remove remove-icon" href="<?php echo $other_discount->url;?>">X</a>
					</td>
					<td>
						<?php echo $other_discount->value;?>
					</td>
				</tr>
				<?php endforeach;?>
				<?php endif;?>
				
 				<!-- voucher -->

               <?php if(isset($this->vouchers)): ?>
               <?php foreach($this->vouchers as $voucher): ?>
               	<tr>
               		<td>
 							<?php echo JText::sprintf('J2STORE_VOUCHER_TITLE', $voucher->voucher_code); ?>
 							<a class="j2store-remove remove-icon" href="javascript:void(0)" onClick="jQuery('#j2store-cart-form #j2store-cart-task').val('removeVoucher'); jQuery('#j2store-cart-form').submit();" >X</a>
 					</td>
 					 <td>
 						 <?php echo $this->currency->format($voucher->amount); ?>

 					 </td>
 				</tr>
 				<?php endforeach; ?>
 				<?php endif;?>

				<!-- taxes -->
				<?php if(isset($this->taxes) && count($this->taxes) ): ?>
				<tr>
					<td>
							<?php if($this->params->get('checkout_price_display_options', 0) == 1): ?>
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
					<br />
					<?php foreach ($this->taxes as $tax): ?>							
							<?php echo $this->currency->format($tax->ordertax_amount); ?>
							<br />
					<?php endforeach; ?>
					</td>
				</tr>
				<?php endif; ?>

				<tr>
					<td>
							<?php echo JText::_('J2STORE_CART_GRANDTOTAL'); ?>
					</td>
					<td><?php echo $this->currency->format($this->order->order_total); ?></td>
				</tr>
			</table>
			
			<div class="buttons-right">
				<span class="cart-checkout-button">
					<a class="btn btn-large btn-success" href="<?php echo $this->checkout_url; ?>" ><?php echo JText::_('J2STORE_PROCEED_TO_CHECKOUT'); ?> </a>
				</span>
				<?php echo J2Store::plugin()->eventWithHtml('AfterDisplayCheckoutButton', array($this->order)); ?>	
			</div>