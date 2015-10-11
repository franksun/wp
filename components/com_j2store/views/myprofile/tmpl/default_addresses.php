<?php
/**
 * @package J2Store
* @copyright Copyright (c)2014-17 Ramesh Elamathi / J2Store.org
* @license GNU GPL v3 or later
*/
// No direct access to this file
defined('_JEXEC') or die;
?>

<h3 class="myprofile-address-list-heading"><?php echo JText::_('J2STORE_ADDRESS_LIST');?></h3>
	<div class="myprofile-address-addnew">
		<?php echo J2StorePopup::popupAdvanced('index.php?option=com_j2store&view=myprofile&task=editAddress&layout=address&tmpl=component&address_id=', JText::_('J2STORE_ADD') ,array('update'=>true,'class'=>'btn btn-success','width'=>800 , 'height'=>600));?>

	</div>
<hr>
<ul class="j2store-myprofile-address-list">
	<?php
	if($this->orderinfos && !empty($this->orderinfos ) ):
	foreach($this->orderinfos as $orderinfo):?>
	<?php
			$addressTable = F0FTable::getInstance('Address', 'J2StoreTable');
			$addressTable->load($orderinfo->j2store_address_id);
			$fields =  $this->fieldClass->getFields($addressTable->type,$addressTable,'address');
		?>
		<li id="j2store-address-tr-<?php echo $orderinfo->j2store_address_id;?>" class="j2store-myprofile-address-single-list well" >
			<ul class="j2store-myprofile-address-controls inline pull-right">
				<li class="myprofile-address-control-edit">
					<?php echo J2StorePopup::popup('index.php?option=com_j2store&view=myprofile&task=editAddress&layout=address&tmpl=component&address_id='.$orderinfo->j2store_address_id, JText::_('J2STORE_EDIT') ,array('update'=>true,'width'=>800 , 'height'=>500));?>
				</li>
				<li class="myprofile-address-control-delete">
					<a  href="<?php echo JRoute::_('index.php?option=com_j2store&view=myprofile&task=deleteAddress&address_id='.$orderinfo->j2store_address_id);?>" >
						<?php echo JText::_('J2STORE_DELETE');?>
					</a>
				</li>
			</ul>
			<?php foreach ($fields as $fieldName => $oneExtraField):?>
					<?php if(property_exists($addressTable, $fieldName)):?>
						<strong><?php echo JText::_($oneExtraField->field_name); ?></strong> :
						<?php if($fieldName == 'country_id'): ?>
							<?php echo $orderinfo->country_name; ?> <br />
						<?php elseif($fieldName == 'zone_id'): ?>
							<?php echo $orderinfo->zone_name; ?> <br />
						<?php else: ?>
							<?php echo $addressTable->$fieldName;?> <br />
						<?php endif;?>
					<?php endif;?>
				<?php endforeach;?>

		</li>
	<?php endforeach;?>
	<?php endif;?>
</ul>
<div class="before-profile">
<?php if(isset($this->beforedisplayprofile)): ?>
	<?php echo $this->beforedisplayprofile;?>
<?php endif; ?>
</div>

