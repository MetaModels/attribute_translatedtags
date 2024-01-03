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
 * @author     Richard Henkenjohann <richardhenkenjohann@googlemail.com>
 * @author     Sven Baumann <baumann.sv@gmail.com>
 * @author     Ingolf Steinhardt <info@e-spin.de>
 * @copyright  2012-2024 The MetaModels team.
 * @license    https://github.com/MetaModels/attribute_translatedtags/blob/master/LICENSE LGPL-3.0-or-later
 * @filesource
 */

namespace MetaModels\AttributeTranslatedTagsBundle\Test\Attribute;

use Doctrine\DBAL\Connection;
use MetaModels\AttributeTranslatedTagsBundle\Attribute\AttributeTypeFactory;
use MetaModels\AttributeTranslatedTagsBundle\Attribute\TranslatedTags;
use MetaModels\IMetaModel;
use MetaModels\MetaModel;
use PHPUnit\Framework\TestCase;

/**
 * Test the attribute factory.
 *
 * @covers \MetaModels\AttributeTranslatedTagsBundle\Attribute\AttributeTypeFactory
 */
class TranslatedTagsAttributeTypeFactoryTest extends TestCase
{
    /**
     * Mock a MetaModel.
     *
     * @param string $tableName        The table name.
     * @param string $language         The language.
     * @param string $fallbackLanguage The fallback language.
     *
     * @return \PHPUnit_Framework_MockObject_MockObject|IMetaModel
     */
    protected function mockMetaModel($tableName, $language, $fallbackLanguage)
    {
        $metaModel = $this->getMockForAbstractClass(IMetaModel::class);

        $metaModel
            ->method('getTableName')
            ->willReturn($tableName);

        $metaModel
            ->method('getActiveLanguage')
            ->willReturn($language);

        $metaModel
            ->method('getFallbackLanguage')
            ->willReturn($fallbackLanguage);

        return $metaModel;
    }

    /**
     * Mock the database connection.
     *
     * @return \PHPUnit_Framework_MockObject_MockObject|Connection
     */
    private function mockConnection()
    {
        $connection = $this->getMockBuilder(Connection::class)
            ->disableOriginalConstructor()
            ->getMock();

        return $connection;
    }

    /**
     * Test creation of an plain SQL tags attribute.
     *
     * @return void
     */
    public function testCreateTags()
    {
        $factory   = new AttributeTypeFactory($this->mockConnection());
        $values    = [
            'tag_table'  => 'tl_page',
            'tag_column' => 'pid',
            'tag_alias'  => 'alias',
        ];
        $attribute = $factory->createInstance(
            $values,
            $this->mockMetaModel('mm_test', 'de', 'en')
        );

        $this->assertInstanceOf(TranslatedTags::class, $attribute);

        foreach ($values as $key => $value) {
            $this->assertEquals($value, $attribute->get($key), $key);
        }
    }
}
