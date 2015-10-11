<?php
/**
 * @package J2Store
 * @copyright Copyright (c)2014-17 Ramesh Elamathi / J2Store.org
 * @license GNU GPL v3 or later
 */
// No direct access
defined('_JEXEC') or die;

//print_r($this->row);
?>
<div class="j2store">

	<?php if(isset($this->products) && count($this->products)):?>
	<h3><?php echo JText::_( "J2STORE_PAI_IMPORT_PRODUCT_OPTIONS_FOR" ); ?>:<?php echo $this->row->product_name; ?></h3>
	<form action="index.php" method="post" name="adminForm" id="adminForm" enctype="multipart/form-data">

		<div class="row-fluid">
			<div>
				<button class="btn btn-primary"
					onclick="document.getElementById('task').value='importattributes'; document.adminForm.submit();">
					<?php echo JText::_('J2STORE_PAI_IMPORT_PRODUCT_OPTIONS'); ?>
				</button>
			</div>
			<br />
			<div class="alert alert-block alert-info"><?php echo JText::_('J2STORE_PAI_IMPORT_PRODUCT_OPTIONS_HELP_TEXT');?></div>
			<table class="adminlist table table-striped">
				<thead>
					<tr>
						<th style="width: 20px;">
							<input type="checkbox"	name="checkall-toggle" value=""  />
						</th>
						<th style="text-align: left;"><?php echo JText::_('J2STORE_PRODUCT_ID'); ?>
						</th>
						<th style="text-align: left;"><?php echo JText::_('J2STORE_PRODUCT_NAME'); ?>
						</th>
						<th style="text-align: left;"><?php echo JText::_( "J2STORE_PRODUCT_OPTIONS" ); ?>
						</th>

					</tr>
				</thead>
				<tbody>

					<?php $i=0; $k=0; ?>
					<?php foreach ($this->products as $item) :
					$checked = JHTML::_('grid.id', $i, $item->j2store_product_id);
					$attributes = $this->productHelper->getProductOptions($item);
					?>
					<tr class='row<?php echo $k; ?>'>
						<td style="text-align: center;"><?php
							echo $checked;
						?>
						</td>
						<td style="text-align: left;"><?php echo $item->j2store_product_id?>
						</td>

						<td style="text-align: left;"><?php echo $item->product_name; ?>
						</td>
						<td style="text-align: left;">
					 <?php if(count($attributes)) : ?>
				 		<ol>
					 	<?php foreach($attributes as $attribute) : ?>
					 		<li><?php echo $attribute['option_name']; ?></li>
					 		<?php if(isset($attribute['optionvalue']) && !empty($attribute['optionvalue']) && count($attribute['optionvalue'])) : ?>
					 				<strong> <?php echo JText::_('J2STORE_PAI_IMPORT_VALUES_FOR_THIS_OPTION'); ?></strong>
					 				<ol>
					 				<?php foreach ($attribute['optionvalue'] as $a_option) :
					 				?>
									<li>
										<span><?php echo $a_option['optionvalue_name']; ?> </span>
										<span>
										<?php echo $a_option['product_optionvalue_prefix']; ?>&nbsp;<?php echo $this->currency->format($a_option['product_optionvalue_price']); ?>
										</span>
									</li>
									<?php endforeach; ?>
									</ol>
					 		<?php endif; ?>
					 	<?php endforeach; ?>
					 	</ol>
					 <?php endif; ?>
						</td>
					</tr>
					<?php $i=$i+1; $k = (1 - $k); ?>
					<?php endforeach; ?>

				</tbody>
				<tfoot>
					<tr>
						<td colspan="4"><?php // echo @$this->pagination->getListFooter(); ?>
						</td>
					</tr>
				</tfoot>
			</table>

			<input type="hidden" name="order_change" value="0" /> <input
				type="hidden" name="product_id" value="<?php echo $this->row->j2store_product_id; ?>" /> <input
				type="hidden" name="task" id="task" value="setpaimport" /> <input
				type="hidden" name="option" value="com_j2store" /> <input
				type="hidden" name="view" value="products" /> <input type="hidden"
				name="boxchecked" value="" />
				<input type="hidden"		name="filter_order" value="<?php // echo $this->lists['order']; ?>" />
			<input type="hidden" name="filter_order_Dir"
				value="<?php // echo $this->lists['order_Dir']; ?>" />
		</div>
	</form>
	<?php else: ?>
	<div>
		<?php echo JText::_('J2STORE_NO_ITEMS_FOUND'); ?>
	</div>
	<?php endif; ?>
</div>
<script>

jQuery(document).ready(function() {
	jQuery('input[name=checkall-toggle]').click(function(event) {  //on click
        if(this.checked) {
            // check select status
        	jQuery('input[type=checkbox]').each(function() { //loop through each checkbox
                this.checked = true;  //select all checkboxes with class "checkbox1"
            });
        }else{
        	jQuery('input[type=checkbox]').each(function() { //loop through each checkbox
                this.checked = false; //deselect all checkboxes with class "checkbox1"
            });
        }
    });

});

					 				</script>