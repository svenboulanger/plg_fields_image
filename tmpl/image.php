<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  Fields.Image
 *
 * @copyright   Copyright (C) 2005 - 2017 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

$value = $field->value;

if ($value == '')
{
	return;
}

// echo htmlentities($value);
$path = JRoute::_(JUri::root() . trim($field->fieldparams->get('path', $this->default_path, 'string'), '/') . '/' . $value);
$class = $field->params->get('css', 'img-thumbnail img-responsive', 'string');
echo "<img src=\"$path\" class=\"$class\" \>";
