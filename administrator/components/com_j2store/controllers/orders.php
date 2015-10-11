<?php
/**
 * @package J2Store
 * @copyright Copyright (c)2014-17 Ramesh Elamathi / J2Store.org
 * @license GNU GPL v3 or later
 */
// No direct access to this file
defined('_JEXEC') or die;

class J2StoreControllerOrders extends F0FController
{
	public function __construct($config) {
		parent::__construct($config);
		$this->registerTask('apply', 'save');
		$this->registerTask('saveNew', 'save');
	}


	/*
	 * Method to save Order status
	*/
	public function saveOrderstatus(){
		$data = $this->input->getArray($_POST);
		$id = $this->input->getInt('id');
		$status =false;
		$return = isset($data['return']) ? $data['return'] : '';
		$order_id = $this->input->getString('order_id');
		$order = F0FTable::getInstance('Order', 'J2StoreTable');
		$order->load(array('order_id'=>$order_id));

		if(!empty($order->order_id)) {

			//update status
			$order->update_status($data['order_state_id'], $data['notify_customer']);

			if(isset($data['reduce_stock']) && $data['reduce_stock'] == 1) {
				$order->reduce_order_stock();
			}

			if(isset($data['increase_stock']) && $data['increase_stock'] == 1) {
				$order->restore_order_stock();
			}

			if(isset($data['grant_download_access']) && $data['grant_download_access'] == 1) {
				$order->grant_download_permission();
			}

		}

		//is it an ajax call
		if($return){
			$json =array();
			$link = 'index.php?option=com_j2store&view=orders';
			$json['success']['link'] = $link;
			echo json_encode($json);
			JFactory::getApplication()->close();
		}else {
			$url ='index.php?option=com_j2store&view=order&task=edit&id='.$id;
			$this->setRedirect($url, $msg,$msgType);
		}

	}

	/**
	 * Method to save Order Customer Note
	 */
	public function saveOrderCnote(){
		$data = $this->input->getArray($_POST);
		$id = $this->input->getInt('id');
		$order = F0FTable::getAnInstance('Order' ,'J2StoreTable');
		$msg = JText::_('J2STORE_ORDER_SAVE_ERROR');
		$msgType='warning';
		//must check id exists
		if($id){
			//then load the id and confirm row exists
			if($order->load($id)){
				//now assign the customer note to order customer note object
				$order->customer_note = $data['customer_note'];
				$msg = JText::_('J2STORE_ORDER_SAVED_SUCCESSFULLY');
				$msgType ='message';
				if(!$order->save($order)){
					$msg = JText::_('J2STORE_ORDER_SAVE_ERROR');
					$msgType='warning';
				}
			}
		}
		$url ='index.php?option=com_j2store&view=order&task=edit&id='.$id;
		$this->setRedirect($url, $msg,$msgType);

	}

	/**
	 * Method to save shipping tracking id
	 */
	public function saveTrackingId(){
		$data = $this->input->getArray($_POST);
		$id = $this->input->getInt('id');
		$order = F0FTable::getAnInstance('Order' ,'J2StoreTable');
		$msg = '';
		$msgType='warning';

		//must check id exists
		if($order->load($id)){
			//load the shipping
			$ordershipping = F0FTable::getAnInstance('Ordershipping', 'J2StoreTable');

			if($ordershipping->load(array('order_id'=>$order->order_id))){
				$ordershipping->ordershipping_tracking_id = isset($data['ordershipping_tracking_id']) ? $data['ordershipping_tracking_id'] : '';
				if($ordershipping->store()) {
					$msg = JText::_('J2STORE_ORDER_SAVED_SUCCESSFULLY');
					$msgType ='message';
				}else {
					$msg = JText::_('J2STORE_ORDER_SAVE_ERROR');
					$msgType='warning';
				}
			}
		}
		$url ='index.php?option=com_j2store&view=order&task=edit&id='.$id;
		$this->setRedirect($url, $msg,$msgType);

	}

	/**
	 * Method to edit orderinfo based on the address type
	 *
	 */
	function setOrderinfo(){
		$order_id  = $this->input->getString('order_id');
		$address_type = $this->input->getString('address_type');
		$orderinfo = F0FTable::getAnInstance('Orderinfo','J2StoreTable');
		$orderinfo->load(array('order_id'=>$order_id));

		$processed = $this->removePrefix((array)$orderinfo,$address_type);
		$model = F0FModel::getTmpInstance('Orders','J2StoreModel');
		$view = $this->getThisView();
		$view->setModel($model, true);
		$view->addTemplatePath(JPATH_ADMINISTRATOR.'/components/com_j2store/views/order/tmpl/');
		$view->set('address_type',$address_type);
		$fieldClass  = J2Store::getSelectableBase();
		$view->set('fieldClass' , $fieldClass);
		$view->set('orderinfo',$processed);
		$view->set('item',$orderinfo);
		$view->setLayout('address');
		$view = $this->display();
	}

	/**
	 * Method to save orderinfo
	 */
	function saveOrderinfo(){
		$data = $this->input->getArray($_POST);
		$order_id = $this->input->getString('order_id');
		$order = F0FTable::getAnInstance('Order','J2StoreTable');
		$order->load(array('order_id'=>$order_id));
		$address_type = $this->input->getString('address_type');
		$orderinfo = F0FTable::getAnInstance('Orderinfo','J2StoreTable');
		$orderinfo->load(array('order_id'=>$order_id));

		//$orderinfo->bind($data);
		$msg =JText::_('J2STORE_ORDERINFO_SAVED_SUCCESSFULLY');
		$msgType='message';
		if(!$orderinfo->save($data)){
			$msg =JText::_('J2STORE_ORDERINFO_SAVED_SUCCESSFULLY');
			$msgType='warning';
		}
		$url = "index.php?option=com_j2store&view=orders&task=setOrderinfo&order_id=".$order_id."&address_type=".$address_type."&layout=address&tmpl=component";
		$this->setRedirect($url, $msg,$msgType);

	}

	/**
	 * Method to remove the prefix and return result of address
	 * @param unknown_type $input
	 * @param unknown_type $prefix
	 */
	public function removePrefix($input ,$prefix) {
		$keys = array_keys($input);
		$values =array();
		$return = new JObject();
		foreach($input as $k =>$value){
			if (strpos($k,$prefix.'_') === 0){
				$key =  str_replace($prefix.'_','',$k);
				$return->$key = $value;
			}
		}

		return $return;
	}

	/**
	 * Method to get Countrylist
	 */
	public function getCountry(){
		$app = JFactory::getApplication();
		$country_id = $this->input->getInt('country_id');
		$zone_id = $this->input->getInt('zone_id');
		if($country_id) {
			$zones = F0FModel::getTmpInstance('Zones', 'J2storeModel')->country_id($country_id)->getList();
		}
		$json = array();
		$json['zone'] = $zones ;
		echo json_encode($json);
		$app->close();

	}


	function download() {
		$app = JFactory::getApplication();
		$ftoken = $app->input->getString('ftoken', '');

		if($ftoken) {
			$table = F0FTable::getInstance('Upload', 'J2StoreTable');
			if($table->load(array('mangled_name'=>$ftoken))) {
				$name = $table->original_name;
				$mask = basename($name);
				$file = $table->saved_name;
				jimport('joomla.filesystem.file');
				$path = JPATH_ROOT.'/media/j2store/uploads/'.$file;
				if(JFile::exists($path)) {
					F0FModel::getTmpInstance('Orderdownloads', 'J2StoreModel')->downloadFile($path, $mask);
					$app->close();
				}
			}
		}
	}



	public function printOrder(){
		$app = JFactory::getApplication();
		$order_id = $this->input->getString('order_id');
		$view = $this->getThisView();
		if ($model = $this->getThisModel())
		{
			// Push the model into the view (as default)
			$view->setModel($model, true);
		}
		$order = F0FTable::getInstance('Order' ,'J2StoreTable');
		$order->load(array('order_id' => $order_id));
		$error = false;
		$view->assign('order' ,$order );

		$view->assign('error', $error);
		$view->setLayout('print');
		$view->display();
	}


	public function printShipping(){
		$app = JFactory::getApplication();
		$order_id = $this->input->getString('order_id');
		$view = $this->getThisView();
		if ($model = $this->getThisModel())
		{
			// Push the model into the view (as default)
			$view->setModel($model, true);
		}

		$order = F0FTable::getInstance('Order' ,'J2StoreTable');
		$order->load(array('order_id' => $order_id));

		$orderinfo = F0FTable::getAnInstance('Orderinfo','J2StoreTable');
		$orderinfo->load(array('order_id'=>$order_id));

		$error = false;
		$view->assign('orderinfo' ,$orderinfo );
		$view->assign('item' ,$order );
		$view->assign('params' ,J2Store::config() );
		$view->assign('error', $error);
		$view->setLayout('print_shipping');
		$view->display();
	}
}