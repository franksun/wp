<?php
/*------------------------------------------------------------------------
# com_j2store - J2Store
# ------------------------------------------------------------------------
# author    Ramesh Elamathi - Weblogicx India http://www.weblogicxindia.com
# copyright Copyright (C) 2014 - 19 Weblogicxindia.com. All Rights Reserved.
# @license - http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
# Websites: http://j2store.org
# Technical Support:  Forum - http://j2store.org/forum/index.html
-------------------------------------------------------------------------*/

// no direct access
defined('_JEXEC') or die('Restricted access');

class J2Email {

	public static $instance = null;
	protected $state;
	var $is_template_file = false;

	public function __construct($properties=null) {

	}

	public static function getInstance(array $config = array())
	{
		if (!self::$instance)
		{
			self::$instance = new self($config);
		}

		return self::$instance;
	}

	protected function loadEmailTemplate($order) {

		// Initialise
		$templateText = '';
		$subject = '';
		$loadLanguage = null;
		$isHTML = false;

		// Look for desired languages
		$jLang = JFactory::getLanguage();

		$userLang = $order->customer_language;
		$languages = array(
				$userLang, $jLang->getTag(), $jLang->getDefault(), 'en-GB', '*'
		);

		//load all templates
		$allTemplates = $this->getEmailTemplates($order);

		if(count($allTemplates) && J2Store::isPro() == 1)
		{
			// Pass 1 - Give match scores to each template
			$preferredIndex = null;
			$preferredScore = 0;

			foreach($allTemplates as $idx => $template)
			{
				// Get the language and level of this template
				$myLang = $template->language;

				// Make sure the language matches one of our desired languages, otherwise skip it
				$langPos = array_search($myLang, $languages);
				if ($langPos === false)
				{
					continue;
				}
				$langScore = (5 - $langPos);


				// Calculate the score
				$score = $langScore;
				if ($score > $preferredScore)
				{
					$loadLanguage = $myLang;
					$subject = $template->subject;
					
					if(isset($template->body_source) && $template->body_source == 'file') {
						$templateText = $this->getTemplateFromFile($template, $order);
						$this->is_template_file = true;
						
					}else {
						$templateText = $template->body;
					}
					
					$preferredScore = $score;

					$isHTML = true;
				}
			}
		} else {

			$isHTML = true;
			$templateText = JText::_('J2STORE_ORDER_EMAIL_TEMPLATE_STANDARD_BODY');
			$subject = JText::_('J2STORE_ORDER_EMAIL_TEMPLATE_STANDARD_SUBJECT');
		}
		return array($isHTML, $subject, $templateText, $loadLanguage);
	}

	/**
	 * Method to process tags
	 *
	 * @param string $text Text to process
	 * @param object $order TableOrder object
	 * @param array $extras an array containing extra tags to process
	 */

	public function processTags($text, $order, $extras=array()) {

		$app = JFactory::getApplication();
		$params = J2Store::config();
		$currency = J2Store::currency();
		$order_model = F0FModel::getTmpInstance('Orders', 'J2StoreModel');

		// -- Get the site name
		$config = JFactory::getConfig();
		if(version_compare(JVERSION, '3.0', 'ge')) {
			$sitename = $config->get('sitename');
		} else {
			$sitename = $config->getValue('config.sitename');
		}

		//site url
		$baseURL = JURI::base();
		$subpathURL = JURI::base(true);
		//replace administrator string, if present
		$baseURL = str_replace('/administrator', '', $baseURL);
		$subpathURL = str_replace('/administrator', '', $subpathURL);

		//invoice url
		$url = str_replace('&amp;','&', JRoute::_('index.php?option=com_j2store&view=myprofile'));

		$url = str_replace('/administrator', '', $url);
		$url = ltrim($url, '/');
		$subpathURL = ltrim($subpathURL, '/');
		if(substr($url,0,strlen($subpathURL)+1) == "$subpathURL/") $url = substr($url,strlen($subpathURL)+1);
		$invoiceURL = rtrim($baseURL,'/').'/'.ltrim($url,'/');

		//order date
		//$order_date = JHTML::_('date', $order->created_on, $params->get('date_format', JText::_('DATE_FORMAT_LC1')));
		$tz = JFactory::getConfig()->get('offset');
		$date = JFactory::getDate($order->created_on, $tz);
		$order_date = $date->format($params->get('date_format', JText::_('DATE_FORMAT_LC1')), true);


		//items table
		$items = $order_model->loadItemsTemplate($order);
		$invoice_number = $order->getInvoiceNumber();
		//now process tags
		$orderinfo = $order->getOrderInformation();
		$shipping = $order->getOrderShippingRate();
		$ordercoupon = $order->getOrderCoupons();
		$status = F0FModel::getTmpInstance('Orderstatuses', 'J2StoreModel')->getItem($order->order_state_id);
		$coupon_code = '';
		if($ordercoupon) {
			$coupon_code = $ordercoupon[0]->coupon_code;
		}
		$orderinfo->billing_country_name = F0FModel::getTmpInstance('Countries','J2StoreModel')->getItem($orderinfo->billing_country_id)->country_name;
		$orderinfo->shipping_country_name = F0FModel::getTmpInstance('Countries','J2StoreModel')->getItem($orderinfo->shipping_country_id)->country_name;
		$orderinfo->billing_zone_name = F0FModel::getTmpInstance('Zones','J2StoreModel')->getItem($orderinfo->billing_zone_id)->zone_name;
		$orderinfo->shipping_zone_name = F0FModel::getTmpInstance('Zones','J2StoreModel')->getItem($orderinfo->shipping_zone_id)->zone_name;
		$tags = array(
				"\\n"					=> "\n",
				'[SITENAME]'			=> $sitename,
				'[SITEURL]'				=> $baseURL,
				'[INVOICE_URL]'				=> $invoiceURL,
				'[ORDERID]'				=> $order->order_id,

				'[INVOICENO]'			=> $invoice_number,
				'[ORDERDATE]'			=> $order_date,
				'[ORDERSTATUS]'			=> JText::_($status->orderstatus_name),
				'[ORDERAMOUNT]'			=> $currency->format($order->get_formatted_grandtotal(), $order->currency_code, $order->currency_value ),

				'[CUSTOMER_NAME]'		=> $orderinfo->billing_first_name.' '.$orderinfo->billing_last_name,
				'[BILLING_FIRSTNAME]'	=> $orderinfo->billing_first_name,
				'[BILLING_LASTNAME]'	=> $orderinfo->billing_last_name,
				'[BILLING_EMAIL]'		=> $order->user_email,
				'[BILLING_ADDRESS_1]'	=> $orderinfo->billing_address_1,
				'[BILLING_ADDRESS_2]'	=> $orderinfo->billing_address_2,
				'[BILLING_CITY]'		=> $orderinfo->billing_city,
				'[BILLING_ZIP]'			=> $orderinfo->billing_zip,
				'[BILLING_COUNTRY]'		=> $orderinfo->billing_country_name,
				'[BILLING_STATE]'		=> $orderinfo->billing_zone_name,
				'[BILLING_COMPANY]'		=> $orderinfo->billing_company,
				'[BILLING_VATID]'		=> $orderinfo->billing_tax_number,
				'[BILLING_PHONE]'		=> $orderinfo->billing_phone_1,
				'[BILLING_MOBILE]'		=> $orderinfo->billing_phone_2,

				'[SHIPPING_FIRSTNAME]'	=> $orderinfo->shipping_first_name,
				'[SHIPPING_LASTNAME]'	=> $orderinfo->shipping_last_name,
				'[SHIPPING_ADDRESS_1]'	=> $orderinfo->shipping_address_1,
				'[SHIPPING_ADDRESS_2]'	=> $orderinfo->shipping_address_2,
				'[SHIPPING_CITY]'		=> $orderinfo->shipping_city,
				'[SHIPPING_ZIP]'		=> $orderinfo->shipping_zip,
				'[SHIPPING_COUNTRY]'	=> $orderinfo->shipping_country_name,
				'[SHIPPING_STATE]'		=> $orderinfo->shipping_zone_name,
				'[SHIPPING_COMPANY]'	=> $orderinfo->shipping_company,
				'[SHIPPING_VATID]'		=> $orderinfo->shipping_tax_number,
				'[SHIPPING_PHONE]'		=> $orderinfo->shipping_phone_1,
				'[SHIPPING_MOBILE]'		=> $orderinfo->shipping_phone_2,

				'[SHIPPING_METHOD]'		=> JText::_($shipping->ordershipping_name),
				'[SHIPPING_TYPE]'		=> JText::_($shipping->ordershipping_name),
				'[SHIPPING_TRACKING_ID]'	=> $shipping->ordershipping_tracking_id,

				'[CUSTOMER_NOTE]'		=> $order->customer_note,
				'[PAYMENT_TYPE]'		=> JText::_($order->orderpayment_type),
				'[ORDER_TOKEN]'			=> $order->token,
				'[TOKEN]'				=> $order->token,
				'[COUPON_CODE]'			=> $coupon_code,


				'[ITEMS]'				=> $items

		);
		
		$tags = array_merge($tags, $extras);
		foreach ($tags as $key => $value)
		{
			$text = str_replace($key, $value, $text);
		}
		//process custom fields.
		//billing Format [CUSTOM_BILLING_FIELD:KEYNAME]
		$text = $this->processCustomFields($orderinfo, 'billing', $text);
		//shipping Format [CUSTOM_SHIPPING_FIELD:KEYNAME]
		$text = $this->processCustomFields($orderinfo, 'shipping', $text);

		//payment Format [CUSTOM_PAYMENT_FIELD:KEYNAME]
		$text = $this->processCustomFields($orderinfo, 'payment', $text);

		//now we have unprocessed fields. remove any other square brackets found.
		preg_match_all("^\[(.*?)\]^",$text,$removeFields, PREG_PATTERN_ORDER);
		if(count($removeFields[1])) {
			foreach($removeFields[1] as $fieldName) {
				$text= str_replace('['.$fieldName.']', '', $text);
			}
		}
		J2Store::plugin()->event('AfterProcessTags', array($text, $order, $tags));
		return $text;

	}

	private function getDecodedFields($json) {
		$result = array();
		if(!empty($json)) {
			$registry = new JRegistry();
			$registry->loadString(stripslashes($json), 'JSON');
			$result = $registry->toArray();
		}
		return $result;
	}

	private function processCustomFields($row, $type, $text) {
		if ($type == 'billing') {
			$field = 'all_billing';
		} elseif ($type == 'shipping') {
			$field = 'all_shipping';
		} elseif ($type == 'payment') {
			$field = 'all_payment';
		}
		
		$fields = array ();
		if (! empty ( $row->$field ) && JString::strlen ( $row->$field ) > 0) {
			
			$custom_fields = $this->getDecodedFields ( $row->$field );
			if (isset ( $custom_fields ) && count ( $custom_fields )) {
				foreach ( $custom_fields as $namekey => $field ) {
					if (! property_exists ( $row, $type . '_' . $namekey ) && ! property_exists ( $row, 'user_' . $namekey ) && $namekey != 'country_id' && $namekey != 'zone_id' && $namekey != 'option' && $namekey != 'task' && $namekey != 'view') {
						$fields [$namekey] = $field;
					}
				}
			}
		}
		
		if (isset ( $fields ) && count ( $fields )) {
			foreach ( $fields as $namekey => $field ) {
				
				$string = '';
				if (is_array ( $field ['value'] )) {
					foreach ( $field ['value'] as $value ) {
						$string .= '-' . JText::_ ( $value ) . '\n';
					}
				} elseif (is_object ( $field ['value'] )) {
					// convert the object into an array
					$obj_array = JArrayHelper::fromObject ( $field ['value'] );
					$string .= '\n';
					foreach ( $obj_array as $value ) {
						$string .= '- ' . JText::_ ( $value ) . '\n';
					}
				} elseif (is_string ( $field ['value'] ) && J2store::utilities ()->isJson ( stripslashes ( $field ['value'] ) )) {
					$json_values = json_decode ( stripslashes ( $field ['value'] ) );
					if (is_array ( $json_values )) {
						foreach ( $json_values as $value ) {
							$string .= '-' . JText::_ ( $value ) . '\n';
						}
					} else {
						$string .= JText::_ ( $field ['value'] );
					}
				} else {
					$string = JText::_ ( $field ['value'] );
				}
				
				$value = JText::_ ( $field ['label'] ) . ' : ' . $string;
				
				$tag_value = '[CUSTOM_' . strtoupper ( $type ) . '_FIELD:' . strtoupper ( $namekey ) . ']';
				
				$text = str_replace ( $tag_value, $value, $text );
			}
		}
		
		return $text;
	}

	public function getEmailTemplates($order) {
 		$db = JFactory::getDbo();

			$query = $db->getQuery(true)
			->select('*')
			->from('#__j2store_emailtemplates')
			->where($db->qn('enabled').'='.$db->q(1))
			->where(' CASE WHEN orderstatus_id = '.$order->order_state_id .' THEN orderstatus_id = '.$order->order_state_id .'
							ELSE orderstatus_id ="*" OR orderstatus_id =""
						END
					');
			if(isset($order->customer_group) && !empty($order->customer_group)) {
				$query->where(' CASE WHEN group_id IN( '.$order->customer_group.') THEN group_id IN('.$order->customer_group.')
									ELSE group_id ="*" OR group_id =""
								END
					');

			}
			$query->where(' CASE WHEN paymentmethod ='.$db->q($order->orderpayment_type).' THEN paymentmethod ='.$db->q($order->orderpayment_type).'
							ELSE paymentmethod="*" OR paymentmethod=""
						END
					');

			$db->setQuery($query);
			try {
				$allTemplates = $db->loadObjectList();
			} catch (Exception $e) {
				$allTemplates = array();
			}
		return $allTemplates;
	}

	/**
	 * Creates a PHPMailer instance
	 *
	 * @param   boolean  $isHTML
	 *
	 * @return  PHPMailer  A mailer instance
	 */
	private static function &getMailer($isHTML = true)
	{
		$mailer = clone JFactory::getMailer();

		$mailer->IsHTML($isHTML);
		// Required in order not to get broken characters
		$mailer->CharSet = 'UTF-8';

		return $mailer;
	}

	private function initMailer() {
		$app =JFactory::getApplication();
		$config = JFactory::getConfig();
		$params     = J2Store::config();

		$mailer = $this->getMailer();
		if(version_compare(JVERSION, '3.0', 'ge')) {
			$mailfrom = $config->get('mailfrom');
			$fromname = $config->get('fromname');
		} else {
			$mailfrom = $config->getValue('config.mailfrom');
			$fromname = $config->getValue('config.fromname');
		}
		$mailer->setSender(array( $mailfrom, $fromname ));

		return $mailer;
	}


	/**
	 * Method to get the pre-loaded mailer function
	 *
	 * @param object $order
	 * @return PHPMailer  A mailer instance
	 */

	public function getEmail($order) {

		if(!isset($order->order_id) || empty($order->order_id)) return false;

		list($isHTML, $subject, $templateText, $loadLanguage) = $this->loadEmailTemplate($order);
		
		//load language overrides
		$this->loadLanguageOverrides($order);

		$extras= array();
		$templateText = $this->processTags($templateText, $order, $extras);
		
		$subject = $this->processTags($subject, $order, $extras);

		$baseURL = str_replace('/administrator', '', JURI::base());
		//replace administrator string, if present
		$baseURL = ltrim($baseURL, '/');

		// Get the mailer
		$mailer = $this->getMailer($isHTML);
		$mailer->setSubject($subject);

		// Include inline images
		$pattern = '/(src)=\"([^"]*)\"/i';
		$number_of_matches = preg_match_all($pattern, $templateText, $matches, PREG_OFFSET_CAPTURE);
		if($number_of_matches > 0) {
			$substitutions = $matches[2];
			$last_position = 0;
			$temp = '';

			// Loop all URLs
			$imgidx = 0;
			$imageSubs = array();
			foreach($substitutions as &$entry)
			{
				// Copy unchanged part, if it exists
				if($entry[1] > 0)
					$temp .= substr($templateText, $last_position, $entry[1]-$last_position);
				// Examine the current URL
					 $url = $entry[0];
				if( (substr($url,0,7) == 'http://') || (substr($url,0,8) == 'https://') ) {
					// External link, skip
					$temp .= $url;
				} else {
					 $ext = strtolower(JFile::getExt($url));
					if(!JFile::exists($url)) {
						// Relative path, make absolute
						$url = $baseURL.ltrim($url,'/');
					}
					if( !JFile::exists($url) || !in_array($ext, array('jpg','png','gif')) ) {
						// Not an image or inexistent file
						$temp .= $url;
					} else {
						// Image found, substitute
						if(!array_key_exists($url, $imageSubs)) {
							// First time I see this image, add as embedded image and push to
							// $imageSubs array.
							$imgidx++;
							$mailer->AddEmbeddedImage($url, 'img'.$imgidx, basename($url));
							$imageSubs[$url] = $imgidx;
						}
						// Do the substitution of the image
						$temp .= 'cid:img'.$imageSubs[$url];
					}
				}

				// Calculate next starting offset
				$last_position = $entry[1] + strlen($entry[0]);
			}
			// Do we have any remaining part of the string we have to copy?
			if($last_position < strlen($templateText))
				$temp .= substr($templateText, $last_position);
			// Replace content with the processed one
			$templateText = $temp;

		}
		
	
		
		$htmlExtra = '';
		$lang = JFactory::getLanguage();
		if($lang->isRTL()) {
			$htmlExtra = ' dir="rtl"';
		}
		$body = '<html'.$htmlExtra.'><head>'.
				'<meta http-equiv="Content-Type" content="text/html; charset='.$mailer->CharSet.'">
				<meta name="viewport" content="width=device-width, initial-scale=1.0">
				</head>';
		//echo $body.$templateText; exit;
		$mailer->setBody($body.$templateText);				
		return $mailer;
	}
	
	public function processInlineImages($templateText, &$mailer) {
		
		// Include inline images
		$pattern = '/(src)=\"([^"]*)\"/i';
		$number_of_matches = preg_match_all($pattern, $templateText, $matches, PREG_OFFSET_CAPTURE);
		if($number_of_matches > 0) {
			
			$baseURL = str_replace('/administrator', '', JURI::base());
			//replace administrator string, if present
			$baseURL = ltrim($baseURL, '/');
			
			$substitutions = $matches[2];
			$last_position = 0;
			$temp = '';
		
			// Loop all URLs
			$imgidx = 0;
			$imageSubs = array();
			foreach($substitutions as &$entry)
			{
				// Copy unchanged part, if it exists
				if($entry[1] > 0)
					$temp .= substr($templateText, $last_position, $entry[1]-$last_position);
				// Examine the current URL
				$url = $entry[0];
				if( (substr($url,0,7) == 'http://') || (substr($url,0,8) == 'https://') ) {
					// External link, skip
					$temp .= $url;
				} else {
					$ext = strtolower(JFile::getExt($url));
					if(!JFile::exists($url)) {
						// Relative path, make absolute
						$url = $baseURL.ltrim($url,'/');
					}
					if( !JFile::exists($url) || !in_array($ext, array('jpg','png','gif')) ) {
						// Not an image or inexistent file
						$temp .= $url;
					} else {
						// Image found, substitute
						if(!array_key_exists($url, $imageSubs)) {
							// First time I see this image, add as embedded image and push to
							// $imageSubs array.
							$imgidx++;
							$mailer->AddEmbeddedImage($url, 'img'.$imgidx, basename($url));
							$imageSubs[$url] = $imgidx;
						}
						// Do the substitution of the image
						$temp .= 'cid:img'.$imageSubs[$url];
					}
				}
		
				// Calculate next starting offset
				$last_position = $entry[1] + strlen($entry[0]);
			}
			// Do we have any remaining part of the string we have to copy?
			if($last_position < strlen($templateText))
				$temp .= substr($templateText, $last_position);
			// Replace content with the processed one
			$templateText = $temp;
		
		}
		
	}


	/**
	 * Sends error messages to site administrators
	 *
	 * @param string $message
	 * @param string $paymentData
	 * @return boolean
	 * @access protected
	 */
	public function sendErrorEmails($receiver, $subject, $body,  $cc = null, $bcc = null)
	{
		if(!isset($receiver)) return false;

		$mainframe = JFactory::getApplication();
		$config = JFactory::getConfig();

			$mailer = $this->initMailer();
			$mailer->addRecipient($receiver);
			$mailer->setSubject($subject);
			$mailer->setBody($body);
			$mailer->addCC($cc);
			$mailer->addCC($bcc);
		return $mailer->Send();
	}
	
	public function getTemplateFromFile($template, $order) {
		
		//sanity check
		if(isset($template->body_source) && $template->body_source == 'file') {
			
			if(empty($template->body_source_file)) return $template->body;

			//we have the file name
			jimport('joomla.filesystem.file');
				
			$app = JFactory::getApplication();
			$fileName = $template->body_source_file;
				
			$filePath = JPath::clean ( JPATH_ADMINISTRATOR.'/components/com_j2store/views/emailtemplate/tpls/'.$fileName);
			
			//file exists
			if (!file_exists ( $filePath )) {
				return $template->body;
			}

			// Try to make the template file writable.
			$user = get_current_user();
			chown($filePath, $user);
			JPath::setPermissions($filePath, '0644');
			
			if (!is_readable($filePath)) {
				return $template->body;
			}
			//the file is readable. get the contents
			$templateText = $this->_getLayout($filePath, $order);
			return $templateText;
		}
		
		return $template->body;
	}
	
	/**
	 * Gets the parsed layout file
	 *
	 * @param string $layout The name of  the layout file
	 * @param object $vars Variables to assign to
	 * @param string $plugin The name of the plugin
	 * @param string $group The plugin's group
	 * @return string
	 * @access protected
	 */
	function _getLayout($layout, $order)
	{
		ob_start();
		$this->loadLanguageOverrides($order);	
		include($layout);
		$html = ob_get_contents();
		ob_end_clean();
	
		return $html;
	}
	
	function loadLanguageOverrides($order) {
	
		$extension = 'com_j2store';
		$jlang = JFactory::getLanguage();
		// -- English (default fallback)
		$jlang->load($extension, JPATH_ADMINISTRATOR, 'en-GB', true);
		$jlang->load($extension.'.override', JPATH_ADMINISTRATOR, 'en-GB', true);
		// -- Default site language
		$jlang->load($extension, JPATH_ADMINISTRATOR, $jlang->getDefault(), true);
		$jlang->load($extension.'.override', JPATH_ADMINISTRATOR, $jlang->getDefault(), true);
		// -- Current site language
		$jlang->load($extension, JPATH_ADMINISTRATOR, null, true);
		$jlang->load($extension.'.override', JPATH_ADMINISTRATOR, null, true);
	
		$jlang->load($extension, JPATH_ADMINISTRATOR, $order->customer_language, true);
		$jlang->load($extension.'.override', JPATH_ADMINISTRATOR, $order->customer_language, true);
	
	}
	
}