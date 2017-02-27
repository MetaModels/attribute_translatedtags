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

namespace MetaModels\Attribute\TranslatedTags;

use Contao\Database\Result;
use MetaModels\Attribute\ITranslated;
use MetaModels\Attribute\Tags\Tags;
use MetaModels\Filter\Rules\SimpleQuery;

/**
 * This is the MetaModelAttribute class for handling translated tag attributes.
 *
 * @package    MetaModels
 * @subpackage AttributeTranslatedTags
 * @author     Christian Schiffler <c.schiffler@cyberspectrum.de>
 * @author     Christian de la Haye <service@delahaye.de>
 */
class TranslatedTags extends Tags implements ITranslated
{
    /**
     * Retrieve the name of the language column.
     *
     * @return string
     */
    protected function getTagLangColumn()
    {
        return $this->get('tag_langcolumn') ?: null;
    }

    /**
     * Retrieve the sorting source table.
     *
     * @return string
     */
    protected function getTagSortSourceTable()
    {
        return $this->get('tag_srctable') ?: null;
    }

    /**
     * Retrieve the sorting source column.
     *
     * @param bool $prefixWithTable Flag if the column name shall be prefixed with the table name "<table>.<column>".
     *
     * @return string
     */
    protected function getTagSortSourceColumn($prefixWithTable)
    {
        $column = $this->get('tag_srcsorting');

        if (!$column) {
            return null;
        }

        if ($prefixWithTable) {
            return $this->getTagSortSourceTable() . '.' . $column;
        }

        return $column;
    }

    /**
     * {@inheritDoc}
     */
    protected function checkConfiguration()
    {
        // Parent checks apply.
        if (!parent::checkConfiguration()) {
            return false;
        }

        // If sort table given and non existent, exit.
        if (null !== ($sortTable = $this->getTagSortSourceTable()) && !$this->getDatabase()->tableExists($sortTable)) {
            return false;
        }

        return (null !== $this->getTagLangColumn());
    }

    /**
     * Determine the amount of entries in the relation table for this attribute and the given value ids.
     *
     * @param int[] $arrIds The ids of the items for which the tag count shall be determined.
     *
     * @return array
     */
    public function getTagCount($arrIds)
    {
        $objDB        = $this->getDatabase();
        $strTableName = $this->getTagSource();
        $strColNameId = $this->getIdColumn();
        $arrReturn    = array();

        if ($strTableName && $strColNameId) {
            $objValue = $objDB
                ->prepare(sprintf(
                    'SELECT `item_id`, count(*) as count
                    FROM `tl_metamodel_tag_relation`
                    WHERE att_id = ? AND item_id IN (%1$s)
                    GROUP BY `item_id`',
                    implode(',', $arrIds)
                ))
                ->execute($this->get('id'));

            while ($objValue->next()) {
                /** @noinspection PhpUndefinedFieldInspection */
                $itemId = $objValue->item_id;

                if (!isset($arrReturn[$itemId])) {
                    $arrReturn[$itemId] = array();
                }

                /** @noinspection PhpUndefinedFieldInspection */
                $arrReturn[$itemId] = $objValue->count;
            }
        }
        return $arrReturn;
    }

    /**
     * Convert the value ids to a result array.
     *
     * @param Result     $valueResult The database result.
     *
     * @param null|array $counter     The destination for the counter values.
     *
     * @return array
     */
    protected function convertValueIds($valueResult, &$counter = null)
    {
        $result      = array();
        $aliases     = array();
        $idColumn    = $this->getIdColumn();
        $aliasColumn = $this->getAliasColumn();
        while ($valueResult->next()) {
            $valueId           = $valueResult->$idColumn;
            $aliases[$valueId] = $valueResult->$aliasColumn;
            $result[]          = $valueId;
        }

        if (($counter !== null) && !empty($result)) {
            $objCount = $this
                ->getDatabase()
                ->prepare(
                    sprintf(
                        'SELECT value_id, COUNT(value_id) as mm_count
                        FROM tl_metamodel_tag_relation
                        WHERE att_id=?
                        AND value_id IN (%s)
                        GROUP BY item_id',
                        $this->parameterMask($result)
                    )
                )
                ->execute(array_merge(array($this->get('id')), $result));
            /** @noinspection PhpUndefinedFieldInspection */
            $amount = $objCount->mm_count;
            /** @noinspection PhpUndefinedFieldInspection */
            $valueId = $objCount->value_id;
            $alias   = $aliases[$valueId];

            $counter[$valueId] = $amount;
            $counter[$alias]   = $amount;
        }

        return $result;
    }

    /**
     * Fetch the ids of options optionally limited to the items with the provided ids.
     *
     * NOTE: this does not take the actual availability of an value in the current or
     * fallback languages into account.
     * This method is mainly intended as a helper for TranslatedTags::getFilterOptions().
     *
     * @param string[] $arrIds      A list of item ids that the result shall be limited to.
     *
     * @param bool     $blnUsedOnly Do only return ids that have matches in the real table.
     *
     * @param null     $arrCount    Array to where the amount of items per tag shall be stored. May be null to return
     *                              nothing.
     *
     * @return int[] a list of all matching value ids.
     *
     * @see TranslatedTags::getFilterOptions().
     *
     * @@SuppressWarnings(PHPMD.ExcessiveMethodLength)
     * @@SuppressWarnings(PHPMD.NPathComplexity)
     */
    protected function getValueIds($arrIds, $blnUsedOnly, &$arrCount = null)
    {
        if ($arrIds === array()) {
            return array();
        }

        // Get name of the alias column.
        $strColNameAlias = $this->getAliasColumn();

        // First off, we need to determine the option ids in the foreign table.
        $objDB = $this->getDatabase();

        $join    = false;
        $fields  = false;
        $sorting = false;
        $where   = $this->getWhereColumn()
            ? 'AND (' . $this->getWhereColumn() . ')'
            : false;

        if ($this->getTagSortSourceTable()) {
            $fields = ', ' . $this->getTagSortSourceTable() . '.*';
            $join   = sprintf(
                'JOIN %s ON %s.%s=%s.id',
                $this->getTagSortSourceTable(),
                $this->getTagSource(),
                $this->getIdColumn(),
                $this->getTagSortSourceTable()
            );

            if ($this->getTagSortSourceColumn(true)) {
                $sorting = $this->getTagSortSourceColumn(true) . ',';
            }
        }

        if ($arrIds !== null) {
            $objValueIds = $objDB->prepare(sprintf(
                'SELECT COUNT(%1$s.%2$s) as mm_count, %1$s.%2$s, %1$s.%9$s %6$s
                FROM %1$s
                %8$s
                LEFT JOIN tl_metamodel_tag_relation ON (
                    (tl_metamodel_tag_relation.att_id=?)
                    AND (tl_metamodel_tag_relation.value_id=%1$s.%2$s)
                )
                WHERE tl_metamodel_tag_relation.item_id IN (%3$s) %5$s
                GROUP BY %1$s.%2$s
                ORDER BY %7$s %1$s.%4$s',
                // @codingStandardsIgnoreStart - We want to keep the numbers as comment at the end of the following lines.
                $this->getTagSource(),      // 1
                $this->getIdColumn(),       // 2
                implode(',', $arrIds),      // 3
                $this->getSortingColumn(),  // 4
                $where,                     // 5
                $fields,                    // 6
                $sorting,                   // 7
                $join,                      // 8
                $strColNameAlias            // 9
                // @codingStandardsIgnoreEnd
            ))
                ->execute($this->get('id'));
        } elseif ($blnUsedOnly) {
            $objValueIds = $objDB->prepare(sprintf(
                'SELECT COUNT(value_id) as mm_count, value_id AS %1$s, %3$s.%8$s %5$s
                FROM tl_metamodel_tag_relation
                RIGHT JOIN %3$s ON(tl_metamodel_tag_relation.value_id=%3$s.%1$s)
                %7$s
                WHERE tl_metamodel_tag_relation.att_id=? %4$s
                GROUP BY tl_metamodel_tag_relation.value_id
                ORDER BY %6$s %3$s.%2$s',
                // @codingStandardsIgnoreStart - We want to keep the numbers as comment at the end of the following lines.
                $this->getIdColumn(),      // 1
                $this->getSortingColumn(), // 2
                $this->getTagSource(),     // 3
                $where,                    // 4
                $fields,                   // 5
                $sorting,                  // 6
                $join,                     // 7
                $strColNameAlias           // 8
                // @codingStandardsIgnoreEnd
            ))
                ->execute($this->get('id'));
        } else {
            $objValueIds = $objDB->prepare(sprintf(
                'SELECT COUNT(%1$s.%2$s) as mm_count, %1$s.%2$s, %1$s.%8$s %5$s
                FROM %1$s
                %7$s
                %4$s
                GROUP BY %1$s.%2$s
                ORDER BY %6$s %3$s',
                // @codingStandardsIgnoreStart - We want to keep the numbers as comment at the end of the following lines.
                $this->getTagSource(),     // 1
                $this->getIdColumn(),      // 2
                $this->getSortingColumn(), // 3
                $where
                    ? 'WHERE ' . substr($where, 4)
                    : '',                  // 4
                $fields,                   // 5
                $sorting,                  // 6
                $join,                     // 7
                $strColNameAlias           // 8
                // @codingStandardsIgnoreEnd
            ))
                ->execute();
        }

        return $this->convertValueIds($objValueIds, $arrCount);
    }

    /**
     * Fetch the values with the provided ids and given language.
     *
     * This method is mainly intended as a helper for
     * {@see MetaModelAttributeTranslatedTags::getFilterOptions()}
     *
     * @param int[]  $arrValueIds A list of value ids that the result shall be limited to.
     *
     * @param string $strLangCode The language code for which the values shall be retrieved.
     *
     * @return Result a database result containing all matching values.
     */
    protected function getValues($arrValueIds, $strLangCode)
    {
        $join    = false;
        $fields  = false;
        $sorting = false;
        $where   = $this->getWhereColumn()
            ? 'AND (' . $this->getWhereColumn() . ')'
            : false;
        if ($this->getTagSortSourceTable()) {
            $fields = ', ' . $this->getTagSortSourceTable() . '.*';
            $join   = sprintf(
                'JOIN %s ON %s.%s=%s.id',
                $this->getTagSortSourceTable(),
                $this->getTagSource(),
                $this->getIdColumn(),
                $this->getTagSortSourceTable()
            );

            if ($this->getTagSortSourceColumn(true)) {
                $sorting = $this->getTagSortSourceColumn(true) . ',';
            }
        }

        // Now for the retrieval, first with the real language.
        return $this->getDatabase()->prepare(sprintf(
            'SELECT %1$s.* %7$s
            FROM %1$s
            %9$s
            WHERE %1$s.%2$s IN (%3$s) AND (%1$s.%4$s=? %6$s)
            GROUP BY %1$s.%2$s
            ORDER BY %8$s %1$s.%5$s',
            // @codingStandardsIgnoreStart - We want to keep the numbers as comment at the end of the following lines.
            $this->getTagSource(),        // 1
            $this->getIdColumn(),         // 2
            implode(',', $arrValueIds),   // 3
            $this->getTagLangColumn(),    // 4
            $this->getSortingColumn(),    // 5
            $where,                       // 6
            $fields,                      // 7
            $sorting,                     // 8
            $join                         // 9
            // @codingStandardsIgnoreEnd
        ))
            ->execute($strLangCode);
    }

    /**
     * {@inheritDoc}
     */
    public function getAttributeSettingNames()
    {
        return array_merge(parent::getAttributeSettingNames(), array(
            'tag_langcolumn', 'tag_srctable', 'tag_srcsorting'
        ));
    }

    /**
     * {@inheritDoc}
     */
    public function getFilterOptions($idList, $usedOnly, &$arrCount = null)
    {
        if (!$this->getTagSource() && $this->getIdColumn()) {
            return array();
        }

        $arrReturn    = array();
        $strColNameId = $this->getIdColumn();

        // Fetch the value ids.
        $arrValueIds = $this->getValueIds($idList, $usedOnly, $arrCount);
        if (!count($arrValueIds)) {
            return $arrReturn;
        }

        $strColNameValue = $this->getValueColumn();
        $strColNameAlias = $this->getAliasColumn();

        // Now for the retrieval, first with the real language.
        $objValue             = $this->getValues($arrValueIds, $this->getMetaModel()->getActiveLanguage());
        $arrValueIdsRetrieved = array();
        while ($objValue->next()) {
            $arrValueIdsRetrieved[]                 = $objValue->$strColNameId;
            $arrReturn[$objValue->$strColNameAlias] = $objValue->$strColNameValue;
        }
        // Determine missing ids.
        $arrValueIds = array_diff($arrValueIds, $arrValueIdsRetrieved);
        // If there are missing ids and the fallback language is different than the current language, then fetch
        // those now.
        if ($arrValueIds
            && ($this->getMetaModel()->getFallbackLanguage() != $this->getMetaModel()->getActiveLanguage())
        ) {
            $objValue = $this->getValues($arrValueIds, $this->getMetaModel()->getFallbackLanguage());
            while ($objValue->next()) {
                $arrReturn[$objValue->$strColNameAlias] = $objValue->$strColNameValue;
            }
        }

        return $arrReturn;
    }

    /**
     * {@inheritDoc}
     */
    public function getDataFor($arrIds)
    {
        $strActiveLanguage   = $this->getMetaModel()->getActiveLanguage();
        $strFallbackLanguage = $this->getMetaModel()->getFallbackLanguage();

        $arrReturn   = $this->getTranslatedDataFor($arrIds, $strActiveLanguage);
        $arrTagCount = $this->getTagCount($arrIds);

        // Check if we got all tags.
        foreach ($arrReturn as $key => $results) {
            // Remove matching tags.
            if (count($results) == $arrTagCount[$key]) {
                unset($arrTagCount[$key]);
            }
        }

        $arrFallbackIds = array_keys($arrTagCount);

        // Second round, fetch fallback languages if not all items could be resolved.
        if ((count($arrFallbackIds) > 0) && ($strActiveLanguage != $strFallbackLanguage)) {
            $arrFallbackData = $this->getTranslatedDataFor($arrFallbackIds, $strFallbackLanguage);

            // Cannot use array_merge here as it would renumber the keys.
            foreach ($arrFallbackData as $intId => $arrTransValue) {
                foreach ($arrTransValue as $intTransID => $arrValue) {
                    if (!$arrReturn[$intId][$intTransID]) {
                        $arrReturn[$intId][$intTransID] = $arrValue;
                    }
                }
            }
        }
        return $arrReturn;
    }

    /**
     * {@inheritdoc}
     */
    public function searchFor($strPattern)
    {
        return $this->searchForInLanguages($strPattern, array($this->getMetaModel()->getActiveLanguage()));
    }

    /**
     * {@inheritDoc}
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function setTranslatedDataFor($arrValues, $strLangCode)
    {
        // Although we are translated, we do not manipulate tertiary tables
        // in this attribute. Updating the reference table from plain setDataFor
        // will do just fine.
        $this->setDataFor($arrValues);
    }

    /**
     * {@inheritDoc}
     */
    public function getTranslatedDataFor($arrIds, $strLangCode)
    {
        $objDB              = $this->getDatabase();
        $strTableName       = $this->getTagSource();
        $strColNameId       = $this->getIdColumn();
        $strColNameLangCode = $this->getTagLangColumn();
        $strSortColumn      = $this->getSortingColumn();

        if (!$this->isProperlyConfigured()) {
            return array();
        }

        $metaModelItemId = $this->getMetaModel()->getTableName() . '_id';

        $join  = false;
        $field = false;
        $where = $this->getWhereColumn()
            ? 'AND (' . $this->getWhereColumn() . ')'
            : false;
        if ($this->getTagSortSourceTable()) {
            $join = sprintf(
                'JOIN %1$s ON %2$s.%3$s=%1$s.id',
                $this->getTagSortSourceTable(),
                $strTableName,
                $strColNameId
            );

            if ($this->getTagSortSourceColumn(true)) {
                $field = ', ' . $this->getTagSortSourceColumn(true);
            }
        }

        $objValue = $objDB->prepare(sprintf(
            'SELECT %1$s.*, tl_metamodel_tag_relation.item_id AS %2$s %8$s
            FROM %1$s
            LEFT JOIN tl_metamodel_tag_relation ON (
                (tl_metamodel_tag_relation.att_id=?)
                AND (tl_metamodel_tag_relation.value_id=%1$s.%3$s)
                AND (%1$s.%5$s=?)
            )
            %9$s
            WHERE tl_metamodel_tag_relation.item_id IN (%4$s) %7$s
            ORDER BY %10$s %6$s',
            // @codingStandardsIgnoreStart - We want to keep the numbers as comment at the end of the following lines.
            $strTableName,                   // 1
            $metaModelItemId,                // 2
            $strColNameId,                   // 3
            implode(',', $arrIds),           // 4
            $strColNameLangCode,             // 5
            $strSortColumn,                  // 6
            $where,                          // 7
            $field,                          // 8
            $join,                           // 9
            ($field ? 'srcsorting,' : false) //10
            // @codingStandardsIgnoreEnd
        ))
            ->execute($this->get('id'), $strLangCode);

        return $this->convertRows($objValue, $metaModelItemId, $strColNameId);
    }

    /**
     * {@inheritDoc}
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function unsetValueFor($arrIds, $strLangCode)
    {
        // We can not simply unset only one language. When un-setting, all languages will disappear.
        $this->unsetDataFor($arrIds);
    }

    /**
     * {@inheritdoc}
     */
    public function searchForInLanguages($strPattern, $arrLanguages = array())
    {
        $arrParams          = array($strPattern, $strPattern);
        $strTableName       = $this->getTagSource();
        $strColNameId       = $this->getIdColumn();
        $strColNameLangCode = $this->getTagLangColumn();
        $strColumn          = $this->getValueColumn();
        $strColAlias        = $this->getAliasColumn();

        $languages = '';
        if ($arrLanguages) {
            $languages = sprintf(' AND %s IN (\'%s\')', $strColNameLangCode, implode('\',\'', $arrLanguages));
        }

        $objFilterRule = new SimpleQuery(
            sprintf(
                'SELECT item_id
                FROM tl_metamodel_tag_relation
                WHERE value_id IN (
                    SELECT DISTINCT %1$s
                    FROM %2$s
                    WHERE %3$s LIKE ?
                    OR %6$s LIKE ?%4$s
                ) AND att_id = %5$s',
                // @codingStandardsIgnoreStart - We want to keep the numbers as comment at the end of the following lines.
                $strColNameId,    // 1
                $strTableName,    // 2
                $strColumn,       // 3
                $languages,       // 4
                $this->get('id'), // 5
                $strColAlias      // 6
                // @codingStandardsIgnoreEnd
            ),
            $arrParams,
            'item_id',
            $this->getDatabase()
        );

        return $objFilterRule->getMatchingIds();
    }

    /**
     * Convert the database result to an result array.
     *
     * @param Result $dbResult    The database result.
     * @param string $idColumn    The id column name.
     * @param string $valueColumn The value column name.
     *
     * @return array
     */
    private function convertRows(Result $dbResult, $idColumn, $valueColumn)
    {
        $result = [];
        while ($dbResult->next()) {
            if (!isset($result[$dbResult->$idColumn])) {
                $result[$dbResult->$idColumn] = [];
            }
            $data = $dbResult->row();
            unset($data[$idColumn]);
            $result[$dbResult->$idColumn][$dbResult->$valueColumn] = $data;
        }

        return $result;
    }
}
