<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  Fields.Image
 *
 * @copyright   Copyright (C) 2005 - 2017 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

JLoader::register('JFormFieldFile', JPATH_LIBRARIES . '/joomla/form/fields/file.php');

class JFormFieldImage extends JFormFieldFile
{
	protected $type = 'image';
	
	/**
	 * Get a property
	 */
	public function __get($name)
	{
		switch ($name)
		{
			// We are generating the path here
			case 'value':
				if (!empty($this->value))
					return $this->getImagePath();
				else
					return '';
			default:
				return parent::__get($name);
		}
	}
	
	/**
	 * Generate the path for displaying an image
	 */
	protected function getImagePath()
	{
		return JRoute::_(JUri::root() . trim($this->getAttribute('path', 'images', 'string'), '/') . '/' . $this->value);
	}

	/**
	 * Get the input HTML
	 */
	public function getInput()
	{
		$html = '';
		
		// Get the path
		if (!empty($this->value))
			$html .= '<img src="' . $this->getImagePath() . '" class="' . $this->getAttribute('css', 'img-responsive img-thumbnail') . '" /><br />';
		
		// Add an image to the input
		$html .= parent::getInput();
		
		// In order to upload files, we need to add the enctype attribute to the form
		// This little script will fix it
		// Note that this script will only show when editing, because the getInput() method 
		// is ignored otherwise
		$html .= '<script>jQuery("form").attr("enctype","multipart/form-data");</script>';
		
		return $html;
	}
}