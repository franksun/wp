<?php
/**
 * @package J2Store
 * @copyright Copyright (c)2014-17 Ramesh Elamathi / J2Store.org
 * @license GNU GPL v3 or later
 */
// No direct access to this file
defined('_JEXEC') or die;
class J2StoreControllerMyProfile extends F0FController
{

	public function execute($task) {
		if(in_array($task, array('add', 'edit', 'read'))) {
			$task = 'browse';
		}
		parent::execute($task);
	}

	public function display($cachable = false, $url = false) {

		$app = JFactory::getApplication();
		$session = JFactory::getSession();
		$user = JFactory::getUser();
		$document = F0FPlatform::getInstance()->getDocument();
		$params = J2Store::config();
		if ($document instanceof JDocument)
		{
			$viewType = $document->getType();
		}
		else
		{
			$viewType = $this->input->getCmd('format', 'html');
		}

		$view = $this->getThisView();

		// Get/Create the model

		if ($model = $this->getThisModel())
		{
			// Push the model into the view (as default)
			$view->setModel($model, true);
		}

		// Set the layout
		$view->setLayout(is_null($this->layout) ? 'default' : $this->layout);

		// Load the model
		$order_model = F0FModel::getTmpInstance('Orders', 'J2StoreModel');
		$limit_orderstatuses = $params->get('limit_orderstatuses', '*');

		$guest_token = $session->get('guest_order_token', '', 'j2store');
		$guest_order_email = $session->get('guest_order_email', '', 'j2store');
		$orders = array();
		if (empty($user->id) && (empty($guest_token) || empty($guest_order_email)) )
		{
			$view->setLayout('default_login');
		} elseif ($user->id)  {
			if(isset($guest_token)) {
				$session->clear('guest_order_token', 'j2store');
			}
			// Assign data to the view
			$order_model->clearState()->clearInput();			
			$order_model->setState('filter_order', 'created_on');
			$order_model->setState('filter_order_Dir', 'DESC');
			$orders = $order_model->user_id($user->id)->orderstatuses($limit_orderstatuses)->getItemList();
			$view->assign('orders', $orders);
			$view->assign('beforedisplayprofile' , J2Store::plugin()->eventWithHtml('BeforeDisplayMyProfile',$orders));
			$orderinfos = F0FModel::getTmpInstance('Myprofiles','J2StoreModel')->getAddress();
			$view->assign('orderinfos',$orderinfos);
			$view->assign('fieldClass',J2Store::getSelectableBase());
			$view->assign('guest', false);
			if($this->getTask()!='editAddress'){
				$view->setLayout('default');
			}
			// if its guest
		} elseif ($guest_token && $guest_order_email) {

			$order_model->clearState()->clearInput();
			$order_model->setState('filter_order', 'created_on');
			$order_model->setState('filter_order_Dir', 'DESC');
			$orders = $order_model->token($guest_token)->user_email($guest_order_email)->orderstatuses($limit_orderstatuses)->getItemList();
			$view->assign('guest', true);
			if($this->getTask()!='editAddress'){
				$view->setLayout('default');
			}
		}

 		//trigger after display order event
            foreach($orders as $order){
                $result='';
                // results as html
                $result = J2Store::plugin()->eventWithHtml('AfterDisplayOrder', array( $order ) );
                if(!empty($result)){
                    $order->after_display_order = $result;
                }
                    
            }

        $view->assign('orders', $orders);

		$view->assign('params', $params);
		$view->assign('user', $user);
		$view->display();

	}

	public function editAddress(){

		$address_id = $this->input->getInt('address_id');
		$model = $this->getModel('Myprofile' ,'J2StoreModel');
		$view = $this->getThisView();
		//$this->storeProfile
		$address = F0FTable::getAnInstance('Address' ,'J2StoreTable');
		$address->load($address_id);
		$view->setModel($model,true);
		$view->assign('address' ,$address );
		$fieldClass  = J2Store::getSelectableBase();
		$view->assign('fieldClass' , $fieldClass);
		$view->setLayout('address');
		$view = $view->display();
		
	}


	function deleteAddress(){
		$o_id = $this->input->getInt('address_id');
		$table = F0FTable::getAnInstance('Address','J2StoreTable');
		$url =JRoute::_('index.php?option=com_j2store&view=myprofile');
		$msg  = JText::_('J2STORE_MYPROFILE_ADDRESS_DELETED_SUCCESSFULLY');
		$msgType = 'success';
		if($table->load($o_id)){
			if(!$table->delete($o_id)){
				$msg  = JText::_('J2STORE_MYPROFILE_ADDRESS_DELETE_ERROR');
				$msgType = 'warning';
			}
		}
		$this->setRedirect($url,$msg,$msgType);
	}

	/**
	 * Method to save Address
	 * edit / new address will be saved
	 * @param post data
	 * @return result
	 */
	function saveAddress(){
		$app = JFactory::getApplication();
		$user = JFactory::getUser();
		$values = $app->input->getArray($_POST);
		$values['id'] = $values['address_id'];
		$values['user_id'] = (isset($values['user_id'])  && $values['user_id']) ? $values['user_id'] : $user->id;
		$values['email'] = $user->email;
		$model = $this->getModel('myprofile');
		$selectableBase = J2Store::getSelectableBase();
		$json = $selectableBase->validate($values, $values['type'], 'address');

		if(empty($json['error'])){
			$table = F0FTable::getAnInstance('Address','J2StoreTable');
			$values['user_id'] = isset($values['user_id']) ? $values['user_id'] : $user->id;
			$values['email'] = isset($values['user_id']) ? $values['email'] : $user->email;
			if($table->save($values)){
				$json['success']['url'] = JRoute::_('index.php?option=com_j2store&view=myprofile&task=editAddress&layout=address&tmpl=component&address_id='.$table->j2store_address_id);
				$json['success']['msg'] = JText::_('J2STORE_'.strtoupper($table->type).'_ADDRESS_SAVED_SUCCESSFULLY');
				$json['success']['msgType']='success';
			}else{
				$json['error']['msgType']='Warning';
			}
		}
		echo json_encode($json);
		$app->close();
	}
	public  function vieworder(){

		$app = JFactory::getApplication();
		$order_id = $this->input->getString('order_id');
		$view = $this->getThisView();

		if ($model = $this->getThisModel())
		{
			// Push the model into the view (as default)
			$view->setModel($model, true);
		}
		$order = F0FTable::getInstance('Order' ,'J2StoreTable')->getClone();
		$order->load(array('order_id' => $order_id));

		if($this->validate($order)) {
			$error = false;
			$view->assign('order' ,$order );
		}else {
			$msg = JText::_('J2STORE_ORDER_MISMATCH_OR_NOT_FOUND');
			$error = true;
			$view->assign('errormsg' , $msg);
		}
		$view->assign('error', $error);
		$view->setLayout('view');
		$view->display();
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
		$order = F0FTable::getInstance('Order' ,'J2StoreTable')->getClone();
		$order->load(array('order_id' => $order_id));
		if($this->validate($order)) {
			$error = false;
			$view->assign('order' ,$order );
		}else {
			$msg = JText::_('J2STORE_ORDER_MISMATCH_OR_NOT_FOUND');
			$error = true;
			$view->assign('errormsg' , $msg);
		}
		$view->assign('error', $error);
		$view->setLayout('view');
		$view->display();
	}

	 public function createOrderPdf(){
		$app = JFactory::getApplication();
		$order_id = $this->input->getString('order_id');
		$view = $this->getThisView();

		if ($model = $this->getThisModel())
		{
			// Push the model into the view (as default)
			$view->setModel($model, true);
		}
		$order = F0FTable::getInstance('Order' ,'J2StoreTable')->getClone();
		$order->load(array('order_id' => $order_id));
		if($this->validate($order)) {
			$error = false;
			$view->assign('order' ,$order );
		}else {
			$msg = JText::_('J2STORE_ORDER_MISMATCH_OR_NOT_FOUND');
			$msg_type = 'warning';
			$error = true;
			$view->assign('errormsg' , $msg);
		}

		if(!$error) {
			$msg_type ='success';
			$name ='j2store_invoice_'.$order->order_id;
			$msg =JText::_('J2STORE_INVOICE_PDF_GENERATED_SUCCESSFULLY');
			if(!J2Store::invoice()->createPdf($order)){
				$msg_type ='warning';
				$msg = JText::_('J2STORE_INVOICE_PDF_GENERATED_ERROR');
			}
		}
		$this->setRedirect(JRoute::_('index.php?option=com_j2store&view=myprofile') , $msg , $msg_type);
	}

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

	public function validate($order) {

		$app = JFactory::getApplication();
		$user = JFactory::getUser();
		$session = JFactory::getSession();
		$guest_token = $session->get('guest_order_token', '', 'j2store');
		$guest_order_email = $session->get('guest_order_email', '', 'j2store');

		$status = false;

		if(!isset($order->order_id) || empty($order->order_id) || $order->order_id == 0) {
			return $status;
		}

		if ($user->id)  {
			if($order->user_id == $user->id) {
				$status = true;
			}
			// if its guest
		} elseif($guest_token && $guest_order_email) {
			if(($order->user_email == $guest_order_email) && ($order->token == $guest_token)) {
				$status = true;
			}
		}
		return $status;
	}

	function guestentry() {

		//check token
		JRequest::checkToken() or jexit('Invalid Token');
		$app = JFactory::getApplication();
		$post = $app->input->getArray($_REQUEST);
		$email = $this->input->getString('email', '');
		$token = $this->input->getString('order_token', '');
		if(empty($email) || empty($token)) {
			$link = JRoute::_('index.php?option=com_j2store&view=myprofile');
			$msg = JText::_('J2STORE_ORDERS_GUEST_VALUES_REQUIRED');
			$app->redirect($link, $msg);
		}

		//checks
		if(filter_var($email, FILTER_VALIDATE_EMAIL) !== false) {
			$session = JFactory::getSession();
			$session->set('guest_order_email', $email, 'j2store');

		} else {

			$link = JRoute::_('index.php?option=com_j2store&view=myprofile');
			$msg = JText::_('J2STORE_ORDERS_GUEST_INVALID_EMAIL');
			$app->redirect($link, $msg);
		}

		if(F0FTable::getInstance('Order', 'J2StoreTable')->load(array('token'=>$token, 'user_email'=>$email))) {
			$session->set('guest_order_token', $token, 'j2store');
		} else {
			$link = JRoute::_('index.php?option=com_j2store&view=myprofile');
			$msg = JText::_('J2STORE_ORDERS_GUEST_INVALID_TOKEN');
			$app->redirect($link, $msg);
		}

		$url = JRoute::_('index.php?option=com_j2store&view=myprofile');
		$this->setRedirect($url);
		return;
	}

	function download() {
		$model = F0FModel::getTmpInstance('Orderdownloads', 'J2StoreModel');
		if($model->getDownloads() === false ) {
			$msg = $model->getError();
			$url = JRoute::_('index.php?option=com_j2store&view=myprofile');
			$this->setRedirect($url, $msg, 'warning');
		}
	}

}
