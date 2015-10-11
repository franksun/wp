<?php
/**
 * @package J2Store
 * @copyright Copyright (c)2014-17 Ramesh Elamathi / J2Store.org
 * @license GNU GPL v3 or later
 */
// No direct access to this file
defined('_JEXEC') or die;

class J2Invoice {

	public static $instance = null;
	protected $state;

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

	public function loadInvoiceTemplate($order) {

		// Initialise
		$templateText = '';
		$subject = '';
		$loadLanguage = null;
		$isHTML = false;

		// Look for desired languages
		$jLang = JFactory::getLanguage();

		if(JFactory::getUser($order->user_id)->id > 0) {
			$userLang = JFactory::getUser()->getParam('language','');
		} else {
			$userLang = $order->customer_language;
		}
		$languages = array(
				$userLang, $jLang->getTag(), $jLang->getDefault(), 'en-GB', '*'
		);

		//load all templates
		$allTemplates = $this->getInvoiceTemplates($order);

		if(count($allTemplates)){

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
					$templateText = $template->body;

				}
			}
		} else {
			$templateText = JText::_('J2STORE_DEFAULT_INVOICE_TEMPLATE_TEXT');
		}
		return $templateText;
	}

	public function getInvoiceTemplates($order) {


 		$db = JFactory::getDbo();

			$query = $db->getQuery(true)
			->select('*')
			->from('#__j2store_invoicetemplates')
			->where($db->qn('enabled').'='.$db->q(1))
			->where(' CASE WHEN orderstatus_id = '.$order->order_state_id .' THEN orderstatus_id = '.$order->order_state_id .'
							ELSE orderstatus_id ="*" OR orderstatus_id =""
						END
					');
			if(isset($order->customer_group) && !empty($order->customer_group)) {
				$query->where(' CASE WHEN group_id = '.$db->q($order->customer_group).' THEN group_id ='.$db->q($order->customer_group).'
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

	public function	getFormatedInvoice($order){
		$text = $this->loadInvoiceTemplate($order);
		$template =  J2Store::email()->processTags($text, $order, $extras=array());
		return $template;
	}


	/**
	 * Method to generate PDF
	 * @params order object
	 * @param unknown_type $order
	 */
	public function createPdf($order){

		JHtml::_('jquery.framework');
		JHtml::_('bootstrap.framework');
		$app = JFactory::getApplication();
		$name ='invoice-'.$order->order_id;
		
		$template_html = '';
		$template_html .= $this->getFormatedInvoice($order);
		$template_html = $this->processInlineImages($template_html);

		include_once JPATH_LIBRARIES.'/f0f/include.php';

		if(!defined('F0F_INCLUDED'))
		{
			JError::raiseError('500','F0F IS NOT INSTALLED');
		}

		if (function_exists('tidy_repair_string'))
		{
			$tidyConfig = array(
					'bare'							=> 'yes',
					'clean'							=> 'yes',
					'drop-proprietary-attributes'	=> 'yes',
					'clean'							=> 'yes',
					'output-html'					=> 'yes',
					'show-warnings'					=> 'no',
					'ascii-chars'					=> 'no',
					'char-encoding'					=> 'utf8',
					'input-encoding'				=> 'utf8',
					'output-bom'					=> 'no',
					'output-encoding'				=> 'utf8',
					'force-output'					=> 'yes',
					'tidy-mark'						=> 'no',
					'wrap'							=> 0,
			);
			$repaired = tidy_repair_string($template_html, $tidyConfig, 'utf8');
			if ($repaired !== false)
			{
				$template_html = $repaired;
			}
		}
		//echo $template_html; exit;
		// Set up TCPDF
		$jreg = JFactory::getConfig();
		$tmpdir = $jreg->get('tmp_path');
		$tmpdir = rtrim($tmpdir, '/' . DIRECTORY_SEPARATOR) . '/';
		$siteName = $jreg->get('sitename');

		$baseURL = str_replace('/administrator', '', JURI::base());
		//replace administrator string, if present
		$baseurl = ltrim($baseURL, '/');

		define('K_TCPDF_EXTERNAL_CONFIG', 1);
		define ('K_PATH_MAIN', JPATH_BASE . '/');
		define ('K_PATH_URL', $baseurl);
		define ('K_PATH_FONTS', JPATH_SITE.'/libraries/tcpdf/fonts/');
		define ('K_PATH_CACHE', $tmpdir);
		define ('K_PATH_URL_CACHE', $tmpdir);
		define ('PDF_PAGE_FORMAT', 'A4');
		define ('PDF_PAGE_ORIENTATION', 'P');
		define ('PDF_CREATOR', 'J2Store');
		define ('PDF_AUTHOR', $siteName);
		define ('PDF_UNIT', 'mm');
		define ('PDF_MARGIN_HEADER', 5);
		define ('PDF_MARGIN_FOOTER', 10);
		define ('PDF_MARGIN_TOP', 27);
		define ('PDF_MARGIN_BOTTOM', 25);
		define ('PDF_MARGIN_LEFT', 15);
		define ('PDF_MARGIN_RIGHT', 15);
		define ('PDF_FONT_NAME_MAIN', 'helvetica');
		define ('PDF_FONT_SIZE_MAIN', 8);
		define ('PDF_FONT_NAME_DATA', 'helvetica');
		define ('PDF_FONT_SIZE_DATA', 8);
		define ('PDF_FONT_MONOSPACED', 'helvetica');
		define ('PDF_IMAGE_SCALE_RATIO', 1.25);
		define('HEAD_MAGNIFICATION', 1.1);
		define('K_CELL_HEIGHT_RATIO', 1.25);
		define('K_TITLE_MAGNIFICATION', 1.3);
		define('K_SMALL_RATIO', 2/3);
		define('K_THAI_TOPCHARS', true);
		define('K_TCPDF_CALLS_IN_HTML', false);

		require_once(JPATH_SITE.'/libraries/tcpdf/tcpdf.php');
		$tcpdf= new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

		// set document information
		$tcpdf->SetCreator(PDF_CREATOR);
		$tcpdf->SetAuthor(PDF_AUTHOR);
		$tcpdf->SetTitle($order->order_id);

		$tcpdf->SetSubject('Invoices');
		$tcpdf->SetKeywords('invoice, PDF, example, test, guide');
		$tcpdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
		$tcpdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));
		$tcpdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
		$tcpdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
		$tcpdf->SetHeaderMargin(PDF_MARGIN_HEADER);
		$tcpdf->SetFooterMargin(PDF_MARGIN_FOOTER);
		$tcpdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
		$tcpdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
		$tcpdf->setHeaderFont(array('helvetica', '', 8, '', true));
		$tcpdf->setFooterFont(array('helvetica', '', 8, '', true));
		$tcpdf->SetFont('helvetica', '', 8, '', true);

		// remove default header/footer
		$tcpdf->setPrintHeader(false);

		$tcpdf->setPrintFooter(false);
		// add a page
		$tcpdf->AddPage();
		$tcpdf->writeHTML($template_html, true, false, true, false, '');
		$tcpdf->lastPage();
		$pdfData = $tcpdf->Output('', 'S');

		$path = JPATH_SITE .'/media/j2store/invoices';
		if(JFile::exists($path)) {
			JFolder::create($path);
		}
		$ret = JFile::write($path.$name.'.pdf', $pdfData);		
		$tcpdf->Output($name.'.pdf', 'I');
		JFactory::getApplication()->close();
		return;
	}
	
	public function processInlineImages($templateText) {
	
		$baseURL = str_replace('/administrator', '', JURI::base());
		//replace administrator string, if present
		$baseURL = ltrim($baseURL, '/');
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
							//$mailer->AddEmbeddedImage($url, 'img'.$imgidx, basename($url));
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
		return $templateText;
	}

}