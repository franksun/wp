<?php
/**
 * @package J2Store
 * @copyright Copyright (c)2014-17 Ramesh Elamathi / J2Store.org
 * @license GNU GPL v3 or later
 */
// No direct access to this file
defined('_JEXEC') or die;
jimport('joomla.mail.helper');
class J2StoreControllerCustomers extends F0FController
{

	public function __construct($config =array())
	{

		parent::__construct($config);
		$this->registerTask('confirmchangeEmail','changeEmail');
	}
	/**
	 *
	 * @return boolean
	 */
	function viewOrder(){
		$email  = $this->input->getString('email_id');
		$user_id = $this->input->getInt('user_id');
		$this->layout='view';
		$this->display();
		return true;
	}

	/**
	 * Method to delete customer
	 */
	function delete()
	{
		// Initialise the App variables
		$app=JFactory::getApplication();
		// Assign the get Id to the Variable
		$id=$app->input->getInt('id');

		if($id && $app->isAdmin())
		{	// store the table in the variable
			$address = F0FTable::getInstance('Address', 'J2StoreTable');
			$address->load($id);
			$email = $address->email;
			try {
				$address->delete();
				$msg = JText::_('J2STORE_ITEMS_DELETED');
			} catch (Exception $error) {
				$msg = $error->getMessage();
			}
		}

		$link = 'index.php?option=com_j2store&view=customer&task=viewOrder&email_id='.$email;
		$this->setRedirect($link, $msg);

	}

	function changeEmail(){
		// Initialise the App variables
		$app=JFactory::getApplication();
		if($app->isAdmin()){
			$json = array();
			$model = $this->getThisModel();
			// Assign the get Id to the Variable
			$email_id=$app->input->getString('email');
			$new_email=$app->input->getString('new_email');

			if(empty($new_email) && !JMailHelper::isEmailAddress($new_email) ){
				$json = array('msg' => JText::_('Invalid Email Address'), 'msgType' => 'warning');
			}else{
				//incase an account already exists ?
				if($app->input->getString('task') == 'changeEmail'){

					$json = array('msg' => JText::_('J2STORE_EMAIL_UPDATE_NO_WARNING'), 'msgType' => 'message');
					$json = $this->validateEmailexists($new_email);

				}elseif($app->input->getString('task') == 'confirmchangeEmail'){

					$json = array( 'redirect' => JUri::base().'index.php?option=com_j2store&view=customer&task=viewOrder&email_id='.$new_email, 'msg' => JText::_('J2STORE_SUCCESS_SAVING_EMAIL'), 'msgType' => 'message');
					if(!$model->savenewEmail()){
						$json = array('msg' => JText::_('J2STORE_ERROR_SAVING_EMAIL'), 'msgType' => 'warning' );
					}
				}

			}
			echo json_encode($json);
			$app->close();
		}
	}

	function validateEmailexists($new_email){
		$json = array();
		$success = true;
		$model = $this->getThisModel();

		if(J2Store::user()->emailExists($new_email)){
			$success = false;
			$json = array('msg' => JText::_('J2STORE_EMAIL_UPDATE_ERROR_WARNING'), 'msgType' => 'warning');
		}

		if($success){
			$json = array( 'redirect' => JUri::base().'index.php?option=com_j2store&view=customer&task=viewOrder&email_id='.$new_email, 'msg' => JText::_('J2STORE_SUCCESS_SAVING_EMAIL'), 'msgType' => 'message');
			if(!$model->savenewEmail()){
				$json = array('msg' => JText::_('J2STORE_ERROR_SAVING_EMAIL'), 'msgType' => 'warning' );
			}
		}
		return $json;
	}
}