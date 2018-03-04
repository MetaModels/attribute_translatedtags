<?php
/**
 * The MetaModels extension allows the creation of multiple collections of custom items,
 * each with its own unique set of selectable attributes, with attribute extendability.
 * The Front-End modules allow you to build powerful listing and filtering of the
 * data in each collection.
 *
 * PHP version 5
 *
 * @package    MetaModels
 * @subpackage AttributeTranslatedTags
 * @author     Christian Schiffler <c.schiffler@cyberspectrum.de>
 * @author     Christian de la Haye <service@delahaye.de>
 * @copyright  The MetaModels team.
 * @license    LGPL.
 * @filesource
 */

/**
 * Fields
 */
$GLOBALS['TL_LANG']['tl_metamodel_attribute']['typeOptions']['translatedtags'] = 'Translated tags';
$GLOBALS['TL_LANG']['tl_metamodel_attribute']['tag_langcolumn'][0]             = 'Language column';
$GLOBALS['TL_LANG']['tl_metamodel_attribute']['tag_langcolumn'][1]             =
    'Please specify which column holds the language code in ISO 639-1';
$GLOBALS['TL_LANG']['tl_metamodel_attribute']['tag_srctable'][0]               = 'Untranslated table for sorting';
$GLOBALS['TL_LANG']['tl_metamodel_attribute']['tag_srctable'][1]               =
    'Please specify the table that provides the sorting column.';
$GLOBALS['TL_LANG']['tl_metamodel_attribute']['tag_srcsorting'][0]             = 'Sorting column';
$GLOBALS['TL_LANG']['tl_metamodel_attribute']['tag_srcsorting'][1]             =
    'Please specify which column of the untranslated table shall be used for sorting.';
