<?php

/**
 * This file is part of MetaModels/attribute_translatedtags.
 *
 * (c) 2012-2019 The MetaModels team.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * This project is provided in good faith and hope to be usable by anyone.
 *
 * @package    MetaModels/attribute_translatedtags
 * @author     Christian Schiffler <c.schiffler@cyberspectrum.de>
 * @author     Sven Baumann <baumann.sv@gmail.com>
 * @author     Richard Henkenjohann <richardhenkenjohann@googlemail.com>
 * @copyright  2012-2019 The MetaModels team.
 * @license    https://github.com/MetaModels/attribute_translatedtags/blob/master/LICENSE LGPL-3.0-or-later
 * @filesource
 */

namespace MetaModels\AttributeTranslatedTagsBundle\Attribute;

use Doctrine\DBAL\Connection;
use MetaModels\Attribute\AbstractAttributeTypeFactory;
use MetaModels\Attribute\IAttribute;
use MetaModels\IMetaModel;

/**
 * Attribute type factory for translated tags attributes.
 */
class AttributeTypeFactory extends AbstractAttributeTypeFactory
{
    /**
     * Database connection.
     *
     * @var Connection
     */
    private $connection;

    /**
     * Create a new instance.
     *
     * @param Connection $connection The database connection to use.
     */
    public function __construct(Connection $connection)
    {
        parent::__construct();

        $this->typeName   = 'translatedtags';
        $this->typeIcon   = 'bundles/metamodelsattributetranslatedtags/tags.png';
        $this->typeClass  = TranslatedTags::class;
        $this->connection = $connection;
    }

    /**
     * Create a new instance with the given information.
     *
     * @param array      $information The attribute information.
     *
     * @param IMetaModel $metaModel   The MetaModel instance the attribute shall be created for.
     *
     * @return IAttribute|null
     */
    public function createInstance($information, $metaModel)
    {
        return new $this->typeClass($metaModel, $information, $this->connection);
    }
}
