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

namespace MetaModels\AttributeTranslatedTagsBundle\EventListener\DcGeneral\Table\Attribute;

use ContaoCommunityAlliance\DcGeneral\Contao\View\Contao2BackendView\Event\GetPropertyOptionsEvent;
use Doctrine\DBAL\Connection;
use MetaModels\IFactory;
use Symfony\Component\Translation\Exception\InvalidArgumentException;
use Symfony\Component\Translation\TranslatorInterface;

/**
 * Class PropertyOptionsListener
 *
 * Loads the property options.
 */
class PropertyOptionsListener
{

    /**
     * MetaModels factory.
     *
     * @var IFactory
     */
    private $factory;

    /**
     * Database connection.
     *
     * @var Connection
     */
    private $connection;

    /**
     * Translator.
     *
     * @var TranslatorInterface
     */
    private $translator;

    /**
     * PropertyOptionsListener constructor.
     *
     * @param IFactory            $factory    The MetaModels factory.
     * @param Connection          $connection The database connection.
     * @param TranslatorInterface $translator The translator.
     */
    public function __construct(IFactory $factory, Connection $connection, TranslatorInterface $translator)
    {
        $this->factory    = $factory;
        $this->connection = $connection;
        $this->translator = $translator;
    }

    /**
     * Retrieve all column names for the current selected table.
     *
     * @param GetPropertyOptionsEvent $event The event.
     *
     * @return void
     */
    public function getLangColumnNames(GetPropertyOptionsEvent $event)
    {
        if (($event->getPropertyName() !== 'tag_langcolumn')
            || ('tl_metamodel_attribute' !== $event->getEnvironment()->getDataDefinition()->getName())
        ) {
            return;
        }

        $table = $event->getModel()->getProperty('tag_table');
        if (0 === \strpos($table, 'mm_')) {
            $attributes = $this->getAttributeNamesFrom($table);
            \asort($attributes);

            $sqlKey       = $this->translator->trans(
                'tl_metamodel_attribute.tag_column_type.sql',
                [],
                'tl_metamodel_attribute'
            );
            $attributeKey = $this->translator->trans(
                'tl_metamodel_attribute.tag_column_type.attribute',
                [],
                'tl_metamodel_attribute'
            );

            $event->setOptions(
                [
                    $sqlKey       => \array_diff_key(
                        $this->getColumnNamesFrom($table),
                        \array_flip(\array_keys($attributes))
                    ),
                    $attributeKey => $attributes,
                ]
            );

            return;
        }
        $result = $this->getColumnNamesFrom($table);
        if (!empty($result)) {
            \asort($result);
            $event->setOptions($result);
        }
    }

    /**
     * Retrieve all database table names.
     *
     * @param GetPropertyOptionsEvent $event The event.
     *
     * @return void
     *
     * @throws InvalidArgumentException If the locale contains invalid characters.
     */
    public function handleSrcTableNames(GetPropertyOptionsEvent $event)
    {
        if (($event->getPropertyName() !== 'tag_srctable')
            || ($event->getEnvironment()->getDataDefinition()->getName() !== 'tl_metamodel_attribute')) {
            return;
        }

        $sqlTable     = $this->translator->trans(
            'tl_metamodel_attribute.tag_table_type.sql-table',
            [],
            'contao_tl_metamodel_attribute'
        );
        $translated   = $this->translator->trans(
            'tl_metamodel_attribute.tag_table_type.translated',
            [],
            'contao_tl_metamodel_attribute'
        );
        $untranslated = $this->translator->trans(
            'tl_metamodel_attribute.tag_table_type.untranslated',
            [],
            'contao_tl_metamodel_attribute'
        );

        $result = $this->getMetaModelTableNames($translated, $untranslated);
        foreach ($this->connection->getSchemaManager()->listTableNames() as $table) {
            if (0 !== \strpos($table, 'mm_')) {
                $result[$sqlTable][$table] = $table;
            }
        }

        if (\is_array($result[$translated])) {
            \asort($result[$translated]);
        }

        if (\is_array($result[$untranslated])) {
            \asort($result[$untranslated]);
        }

        if (\is_array($result[$sqlTable])) {
            \asort($result[$sqlTable]);
        }

        $event->setOptions($result);
    }

    /**
     * Retrieve all column names of type int for the current selected table.
     *
     * @param GetPropertyOptionsEvent $event The event.
     *
     * @return void
     */
    public function getSourceColumnNames(GetPropertyOptionsEvent $event)
    {
        if (($event->getPropertyName() !== 'tag_srcsorting')
            || ($event->getEnvironment()->getDataDefinition()->getName() !== 'tl_metamodel_attribute')) {
            return;
        }

        $model = $event->getModel();
        $table = $model->getProperty('select_srctable');

        if (!$table || !$this->connection->getSchemaManager()->tablesExist([$table])) {
            return;
        }

        $result = [];

        $indexes = $this->connection->getSchemaManager()->listTableIndexes($table);
        foreach ($this->connection->getSchemaManager()->listTableColumns($table) as $column) {
            if (\array_key_exists($column->getName(), $indexes)) {
                continue;
            }
            $colName = $column->getName();

            $result[$colName] = $colName;
        }

        $event->setOptions($result);
    }

    /**
     * Retrieve all MetaModels table names.
     *
     * @param string $keyTranslated   The array key to use for translated MetaModels.
     *
     * @param string $keyUntranslated The array key to use for untranslated MetaModels.
     *
     * @return array
     */
    private function getMetaModelTableNames($keyTranslated, $keyUntranslated)
    {
        $result = [];
        foreach ($this->factory->collectNames() as $table) {
            $metaModel = $this->factory->getMetaModel($table);
            if (null === $metaModel) {
                continue;
            }

            if ($metaModel->isTranslated()) {
                $result[$keyTranslated][$table] = \sprintf('%s (%s)', $metaModel->get('name'), $table);
            } else {
                $result[$keyUntranslated][$table] = \sprintf('%s (%s)', $metaModel->get('name'), $table);
            }
        }

        return $result;
    }

    /**
     * Retrieve all attribute names from a given MetaModel name.
     *
     * @param string $metaModelName The name of the MetaModel.
     *
     * @return string[]
     */
    private function getAttributeNamesFrom($metaModelName)
    {
        $metaModel = $this->factory->getMetaModel($metaModelName);
        $result    = [];
        if (null === $metaModel) {
            return $result;
        }

        foreach ($metaModel->getAttributes() as $attribute) {
            $name   = $attribute->getName();
            $column = $attribute->getColName();
            $type   = $attribute->get('type');

            $result[$column] = \sprintf('%s (%s - %s)', $name, $column, $type);
        }

        return $result;
    }

    /**
     * Retrieve all column names for the given table.
     *
     * @param string $table The table name.
     *
     * @return array
     */
    private function getColumnNamesFrom($table)
    {
        if (0 === \strpos($table, 'mm_')) {
            $attributes = $this->getAttributeNamesFrom($table);
            \asort($attributes);

            $sql       = $this->translator->trans(
                'tl_metamodel_attribute.tag_column_type.sql',
                [],
                'contao_tl_metamodel_attribute'
            );
            $attribute = $this->translator->trans(
                'tl_metamodel_attribute.tag_column_type.attribute',
                [],
                'contao_tl_metamodel_attribute'
            );

            return [
                $sql       => \array_diff_key(
                    $this->getColumnNamesFromTable($table),
                    \array_flip(\array_keys($attributes))
                ),
                $attribute => $attributes,
            ];
        }

        return $this->getColumnNamesFromTable($table);
    }

    /**
     * Retrieve all columns from a database table.
     *
     * @param string     $tableName  The database table name.
     *
     * @param array|null $typeFilter Optional of types to filter for.
     *
     * @return string[]
     */
    private function getColumnNamesFromTable($tableName, $typeFilter = null)
    {
        if (!$this->connection->getSchemaManager()->tablesExist([$tableName])) {
            return [];
        }

        $result = [];
        foreach ($this->connection->getSchemaManager()->listTableColumns($tableName) as $column) {
            if (($typeFilter === null) || \in_array($column->getType()->getName(), $typeFilter, true)) {
                $result[$column->getName()] = $column->getName();
            }
        }

        if (!empty($result)) {
            \asort($result);

            return $result;
        }

        return $result;
    }
}
