<?php
/**
 * The MetaModels extension allows the creation of multiple collections of custom items,
 * each with its own unique set of selectable attributes, with attribute extendability.
 * The Front-End modules allow you to build powerful listing and filtering of the
 * data in each collection.
 *
 * PHP version 5
 * @package	   MetaModels
 * @subpackage Backend
 * @author     Christian Schiffler <c.schiffler@cyberspectrum.de>
 * @author     Christian Kolb <info@kolbchristian.de>
 * @copyright  The MetaModels team.
 * @license    LGPL.
 * @filesource
 */
if (!defined('TL_ROOT'))
{
	die('You cannot access this file directly!');
}

/**
 * Fields
 */
$GLOBALS['TL_LANG']['tl_metamodel_attribute']['typeOptions']['translatedtags']    = 'Übersetzte Tags';

$GLOBALS['TL_LANG']['tl_metamodel_attribute']['tag_langcolumn'] = array('Sprachenspalte', 'Bitte wählen Sie die Spalte aus, die den Sprachcode ISO 639-1 beinhaltet.');
