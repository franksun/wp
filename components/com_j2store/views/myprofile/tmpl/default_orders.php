<?php
/**
 * @package J2Store
* @copyright Copyright (c)2014-17 Ramesh Elamathi / J2Store.org
* @license GNU GPL v3 or later
*/
// No direct access to this file
defined('_JEXEC') or die;
$currency = J2Store::currency();
$doc = JFactory::getDocument();
$doc->addStyleSheet(JUri::root().'media/j2store/css/font-awesome.min.css');
?>

<?php if(isset($this->orders) && count($this->orders)) : ?>
<table class="table table-bordered table-striped">
	<thead>
		<th><?php echo JText::_('J2STORE_ORDER_DATE');?></th>
		<th><?php echo JText::_('J2STORE_INVOICE_NO');?></th>
		<th><?php echo JText::_('J2STORE_ORDER_AMOUNT');?></th>
		<th><?php echo JText::_('J2STORE_ORDER_STATUS');?></th>
		<th><?php echo JText::_('J2STORE_ACTIONS');?></th>
	</thead>
	<tbody>
		<?php foreach($this->orders as $item):?>
		<?php 
		$order = F0FTable::getInstance('Order', 'J2StoreTable');
		$order->load(array('order_id'=>$item->order_id)); 
		
		?>
		<tr>
			<td><?php
			$tz = JFactory::getConfig()->get('offset');
			$date = JFactory::getDate($item->created_on, $tz);
			$order_date = $date->format(J2Store::config()->get('date_format', JText::_('DATE_FORMAT_LC1')), true);			
			echo $order_date;
			?>
			</td>
			<td><?php

			if($item->invoice_number && !empty($item->invoice_prefix) ) {
				$invoice = $item->invoice_prefix.$item->invoice_number;
			}else {
			$invoice = $item->j2store_order_id;
			}
			echo $invoice;
			?></td>
			<td><?php echo $currency->format($order->get_formatted_grandtotal()); ?></td>
			<td>
				<?php if(isset($item->orderstatus_name) && !empty($item->orderstatus_name)) : ?>
				<label class="label <?php echo $item->orderstatus_cssclass;?>">
					<?php echo JText::_($item->orderstatus_name);?>
				</label>
				<?php else: //legacy compatibility ?>
					<label class="label">
						<?php echo JText::_($item->order_state);?>
					</label>
				<?php endif; ?>
			</td>
			<td>
				<span class="j2store-order-action-icons">
					<span class="j2store-order-view">
					<?php $viewUrl = JRoute::_('index.php?option=com_j2store&view=myprofile&task=vieworder&tmpl=component&order_id='.$item->order_id); ?>
						<?php echo J2StorePopup::popup($viewUrl, '', array('class'=>'fa fa-list-alt'));?>
					</span>

					<span class="j2store-order-print">
					<?php
						$printUrl = JRoute::_('index.php?option=com_j2store&view=myprofile&task=printOrder&tmpl=component&order_id='.$item->order_id);
						echo J2StorePopup::popup($printUrl, '', array('class'=>'fa fa-print'));
						?>
					</span>

					<?php
						if(JFolder::exists(JPATH_LIBRARIES.'/tcpdf')):
						include_once JPATH_LIBRARIES .'/tcpdf/tcpdf.php';
						if(class_exists('TCPDF')):
						?>
					<span class="j2store-order-pdf">
						<a href="<?php echo JRoute::_('index.php?option=com_j2store&view=myprofile&task=createOrderPdf&order_id='.$item->order_id); ?>" >
							<i class="icon icon-download fa fa-download-alt"></i>
						</a>
					</span>
					<?php endif;?>
					<?php endif;?>
					<!-- display plugin event actions -->
 					<?php if(isset($item->after_display_order)):
                       		echo $item->after_display_order; 
                     	endif;?>
				</span>
			</td>
		</tr>
		<?php endforeach;?>
	</tbody>
</table>
<?php endif; ?>