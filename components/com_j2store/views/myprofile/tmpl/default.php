<?php
/**
 * @package J2Store
* @copyright Copyright (c)2014-17 Ramesh Elamathi / J2Store.org
* @license GNU GPL v3 or later
*/
// No direct access to this file
defined('_JEXEC') or die;
JHTML::_('behavior.modal');
$this->params = J2Store::config();
$plugin_title_html = J2Store::plugin()->eventWithHtml('AddMyProfileTab');
$plugin_content_html = J2Store::plugin()->eventWithHtml('AddMyProfileTabContent', array($this->orders));
?>
<div class="j2store">
	<div class="j2store-order">
		<h3><?php echo JText::_('J2STORE_MYPROFILE')?></h3>
		 <div class="tabbable tabs">
         	   <ul class="nav nav-tabs">
                  <li class="active">
	                  	<a href="#orders-tab" data-toggle="tab"><i class="fa fa-th-large"></i>
	                  		 <?php echo JText::_('J2STORE_MYPROFILE_ORDERS'); ?>
	                  	</a>
                 </li>
                 	<?php if($this->params->get('download_area', 1)): ?>
                 	<li>
	                  	<a href="#downloads-tab" data-toggle="tab"><i class="fa fa-cloud-download"></i>
	                  		 <?php echo JText::_('J2STORE_MYPROFILE_DOWNLOADS'); ?>
	                  	</a>
                 	</li>
                 	<?php endif;?>
                 	
                 	<?php if($this->user->id) : ?>
                    <li>
                  		<a href="#address-tab" data-toggle="tab">
                  			<i class="fa fa-globe"></i>
                  		 	<?php echo JText::_('J2STORE_MYPROFILE_ADDRESS'); ?>
                  		</a>
                  </li>
                  <?php endif; ?>
                  <?php echo $plugin_title_html; ?>
            	</ul>
				<div class="tab-content">
	                  <div class="tab-pane active" id="orders-tab">
		                  <div class="span12 col-xs-12 col-sm-12 col-md-12 col-lg-12">
							<div class="table-responsive">
								<?php echo $this->loadTemplate('orders');?>
							</div>
						</div>
	                  </div>
					
					<?php if($this->params->get('download_area', 1)): ?>
	                  <div class="tab-pane" id="downloads-tab">
	                  		<div class="span12 col-xs-12 col-sm-12 col-md-12 col-lg-12">
	                  			<?php echo $this->loadTemplate('downloads');?>
							</div>
	                  </div>
					<?php endif; ?>
					
	                  <?php if($this->user->id) : ?>
	                  	<div class="tab-pane" id="address-tab">
	                  		<div class="span12 col-xs-12 col-sm-12 col-md-12 col-lg-12">
	                  			<?php echo $this->loadTemplate('addresses');?>
							</div>
	                  </div>
	                  <?php endif; ?>
	                  <?php echo $plugin_content_html; ?>
                 </div>
		</div>
	</div>
</div>