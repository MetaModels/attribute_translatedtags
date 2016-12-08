<?php

/**
 * This file is part of MetaModels/attribute_translatedtags.
 *
 * (c) 2012-2016 The MetaModels team.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * The MetaModels extension allows the creation of multiple collections of custom items,
 * each with its own unique set of selectable attributes, with attribute extendability.
 * The Front-End modules allow you to build powerful listing and filtering of the
 * data in each collection.
 *
 * @package    MetaModels
 * @subpackage AttributeTranslatedTags
 * @author     Christian Schiffler <c.schiffler@cyberspectrum.de>
 * @author     Stefan Heimes <stefan_heimes@hotmail.com>
 * @author     Andreas Isaak <info@andreas-isaak.de>
 * @author     David Maack <david.maack@arcor.de>
 * @author     Christian de la Haye <service@delahaye.de>
 * @author     Sven Baumann <baumann.sv@gmail.com>
 * @copyright  2012-2016 The MetaModels team.
 * @license    https://github.com/MetaModels/attribute_translatedtags/blob/master/LICENSE LGPL-3.0
 * @filesource
 */

$GLOBALS['TL_DCA']['tl_metamodel_attribute']['metapalettes']['translatedtags extends tags'] = array
(
    '+display' => array('tag_langcolumn after tag_id', 'tag_srctable', 'tag_srcsorting')
);

$GLOBALS['TL_DCA']['tl_metamodel_attribute']['fields']['tag_langcolumn'] = array
(
    'label'                  => &$GLOBALS['TL_LANG']['tl_metamodel_attribute']['tag_langcolumn'],
    'exclude'                => true,
    'inputType'              => 'select',
    'eval'                   => array
    (
        'includeBlankOption' => true,
        'doNotSaveEmpty'     => true,
        'alwaysSave'         => true,
        'submitOnChange'     => true,
        'tl_class'           => 'w50',
        'chosen'             => 'true'
    ),
);

$GLOBALS['TL_DCA']['tl_metamodel_attribute']['fields']['tag_srctable'] = array
(
    'label'                  => &$GLOBALS['TL_LANG']['tl_metamodel_attribute']['tag_srctable'],
    'exclude'                => true,
    'inputType'              => 'select',
    'eval'                   => array
    (
        'includeBlankOption' => true,
        'alwaysSave'         => true,
        'submitOnChange'     => true,
        'tl_class'           => 'w50',
        'chosen'             => 'true'
    ),
);

$GLOBALS['TL_DCA']['tl_metamodel_attribute']['fields']['tag_srcsorting'] = array
(
    'label'                  => &$GLOBALS['TL_LANG']['tl_metamodel_attribute']['tag_srcsorting'],
    'exclude'                => true,
    'inputType'              => 'select',
    'eval'                   => array
    (
        'includeBlankOption' => true,
        'alwaysSave'         => true,
        'submitOnChange'     => true,
        'tl_class'           => 'w50',
        'chosen'             => 'true'
    ),
);
