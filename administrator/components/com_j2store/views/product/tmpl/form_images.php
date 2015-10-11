<?php
/**
 * @package J2Store
 * @copyright Copyright (c)2014-17 Ramesh Elamathi / J2Store.org
 * @license GNU GPL v3 or later
 */

// No direct access
defined('_JEXEC') or die;
$image_counter = 0;
?>

<div class="j2store-product-images">
	<div class="row-fluid">
		<div class="span12">

		<div class="control-group">
			<?php echo J2Html::label(JText::_('J2STORE_PRODUCT_THUMB_IMAGE'), 'thumb_image',array('control-label')); ?>
			<?php echo J2Html::media($this->form_prefix.'[thumb_image]' ,$this->item->thumb_image,array('id'=>'thumb_image','image_id'=>'input-thumb-image'));?>
		</div>
	<div class="control-group">
		<?php echo J2Html::label(JText::_('J2STORE_PRODUCT_MAIN_IMAGE'), 'main_image' ,array('control-label')); ?>
		<?php echo J2Html::media($this->form_prefix.'[main_image]' ,$this->item->main_image,array('id'=>'main_image' ,'image_id'=>'input-main-image'));?>
		<?php echo J2Html::hidden($this->form_prefix.'[j2store_productimage_id]',$this->item->j2store_productimage_id);?>
	</div>

	<table id="additionalImages" class="table table-bordered table-striped table-condensed">
		<thead>
		<tr>
			<td colspan="2">
				<div class="pull-right">
					<input type="button" id="addImagBtn" class="btn btn-success"  value="<?php echo JText::_('J2STORE_PRODUCT_ADDITIONAL_IMAGES_ADD')?>"/>
				</div>
			</td>
			</tr>

		</thead>
			<tr>
				<th>
					<?php echo J2Html::label(JText::_('J2STORE_PRODUCT_ADDITIONAL_IMAGE'), 'additioanl_image_label'); ?>
				</th>
				<th>
					<?php echo JText::_('J2STORE_DELETE');?>
				</th>
			</tr>
		<?php

		if(isset($this->item->additional_images) && !empty($this->item->additional_images)):?>
			<?php $add_image =json_decode($this->item->additional_images); ?>
		<?php endif;
				if(isset($add_image) && !empty($add_image)):
					foreach($add_image as $key => $img):?>
						<tbody class="tr-additional-image" id="additional-image-<?php echo $image_counter;?>">
							<tr>
								<td colspan="1">
									<?php echo J2Html::media($this->form_prefix.'[additional_images]['.$image_counter.']' ,$img,array('id'=>'additional_image_0' ,'class' =>'image-input' ,'image_id'=>'input-additional-image-0'));?>
								</td>
								<td>
									<input type="button" onclick="deleteImageRow(this)" class="btn btn-success"  value="<?php echo JText::_('J2STORE_DELETE')?>"/>
								</td>
							</tr>
						</tbody>
					<?php $image_counter++;?>
					<?php endforeach;?>


		<?php else:?>
			<tbody class="tr-additional-image" id="additional-image-<?php echo $image_counter;?>">
				<tr>
					<td colspan="1">
						<?php echo J2Html::media($this->form_prefix.'[additional_images]['.$image_counter.']' ,'',array('id'=>'additional_image_0' ,'class' =>'image-input' ,'image_id'=>'input-additional-image-0'));?>
					</td>
					<td><input type="button" onclick="deleteImageRow(this)" class="btn btn-success"  value="<?php echo JText::_('J2STORE_DELETE')?>"/></td>
				</tr>
			</tbody>
		<?php endif;?>
	</table>
	</div>
</div>

		<div class="alert alert-info">
			<h4><?php echo JText::_('J2STORE_QUICK_HELP'); ?></h4>
			<h5><?php echo JText::_('J2STORE_FEATURE_AVAILABLE_IN_J2STORE_PRODUCT_LAYOUTS_AND_ARTICLES'); ?></h5>
			<p><?php echo JText::_('J2STORE_PRODUCT_IMAGES_HELP_TEXT'); ?></p>
		</div>
	</div>
<script type="text/javascript">

function deleteImageRow(element){
	(function($){
		var tbody = $(element).closest('.tr-additional-image');

		if($(".tr-additional-image").length ==1){
		    // it exists
		    alert('leave this ');
		    return false;
		}

			tbody.remove();
	})(j2store.jQuery);
	}
var counter = <?php echo $image_counter;?>;

jQuery("#addImagBtn").click(function(){
	counter++;
	(function($){
	var image_div =	$('#additional-image-0');
		addAdditionalImage(image_div ,counter);
	})(j2store.jQuery);


})

function addAdditionalImage(image_div , counter){
	(function($){
		//increament the
	    var clone = image_div.clone();
		//need to change the input name
		clone.find('.j2store-media-slider-image-preview').each(function(){
			$(this).attr('src' , '<?php echo JUri::root().'media/j2store/images/common/no_image-100x100.jpg'; ?>');
				if($('#input-additional-image-'+counter).html() ==''){
		 			$(this).attr("id",'input-additional-image-'+counter);
				}
		  });
	    clone.find(':text').each(function(){
	    	$(this).attr("name", jQuery(this).attr("name").replace(jQuery(this).attr("name").match(/\[[0-9]+\]/), "["+counter+"]"));
	    	$(this).attr("value",'');
	 		$(this).attr("id",'jform_image_additional_image_'+counter);
	 		$(this).attr("image_id",'input-additional-image-'+counter);
		  });
		clone.find('.modal').each(function(){
			$(this).attr('href','index.php?option=com_media&view=images&tmpl=component&asset=1&author=673&fieldid=jform_image_additional_image_'+counter+'&folder=');
		})

		 //to chang label id
		 var new_html = image_div.before(clone);

			//now it is placed just of the image div so remove the element
		 var processed_html =  clone.remove();

		 //get the newly added tbody and insert after the additional-image-0
		  $(processed_html).insertAfter($('#additionalImages tbody:last-child'));

		// intialize squeeze box again for edit button to work
		window.parent.SqueezeBox.initialize({});
		window.parent.SqueezeBox.assign($$('a.modal'), {
			parse: 'rel'
		});




	})(j2store.jQuery);
}
</script>