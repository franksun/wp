<?php
/**
 * @package J2Store
 * @copyright Copyright (c)2014-17 Ramesh Elamathi / J2Store.org
 * @license GNU GPL v3 or later
 */
// No direct access to this file
defined('_JEXEC') or die;

?>
<?php echo J2Store::modules()->loadposition('j2store-cart-top'); ?>
<div class="j2store">
	<div class="j2store-cart">
		<?php if(count($this->items)): ?>
		<div class="span12"><?php echo $this->before_display_cart;?></div>
		<div class="row-fluid">
			<div class="span12">
			<form action="index.php"
				name="j2store-cart-form"
				id="j2store-cart-form"
				enctype="multipart/form-data"
				>

		<input type="hidden" name="option" value="com_j2store" />
		<input type="hidden" name="view" value="carts" />
		<input type="hidden" id="j2store-cart-task" name="task" value="update" />

		<?php  echo $this->loadTemplate('items'); ?>

		<div class="j2store-cart-buttons">
			<div class="buttons-left">
				<span class="cart-continue-shopping-button">
					<?php if($this->continue_shopping_url->type != 'previous'): ?>
						<input class="btn btn-primary" type="button" onclick="window.location='<?php echo $this->continue_shopping_url->url; ?>';" value="<?php echo JText::_('J2STORE_CART_CONTINUE_SHOPPING'); ?>" />
					<?php else: ?>
						<input class="btn btn-primary" type="button" onclick="window.history.back();" value="<?php echo JText::_('J2STORE_CART_CONTINUE_SHOPPING'); ?>" />
					<?php endif;?>
					
				</span>
				<span class="cart-update-button">
					<input class="btn btn-warning" type="submit" value="<?php echo JText::_('J2STORE_CART_UPDATE'); ?>" />
				</span>
			</div>
		</div>		
		</form>
		<!-- Display plugin results -->
		<?php  echo $this->after_display_cart; ?>
	</div>
  </div>

		<div class="row-fluid">
			<div class="span6">

				<div class="cart-estimator-discount-block">
					<?php echo $this->loadTemplate('coupon'); ?>
					<?php echo $this->loadTemplate('voucher'); ?>
					<?php echo $this->loadTemplate('calculator'); ?>
					<?php echo $this->loadTemplate('shipping'); ?>
				</div>

			</div>
			<div class="span6">
				<?php  echo $this->loadTemplate('totals'); ?>				
			</div>
		</div>


		<?php else:  ?>
			<span class="cart-no-items">
				<?php echo JText::_('J2STORE_CART_NO_ITEMS'); ?>
			</span>
		<?php endif; ?>
	</div>
</div>
<?php echo J2Store::modules()->loadposition('j2store-cart-bottom'); ?>