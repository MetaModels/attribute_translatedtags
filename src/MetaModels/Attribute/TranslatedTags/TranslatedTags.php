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

namespace MetaModels\Attribute\TranslatedTags;

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
     * Determine the amount of entries in the relation table for this attribute and the given value ids.
     *
     * @param int[] $arrIds The ids of the items for which the tag count shall be determined.
     *
     * @return array
     */
    public function getTagCount($arrIds)
    {
        $objDB        = \Database::getInstance();
        $strTableName = $this->get('tag_table');
        $strColNameId = $this->get('tag_id');
        $arrReturn    = array();

        if ($strTableName && $strColNameId) {
            $objValue = $objDB->prepare(sprintf(
                'SELECT `item_id`, count(*) as count FROM `tl_metamodel_tag_relation`
                    WHERE att_id = ? AND item_id IN (%1$s) group BY `item_id`',
                implode(',', $arrIds)
            ))
                ->execute($this->get('id'));

            while ($objValue->next()) {

                if (!$arrReturn[$objValue->item_id]) {
                    $arrReturn[$objValue->item_id] = array();
                }
                $arrReturn[$objValue->item_id] = $objValue->count;
            }
        }
        return $arrReturn;
    }

    /**
     * Fetch the ids of options optionally limited to the items with the provided ids.
     *
     * NOTE: this does not take the actual availability of an value in the current or
     * fallback languages into account.
     * This method is mainly intended as a helper for
     * {@see MetaModelAttributeTranslatedTags::getFilterOptions()}
     *
     * @param int[] $arrIds      A list of item ids that the result shall be limited to.
     *
     * @param bool  $blnUsedOnly Do only return ids that have matches in the real table.
     *
     * @param null  $arrCount    Array to where the amount of items per tag shall be stored. May be null to return
     *                           nothing.
     *
     * @return int[] a list of all matching value ids.
     */
    protected function getValueIds($arrIds, $blnUsedOnly, &$arrCount = null)
    {
        if ($arrIds === array()) {
            return array();
        }

        // Get name of the alias column.
        $strColNameAlias = $this->getAliasCol();

        // First off, we need to determine the option ids in the foreign table.
        $objDB = \Database::getInstance();

        $join    = false;
        $fields  = false;
        $sorting = false;
        $where   = ($this->get('tag_where')
            ? 'AND ('.html_entity_decode($this->get('tag_where').')')
            : false);
        if ($this->get('tag_srctable')) {
            $fields = ', ' . $this->get('tag_srctable') . '.*';
            $join   = sprintf(
                'JOIN %s ON %s.%s=%s.id',
                $this->get('tag_srctable'),
                $this->get('tag_table'),
                $this->get('tag_id'),
                $this->get('tag_srctable')
            );

            if ($this->get('tag_srcsorting')) {
                $sorting = $this->get('tag_srctable') . '.' . $this->get('tag_srcsorting') . ',';
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
                $this->get('tag_table'),    // 1
                $this->get('tag_id'),       // 2
                implode(',', $arrIds),      // 3
                $this->get('tag_sorting'),  // 4
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
                $this->get('tag_id'),      // 1
                $this->get('tag_sorting'), // 2
                $this->get('tag_table'),   // 3
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
                $this->get('tag_table'),   // 1
                $this->get('tag_id'),      // 2
                $this->get('tag_sorting'), // 3
                $where,                    // 4
                $fields,                   // 5
                $sorting,                  // 6
                $join,                     // 7
                $strColNameAlias           // 8
                // @codingStandardsIgnoreEnd
            ))
                ->execute();
        }

        $arrReturn = array();
        $strField  = $this->get('tag_id');

        while ($objValueIds->next()) {
            $intID    = $objValueIds->$strField;
            $strAlias = $objValueIds->$strColNameAlias;

            $arrReturn[] = $intID;

            // Count.
            if (is_array($arrCount)) {
                $objCount = $objDB
                        ->prepare(
                            'SELECT COUNT(value_id) as mm_count
                            FROM tl_metamodel_tag_relation
                            WHERE att_id=?
                                AND value_id=?'
                        )
                        ->execute($this->get('id'), $intID);

                $arrCount[$intID]    = $objCount->mm_count;
                $arrCount[$strAlias] = $objCount->mm_count;
            }
        }

        return $arrReturn;
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
     * @return \Database\Result a database result containing all matching values.
     */
    protected function getValues($arrValueIds, $strLangCode)
    {
        $join    = false;
        $fields  = false;
        $sorting = false;
        $where   = ($this->get('tag_where')
            ? 'AND ('.html_entity_decode($this->get('tag_where').')')
            : false);
        if ($this->get('tag_srctable')) {
            $fields = ', ' . $this->get('tag_srctable') . '.*';
            $join   = sprintf(
                'JOIN %s ON %s.%s=%s.id',
                $this->get('tag_srctable'),
                $this->get('tag_table'),
                $this->get('tag_id'),
                $this->get('tag_srctable')
            );

            if ($this->get('tag_srcsorting')) {
                $sorting = $this->get('tag_srctable') . '.' . $this->get('tag_srcsorting') . ',';
            }
        }

        // Now for the retrieval, first with the real language.
        return \Database::getInstance()->prepare(sprintf(
            'SELECT %1$s.* %7$s
            FROM %1$s
            %9$s
            WHERE %1$s.%2$s IN (%3$s) AND (%1$s.%4$s=? %6$s)
            GROUP BY %1$s.%2$s
            ORDER BY %8$s %1$s.%5$s',
            // @codingStandardsIgnoreStart - We want to keep the numbers as comment at the end of the following lines.
            $this->get('tag_table'),      // 1
            $this->get('tag_id'),         // 2
            implode(',', $arrValueIds),   // 3
            $this->get('tag_langcolumn'), // 4
            $this->get('tag_sorting'),    // 5
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
    public function getFilterOptions($arrIds, $usedOnly, &$arrCount = null)
    {
        $arrReturn = array();

        if ($this->get('tag_table') && ($strColNameId = $this->get('tag_id'))) {
            // Fetch the value ids.
            $arrValueIds = $this->getValueIds($arrIds, $usedOnly, $arrCount);
            if (!count($arrValueIds)) {
                return $arrReturn;
            }

            $strColNameValue = $this->get('tag_column');
            $strColNameAlias = $this->getAliasCol();

            // Now for the retrival, first with the real language.
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
     */
    // @codingStandardsIgnoreStart - Accept that parameter $strLangCode is not used.
    public function setTranslatedDataFor($arrValues, $strLangCode)
    {
        // Although we are translated, we do not manipulate tertiary tables
        // in this attribute. Updating the reference table from plain setDataFor
        // will do just fine.
        $this->setDataFor($arrValues);
    }
    // @codingStandardsIgnoreEnd

    /**
     * {@inheritDoc}
     */
    public function getTranslatedDataFor($arrIds, $strLangCode)
    {
        $objDB              = \Database::getInstance();
        $strTableName       = $this->get('tag_table');
        $strColNameId       = $this->get('tag_id');
        $strColNameLangCode = $this->get('tag_langcolumn');
        $strSortColumn      = $this->get('tag_sorting');
        $arrReturn          = array();

        if ($strTableName && $strColNameId && $strColNameLangCode) {
            $strMetaModelTableName   = $this->getMetaModel()->getTableName();
            $strMetaModelTableNameId = $strMetaModelTableName.'_id';

            $join  = false;
            $field = false;
            $where = ($this->get('tag_where')
                ? 'AND ('.html_entity_decode($this->get('tag_where').')')
                : false);
            if ($this->get('tag_srctable')) {
                $join = sprintf(
                    'JOIN %s ON %s.%s=%s.id',
                    $this->get('tag_srctable'),
                    $this->get('tag_table'),
                    $this->get('tag_id'),
                    $this->get('tag_srctable')
                );

                if ($this->get('tag_srcsorting')) {
                    $field = ', ' . $this->get('tag_srctable') . '.' . $this->get('tag_srcsorting');
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
                $strMetaModelTableNameId,        // 2
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
            while ($objValue->next()) {

                if (!$arrReturn[$objValue->$strMetaModelTableNameId]) {
                    $arrReturn[$objValue->$strMetaModelTableNameId] = array();
                }
                $arrData = $objValue->row();
                unset($arrData[$strMetaModelTableNameId]);
                $arrReturn[$objValue->$strMetaModelTableNameId][$objValue->$strColNameId] = $arrData;
            }
        }

        return $arrReturn;
    }

    /**
     * {@inheritDoc}
     *
     * @throws \RuntimeException as it is currently unimplemented.
     */
    public function unsetValueFor($arrIds, $strLangCode)
    {
        // FIXME: unsetValueFor() is unimplemented.
        throw new \RuntimeException(
            'MetaModelAttributeTranslatedTags::unsetValueFor() is not yet implemented, ' .
            'please do it or find someone who can!',
            1
        );
    }

    /**
     * {@inheritdoc}
     */
    public function searchForInLanguages($strPattern, $arrLanguages = array())
    {
        $arrParams          = array($strPattern, $strPattern);
        $strTableName       = $this->get('tag_table');
        $strColNameId       = $this->get('tag_id');
        $strColNameLangCode = $this->get('tag_langcolumn');
        $strColumn          = $this->get('tag_column');
        $strColAlias        = $this->get('tag_alias') ? $this->get('tag_alias') : $strColNameId;

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
            'item_id'
        );

        return $objFilterRule->getMatchingIds();
    }
}