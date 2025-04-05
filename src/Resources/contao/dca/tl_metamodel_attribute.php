<?php

/**
 * This file is part of MetaModels/attribute_translatedtags.
 *
 * (c) 2012-2024 The MetaModels team.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * This project is provided in good faith and hope to be usable by anyone.
 *
 * @package    MetaModels/attribute_translatedtags
 * @author     Christian Schiffler <c.schiffler@cyberspectrum.de>
 * @author     Stefan Heimes <stefan_heimes@hotmail.com>
 * @author     Andreas Isaak <info@andreas-isaak.de>
 * @author     David Maack <david.maack@arcor.de>
 * @author     Christian de la Haye <service@delahaye.de>
 * @author     Sven Baumann <baumann.sv@gmail.com>
 * @author     Richard Henkenjohann <richardhenkenjohann@googlemail.com>
 * @author     Ingolf Steinhardt <info@e-spin.de>
 * @copyright  2012-2024 The MetaModels team.
 * @license    https://github.com/MetaModels/attribute_translatedtags/blob/master/LICENSE LGPL-3.0-or-later
 * @filesource
 */

$GLOBALS['TL_DCA']['tl_metamodel_attribute']['metapalettes']['translatedtags extends tags'] = [
    '+display' => ['tag_langcolumn after tag_id', 'tag_srctable', 'tag_srcsorting'],
];

$GLOBALS['TL_DCA']['tl_metamodel_attribute']['fields']['tag_langcolumn'] = [
    'label'       => 'tag_langcolumn.label',
    'description' => 'tag_langcolumn.description',
    'exclude'     => true,
    'inputType'   => 'select',
    'eval'        => [
        'includeBlankOption' => true,
        'doNotSaveEmpty'     => true,
        'alwaysSave'         => true,
        'submitOnChange'     => true,
        'tl_class'           => 'w50',
        'chosen'             => 'true',
    ],
    'sql'         => "varchar(255) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_metamodel_attribute']['fields']['tag_srctable'] = [
    'label'       => 'tag_srctable.label',
    'description' => 'tag_srctable.description',
    'exclude'     => true,
    'inputType'   => 'select',
    'eval'        => [
        'includeBlankOption' => true,
        'alwaysSave'         => true,
        'submitOnChange'     => true,
        'tl_class'           => 'w50',
        'chosen'             => 'true',
    ],
    'sql'         => "varchar(255) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_metamodel_attribute']['fields']['tag_srcsorting'] = [
    'label'       => 'tag_srcsorting.label',
    'description' => 'tag_srcsorting.description',
    'exclude'     => true,
    'inputType'   => 'select',
    'eval'        => [
        'includeBlankOption' => true,
        'alwaysSave'         => true,
        'submitOnChange'     => true,
        'tl_class'           => 'w50',
        'chosen'             => 'true',
    ],
    'sql'         => "varchar(255) NOT NULL default ''",
];
