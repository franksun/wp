<?php
/**
 * @package J2Store
 * @copyright Copyright (c)2014-17 Ramesh Elamathi / J2Store.org
 * @license GNU GPL v3 or later
 */
// No direct access to this file
defined('_JEXEC') or die;
/* class JFormFieldFieldtypes extends JFormField */
class JFormFieldVersionDownload extends F0FFormFieldText
{
	/**
	 * The field type.
	 *
	 * @var		string
	 */
	protected $type = 'VersionDownload';

	public function getRepeatable()
	{
		$session = JFactory::getSession();
		$update = array();
		$xmlfile = JPATH_SITE.'/plugins/j2store/'.$this->item->element.'/'.$this->item->element.'.xml';
		$version = '';
		if(JFile::exists($xmlfile)) {
			$xml = JFactory::getXML($xmlfile);
			$version =(string)$xml->version;
		}
		//only call once per session. Dont call this often
		if(!$session->has('plugin_update_data', 'j2store')) {
			try {
				$xmlfile = 'http://cdn.j2store.org/extensions.xml';
				$result = JFactory::getDocument($xmlfile);
				$curl = curl_init();
				curl_setopt_array($curl, array(
						CURLOPT_RETURNTRANSFER => 1,
						CURLOPT_URL => $xmlfile
				));
				$result = curl_exec($curl);
				if($result) {
					$extensions = simplexml_load_string($result, 'SimpleXMLElement');
					$type = (string) $extensions->extension->attributes()->type;
					if($type == 'payment') {
						$plugins = $extensions->extension->plugins->plugin;
						foreach ($plugins as $plugin) {
							$update[(string) $plugin->attributes()->element] = (array)$plugin;
						}
					}

					if(count($update)) {

						$session->set('plugin_update_data', $update, 'j2store' );
					}
				}

			}catch(Exception $e) {
				$warning = JText::_('J2STORE_PAYMENT_XML_REMOTE_ERROR');
				$this->assignRef('warning',	$warning);
			}

		} else {
			$update = $session->get('plugin_update_data', array(), 'j2store' );
		}

		$html ='';
		if(isset($update[$this->item->element]['version']) && $update[$this->item->element]['version'] > $version){
			$html .='<span class="text text-error">';
			$html .=$update[$this->item->element]['version'];
			$html .='</span> &nbsp;';
			$html .='<a class="btn btn-danger" href="http://j2store.org/my-account/my-downloads.html" target="_blank">';
			$html .= JText::_('J2STORE_DOWNLOAD');
			$html .='</a>';
		}
		return $html;
	}


}
