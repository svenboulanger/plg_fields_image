<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  Fields.Image
 *
 * @copyright   Copyright (C) 2005 - 2017 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

JLoader::import('components.com_fields.libraries.fieldsplugin', JPATH_ADMINISTRATOR);
JLoader::register('FieldsHelper', JPATH_ADMINISTRATOR . '/components/com_fields/helpers/fields.php');

/**
 * Fields Image Plugin
 *
 * @since 3.7.0
 */
class PlgFieldsImage extends FieldsPlugin
{
	// The uploaded file URL's
	protected $uploaded = array();
	
	// The default path for images
	protected $default_path = 'images';
	
	// Valid image extensions with there MIME type
	protected $validImageTypes = array(
		"jpg" => "image/jpeg",
		"png" => "image/png",
		"gif" => "image/gif"
	);
	
	/**
	 * On content before save
	 * We need to save all the previous filenames because it gets overwritten everytime,
	 * even when no image is uploaded.
	 *
	 * @param string 	$context	The current context
	 * @param mixed 	$item		The current item being saved
	 * @param boolean 	$isNew		True if the item is added as a new item
	 * @param array 	$data		The form data
	 *
	 * @return boolean				Returns false if the method fails to save the image
	 */
	public function onContentBeforeSave($context, $item, $isNew, $data = array())
	{
		$app = JFactory::getApplication();
		$files = $app->input->files->get('jform', array(), 'array');
		if (!empty($files['com_fields']))
			$up = $files['com_fields'];

		// Get the fields for the current content
		$fields = FieldsHelper::getFields($context, $item, true);
		
		// Check the type for each
		foreach ($fields as $field)
		{
			if ($field->type == $this->_name)
			{
				// Extract the previous URL's to restore them later if no file has been uploaded
				$uploaded[$field->id] = $this->value;
				if (!empty($up[$field->name]))
				{
					if ($this->hasFile($up[$field->name]))
					{
						// Try to upload the file
						$response = $this->uploadImage($up[$field->name], $field->fieldparams, $field->id . '_' . $item->id . '_');
						if ($response['success'] == false)
						{
							$app->enqueueMessage($response['error'], 'error');
							return false;
						}
						else
						{
							$this->uploaded[] = (object) array(
								'field' => $field->id,
								'item' => $item->id,
								'image' => $response['image'],
								'old' => $field->rawvalue
							);
						}
					}
					else
					{
						// No file given, so just use the old value
						$this->uploaded[] = (object) array(
							'field' => $field->id,
							'item' => $item->id,
							'image' => $field->rawvalue,
							'old' => $field->rawvalue
						);
					}
				}
			}
		}
	}

	/**
	 * On user before save
	 * Basically a redirect to the content functions
	 */
	public function onUserBeforeSave($oldUser, $isNew, $newUser)
	{
		return $this->onContentBeforeSave('com_users.user', (object)$oldUser, false, $newUser);
	}
	
	/**
	 * On content after save
	 * Overwrite the fields with the actual uploaded image
	 *
	 * @param string	$context	The current context
	 * @param mixed		$item		The current item
	 * @param boolean 	$isNew		True if the item is being added as a new item
	 * @param array		$data		The form data
	 *
	 * @return boolean				Returns true
	 */
	public function onContentAfterSave($context, $item, $isNew, $data = array())
	{
		// Loading the model
		$model = JModelLegacy::getInstance('Field', 'FieldsModel', array('ignore_request' => true));
		$app = JFactory::getApplication();

		// Setting the value for the field and the item
		foreach ($this->uploaded as $upload)
		{
			$model->setFieldValue($upload->field, $upload->item, $upload->image);
			
			// Remove the old image if it exists
			if ($upload->image != $upload->old)
			{
				$field = new JRegistry($model->getItem($upload->field)->fieldparams);
				if ($field->get('remove_old', 1, 'int') > 0)
				{
					unlink($this->getPath($field, $upload->old));
				}
			}
		}
		
		return true;
	}
	
	/**
	 * On user after save
	 * Basically redirects to the content functions
	 */
	public function onUserAfterSave($userData, $isNew, $success, $msg)
	{
		// It is not possible to manipulate the user during save events
		// Check if data is valid or we are in a recursion
		if (!$userData['id'] || !$success)
		{
			return true;
		}

		$user = JFactory::getUser($userData['id']);

		$task = JFactory::getApplication()->input->getCmd('task');

		// Skip fields save when we activate a user, because we will lose the saved data
		if (in_array($task, array('activate', 'block', 'unblock')))
		{
			return true;
		}

		// Trigger the events with a real user
		$this->onContentAfterSave('com_users.user', $user, false, $userData);

		return true;
	}
	
	/**
	 * Performs the display event.
	 *
	 * @param   string    $context  The context
	 * @param   stdClass  $item     The item
	 *
	 * @return  void
	 *
	 * @since   3.7.0
	 */
	public function onContentPrepare($context, &$item)
	{
		if (!in_array($context, array('com_users.user')))
			return;
		
		// Change some values where necessary
		foreach ($item->jcfields as $id => $field)
		{
			$field->rawvalue = $field->value;
			$item->jcfields[$id] = $field;
		}
		
		// Make sure the images show up nicely in the user profile page
		if (!JHtml::isRegistered('users.image'))
		{
			JHtml::register('users.image', array(__CLASS__, 'field_image'));
		}
	}
	
	/**
	 * Displays an image in the frontend
	 * Used in the user profile page
	 */
	public static function field_image($value)
	{
		if (!empty($value))
			return "<img class=\"img-responsive img-thumbnail\" src=\"$value\" />";
		return '-';
	}
	
	/**
	 * Find out if a file has been uploaded
	 *
	 * @param assoc $file		The file data
	 *
	 * @return Boolean			True if this file has been uploaded
	 */
	protected function hasFile($file)
	{
		if ($file['error'] == UPLOAD_ERR_NO_FILE)
			return false;
		return true;
	}

	/**
	 * Get the path to the filename
	 *
	 * @param object $fieldparams		The object containing the field parameters
	 * @param string $filename			The filename of the image
	 *
	 * @return string					The full path to the image
	 */
	protected function getPath($fieldparams, $filename = '')
	{
		$path = $fieldparams->get('path', $this->default_path, 'string');
		$path = trim($path, '/');
		return JPATH_ROOT . '/' . $path . '/' . $filename;
	}
	
	/**
	 * Upload an image
	 *
	 * @param string $file				The filename
	 * @param array $validImageTypes	The accepted image types
	 *
	 * @return mixed					Returns an array with the image info if succeeded, false otherwise
	 */
	protected function uploadImage($file, $params, $prefix = '')
	{
		// Get the full path and create the directory if necessary
		$path = $this->getPath($params);
		if (!file_exists($path))
		{
			mkdir($path, 0777, true);
		}
		
		try
		{
			// Note: Taken from http://php.net/manual/en/features.file-upload.php
			
			// Undefined | Multiple Files | Corruption Attack
			// If this request falls under any of them, treat it invalid
			if (!isset($file['error']) || is_array($file['error']))
			{
				return array("success" => false, "error" => JText::_('PLG_IMAGE_NOACCESS'));
			}
			
			// Check $data['error'] value
			switch ($file['error'])
			{
				case UPLOAD_ERR_OK: 
					break;
					
				case UPLOAD_ERR_NO_FILE: 
					return array("success" => false, "error" => JText::_('PLG_IMAGE_NO_FILE'));
					
				case UPLOAD_ERR_FORM_SIZE:
					return array("success" => false, "error" => JText::_('PLG_IMAGE_FILESIZE_EXCEEDED'));
					
				default: 
					return array("success" => false, "error" => JText::_('PLG_IMAGE_UNKNOWN_ERROR'));
			}
			
			// Check the filesize limit here
			if ($file['size'] > JUtility::getMaxUploadSize())
			{
				return array("success" => false, "error" => JText::_('PLG_IMAGE_FILESIZE_EXCEEDED'));
			}
			
			// Do not trust $data['mime'] VALUE !!
			// Check MIME Type by yourself
			$finfo = new finfo(FILEINFO_MIME_TYPE);
			$mime = $finfo->file($file['tmp_name']);
			if (($ext = array_search($mime, $validImageTypes, true)) === false)
			{
				return array("success" => false, "error" => JText::_('PLG_IMAGE_INVALID_FORMAT'));
			}
			
			// Generate a filename and thumbnail path
			// By adding the user ID, we can always find back who uploaded the file
			while (file_exists($path . ($nurl = $prefix . uniqid() . '.jpg')));

			// Resize the image
			$image = new JImage($file['tmp_name']);
			$w = $params->get('width', 256, 'int');
			$h = $params->get('height', 256, 'int');
			$iw = $image->getWidth();
			$ih = $image->getHeight();
			if ($w != 0 || $h != 0)
			{
				// If there is a 0 size, then this will just maintain the aspect ratio
				if ($w == 0)
				{
					$w = $h / $ih * $iw;
				}
				elseif ($h == 0)
				{
					$h = $w / $iw * $ih;
				}

				// Resize anyway if scaling needs to be done up and down, alse only scale down
				if (($params->get('scale_down', 0, 'int') > 0) || ($w < $iw || $h < $ih))
				{
					$image->resize($w, $h, false, JImage::SCALE_INSIDE);
				}
			}

			// Rotate the image
			$exif = exif_read_data($file['tmp_name']);
			if (!empty($exif['Orientation']))
			{
				switch ($exif['Orientation'])
				{
					case 3:
						ini_set('memory_limit', '512M');
						$image->rotate(180, -1, false);
						break;
						
					case 6:
						ini_set('memory_limit', '512M');
						$image->rotate(-90, -1, false);
						break;
						
					case 8:
						ini_set('memory_limit', '512M');
						$image->rotate(90, -1, false);
						break;
				}
			}

			if ($image->toFile($path . $nurl))
			{
				// Make sure this can't be run again
				unlink($file['tmp_name']);
				return array('success' => true, 'image' => $nurl);
			}
			
			return array('success' => false, 'error' => 'Unknown');
		}
		catch (Exception $ex)
		{
			return array(
				'error' => $ex->getMessage(),
				'success' => false
			);
		}
	}
}