<?php
/**
 * Element: Slide
 * Element to create a new slide pane
 *
 * @package         NoNumber Framework
 * @version         14.5.15
 *
 * @author          Peter van Westen <peter@nonumber.nl>
 * @link            http://www.nonumber.nl
 * @copyright       Copyright © 2014 NoNumber All Rights Reserved
 * @license         http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;

require_once JPATH_PLUGINS . '/system/nnframework/helpers/text.php';

class JFormFieldNN_Slide extends JFormField
{
	public $type = 'Slide';
	private $params = null;

	protected function getLabel()
	{
		return '';
	}

	protected function getInput()
	{
		$this->params = $this->element->attributes();

		JHtml::stylesheet('nnframework/style.min.css', false, true);

		$label = NNText::html_entity_decoder(JText::_($this->get('label')));
		$description = $this->get('description');
		$lang_file = $this->get('language_file');

		$html = '</td></tr></table></div></div>';
		$html .= '<div class="panel"><h3 class="jpane-toggler title" id="advanced-page"><span>';
		$html .= $label;
		$html .= '</span></h3>';
		$html .= '<div class="jpane-slider content"><table width="100%" class="paramlist admintable" cellspacing="1"><tr><td colspan="2" class="paramlist_value">';

		if ($description)
		{
			// variables
			$v1 = $this->get('var1');
			$v2 = $this->get('var2');
			$v3 = $this->get('var3');
			$v4 = $this->get('var4');
			$v5 = $this->get('var5');

			$description = NNText::html_entity_decoder(trim(JText::sprintf($description, $v1, $v2, $v3, $v4, $v5)));
		}

		if ($lang_file)
		{
			jimport('joomla.filesystem.file');

			// Include extra language file
			$lang = str_replace('_', '-', JFactory::getLanguage()->getTag());

			$inc = '';
			$lang_path = 'language/' . $lang . '/' . $lang . '.' . $lang_file . '.inc.php';
			if (JFile::exists(JPATH_ADMINISTRATOR . '/' . $lang_path))
			{
				$inc = JPATH_ADMINISTRATOR . '/' . $lang_path;
			}
			else if (JFile::exists(JPATH_SITE . '/' . $lang_path))
			{
				$inc = JPATH_SITE . '/' . $lang_path;
			}
			if (!$inc && $lang != 'en-GB')
			{
				$lang = 'en-GB';
				$lang_path = 'language/' . $lang . '/' . $lang . '.' . $lang_file . '.inc.php';
				if (JFile::exists(JPATH_ADMINISTRATOR . '/' . $lang_path))
				{
					$inc = JPATH_ADMINISTRATOR . '/' . $lang_path;
				}
				else if (JFile::exists(JPATH_SITE . '/' . $lang_path))
				{
					$inc = JPATH_SITE . '/' . $lang_path;
				}
			}
			if ($inc)
			{
				include $inc;
			}
		}

		if ($description)
		{
			$description = str_replace('span style="font-family:monospace;"', 'span class="nn_code"', $description);
			if ($description['0'] != '<')
			{
				$description = '<p>' . $description . '</p>';
			}
			$class = 'nn_panel nn_panel_description';
			$html .= '<div class="' . $class . '"><div class="nn_block nn_title">';
			$html .= $description;
			$html .= '<div style="clear: both;"></div></div></div>';
		}

		return $html;
	}

	private function get($val, $default = '')
	{
		return (isset($this->params[$val]) && (string) $this->params[$val] != '') ? (string) $this->params[$val] : $default;
	}
}
