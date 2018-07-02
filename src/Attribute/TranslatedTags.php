<?php

/**
 * This file is part of MetaModels/attribute_translatedtags.
 *
 * (c) 2012-2018 The MetaModels team.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * This project is provided in good faith and hope to be usable by anyone.
 *
 * @package    MetaModels
 * @subpackage AttributeTranslatedTags
 * @author     Christian Schiffler <c.schiffler@cyberspectrum.de>
 * @author     Stefan Heimes <stefan_heimes@hotmail.com>
 * @author     Andreas Isaak <info@andreas-isaak.de>
 * @author     David Maack <david.maack@arcor.de>
 * @author     Christian de la Haye <service@delahaye.de>
 * @author     Sven Baumann <baumann.sv@gmail.com>
 * @author     Richard Henkenjohann <richardhenkenjohann@googlemail.com>
 * @copyright  2012-2018 The MetaModels team.
 * @license    https://github.com/MetaModels/attribute_translatedtags/blob/master/LICENSE LGPL-3.0
 * @filesource
 */


namespace MetaModels\AttributeTranslatedTagsBundle\Attribute;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\Statement;
use MetaModels\Attribute\ITranslated;
use MetaModels\AttributeTagsBundle\Attribute\Tags;
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
     * @param string $prefix The prefix (e.g. table name) for a return value like "<table>.<column>".
     *
     * @return string
     */
    protected function getTagSortSourceColumn($prefix = null)
    {
        $column = $this->get('tag_srcsorting');
        if (!$column) {
            return null;
        }

        if (null !== $prefix) {
            return $prefix . '.' . $column;
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
        if (null !== ($sortTable = $this->getTagSortSourceTable())
            && !$this->getConnection()->getSchemaManager()->tablesExist([$sortTable])) {
            return false;
        }

        return (null !== $this->getTagLangColumn());
    }

    /**
     * Determine the amount of entries in the relation table for this attribute and the given value ids.
     *
     * @param int[] $ids The ids of the items for which the tag count shall be determined.
     *
     * @return int[] The counts in the array format 'item_id' => count
     */
    public function getTagCount($ids)
    {
        $tableName = $this->getTagSource();
        $colNameId = $this->getIdColumn();
        $return    = [];

        if ($tableName && $colNameId) {
            $statement = $this->getConnection()->createQueryBuilder()
                ->select('item_id', 'count(*) as count')
                ->from('tl_metamodel_tag_relation')
                ->where('att_id=:att')
                ->andWhere('item_id IN (:items)')
                ->groupBy('item_id')
                ->setParameter('att', $this->get('id'))
                ->setParameter('items', $ids, Connection::PARAM_INT_ARRAY)
                ->execute();

            while ($row = $statement->fetch(\PDO::FETCH_OBJ)) {
                $itemId = $row->item_id;

                $return[$itemId] = (int) $row->count;
            }
        }

        return $return;
    }

    /**
     * Convert the value ids to a result array.
     *
     * @param Statement  $valueResult The database result.
     *
     * @param null|array $counter     The destination for the counter values.
     *
     * @return int[] The value ids that are represented by the passed database statement.
     */
    protected function convertValueIds($valueResult, &$counter = null)
    {
        $result      = [];
        $aliases     = [];
        $idColumn    = $this->getIdColumn();
        $aliasColumn = $this->getAliasColumn();
        while ($row = $valueResult->fetch(\PDO::FETCH_OBJ)) {
            $valueId           = $row->$idColumn;
            $aliases[$valueId] = $row->$aliasColumn;
            $result[]          = $valueId;
        }

        if (($counter !== null) && !empty($result)) {
            $statement = $this->getConnection()->createQueryBuilder()
                ->select('value_id', 'COUNT(value_id) as mm_count')
                ->from('tl_metamodel_tag_relation')
                ->where('att_id=:att')
                ->andWhere('value_id IN (:values)')
                ->groupBy('item_id')
                ->setParameter('att', $this->get('id'))
                ->setParameter('values', $result, Connection::PARAM_STR_ARRAY)
                ->execute()
                ->fetch(\PDO::FETCH_OBJ);

            $amount  = $statement->mm_count;
            $valueId = $statement->value_id;
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
     * @param string[]|null $ids      A list of item ids that the result shall be limited to.
     *
     * @param bool          $usedOnly Do only return ids that have matches in the real table.
     *
     * @param null          $count    Array to where the amount of items per tag shall be stored. May be null to return
     *                                nothing.
     *
     * @return int[] a list of all matching value ids.
     *
     * @see TranslatedTags::getFilterOptions().
     *
     * @@SuppressWarnings(PHPMD.ExcessiveMethodLength)
     * @@SuppressWarnings(PHPMD.NPathComplexity)
     */
    protected function getValueIds($ids, $usedOnly, &$count = null)
    {
        if ([] === $ids) {
            return [];
        }

        $aliasColName = $this->getAliasColumn();

        // First off, we need to determine the option ids in the foreign table.
        $queryBuilder = $this->getConnection()->createQueryBuilder();

        if (null !== $ids) {
            $statement = $this->getConnection()->createQueryBuilder()
                ->select('COUNT(source.' . $this->getIdColumn() . ') AS mm_count')
                ->addSelect('source.' . $this->getIdColumn())
                ->addSelect('source.' . $aliasColName)
                ->from($this->getTagSource(), 'source')
                ->leftJoin(
                    'source',
                    'tl_metamodel_tag_relation',
                    'rel',
                    $queryBuilder->expr()->andX()->add('rel.att_id=:att')->add(
                        'rel.value_id=source.' . $this->getIdColumn()
                    )
                )
                ->where('rel.item_id IN (:items)')
                ->setParameter('att', $this->get('id'))
                ->setParameter('items', $ids, Connection::PARAM_STR_ARRAY);

            if ($this->getTagSortSourceTable()) {
                $statement
                    ->addSelect($this->getTagSortSourceTable() . '.*')
                    ->join(
                        'source',
                        $this->getTagSortSourceTable(),
                        'sort',
                        'source.' . $this->getIdColumn() . '=sort.id'
                    );

                if ($this->getTagSortSourceColumn()) {
                    $statement->orderBy($this->getTagSortSourceColumn('sort'));
                }
            }

            if ($this->getWhereColumn()) {
                $statement->andWhere('(' . $this->getWhereColumn() . ')');
            }

            $statement
                ->groupBy('source.' . $this->getIdColumn())
                ->addOrderBy('source.' . $this->getSortingColumn());

        } elseif ($usedOnly) {
            $statement = $this->getConnection()->createQueryBuilder()
                ->select('COUNT(value_id) AS mm_count')
                ->addSelect('value_id AS ' . $this->getIdColumn())
                ->addSelect($this->getTagSource() . '.' . $aliasColName)
                ->from('tl_metamodel_tag_relation', 'rel')
                ->rightJoin(
                    'rel',
                    $this->getTagSource(),
                    'source',
                    'rel.value_id=source.' . $this->getIdColumn()
                )
                ->where('rel.att_id=:att')
                ->setParameter('att', $this->get('id'));

            if ($this->getTagSortSourceTable()) {
                $statement
                    ->addSelect($this->getTagSortSourceTable() . '.*')
                    ->join(
                        'source',
                        $this->getTagSortSourceTable(),
                        'sort',
                        'source.' . $this->getIdColumn() . '=sort.id'
                    );

                if ($this->getTagSortSourceColumn()) {
                    $statement->orderBy($this->getTagSortSourceColumn('sort'));
                }
            }

            if ($this->getWhereColumn()) {
                $statement->andWhere('(' . $this->getWhereColumn() . ')');
            }

            $statement
                ->groupBy('rel.value_id')
                ->addOrderBy('source.' . $this->getSortingColumn());

        } else {
            $statement = $this->getConnection()->createQueryBuilder()
                ->select('COUNT(source.' . $this->getIdColumn() . ') AS mm_count')
                ->addSelect('source.' . $this->getIdColumn())
                ->addSelect('source.' . $aliasColName)
                ->from($this->getTagSource(), 'source');

            if ($this->getTagSortSourceTable()) {
                $statement
                    ->addSelect($this->getTagSortSourceTable() . '.*')
                    ->join(
                        'source',
                        $this->getTagSortSourceTable(),
                        'sort',
                        'source.' . $this->getIdColumn() . '=sort.id'
                    );

                if ($this->getTagSortSourceColumn()) {
                    $statement->orderBy($this->getTagSortSourceColumn('sort'));
                }
            }

            if ($this->getWhereColumn()) {
                $statement->where('(' . $this->getWhereColumn() . ')');
            }

            $statement
                ->groupBy('source.' . $this->getIdColumn())
                ->addOrderBy($this->getSortingColumn());
        }

        return $this->convertValueIds($statement->execute(), $count);
    }

    /**
     * Fetch the values with the provided ids and given language.
     *
     * This method is mainly intended as a helper for
     * {@see MetaModelAttributeTranslatedTags::getFilterOptions()}
     *
     * @param int[]  $valueIds A list of value ids that the result shall be limited to.
     *
     * @param string $language The language code for which the values shall be retrieved.
     *
     * @return Statement The database result containing all matching values.
     */
    protected function getValues($valueIds, $language)
    {
        $queryBuilder = $this->getConnection()->createQueryBuilder();
        $where        = $this->getWhereColumn()
            ? '(' . $this->getWhereColumn() . ')'
            : null;

        $statement = $this->getConnection()->createQueryBuilder()
            ->select('source.*')
            ->from($this->getTagSource(), 'source')
            ->where($queryBuilder->expr()->in('source.' . $this->getIdColumn(), $valueIds))
            ->andWhere(
                $queryBuilder->expr()->andX()->add('source.' . $this->getTagLangColumn() . '=:lang')->add($where)
            )
            ->setParameter('lang', $language)
            ->groupBy('source.' . $this->getIdColumn());


        if ($this->getTagSortSourceTable()) {
            $statement->addSelect($this->getTagSortSourceTable() . '.*');
            $statement->join(
                's',
                $this->getTagSortSourceTable(),
                'sort',
                $queryBuilder->expr()->eq('source.' . $this->getIdColumn(), 'sort.id')
            );

            if ($this->getTagSortSourceColumn()) {
                $statement->orderBy($this->getTagSortSourceColumn('sort'));
            }
        }

        $statement->addOrderBy('source.' . $this->getSortingColumn());

        return $statement->execute();
    }

    /**
     * {@inheritDoc}
     */
    public function getAttributeSettingNames()
    {
        return \array_merge(
            parent::getAttributeSettingNames(),
            [
                'tag_langcolumn',
                'tag_srctable',
                'tag_srcsorting',
            ]
        );
    }

    /**
     * {@inheritDoc}
     */
    public function getFilterOptions($idList, $usedOnly, &$arrCount = null)
    {
        if (!$this->getTagSource() && $this->getIdColumn()) {
            return [];
        }

        $return    = [];
        $idColName = $this->getIdColumn();

        // Fetch the value ids.
        $valueIds = $this->getValueIds($idList, $usedOnly, $arrCount);
        if (!\count($valueIds)) {
            return $return;
        }

        $valueColName = $this->getValueColumn();
        $aliasColName = $this->getAliasColumn();

        // Now for the retrieval, first with the real language.
        $values               = $this->getValues($valueIds, $this->getMetaModel()->getActiveLanguage());
        $arrValueIdsRetrieved = [];
        while ($row = $values->fetch(\PDO::FETCH_OBJ)) {
            $arrValueIdsRetrieved[]      = $row->$idColName;
            $return[$row->$aliasColName] = $row->$valueColName;
        }
        // Determine missing ids.
        $valueIds = array_diff($valueIds, $arrValueIdsRetrieved);
        // If there are missing ids and the fallback language is different than the current language, then fetch
        // those now.
        if ($valueIds
            && ($this->getMetaModel()->getFallbackLanguage() !== $this->getMetaModel()->getActiveLanguage())) {
            $values = $this->getValues($valueIds, $this->getMetaModel()->getFallbackLanguage());
            while ($row = $values->fetch(\PDO::FETCH_OBJ)) {
                $return[$row->$aliasColName] = $row->$valueColName;
            }
        }

        return $return;
    }

    /**
     * {@inheritDoc}
     */
    public function getDataFor($arrIds)
    {
        $activeLanguage   = $this->getMetaModel()->getActiveLanguage();
        $fallbackLanguage = $this->getMetaModel()->getFallbackLanguage();

        $return   = $this->getTranslatedDataFor($arrIds, $activeLanguage);
        $tagCount = $this->getTagCount($arrIds);

        // Check if we got all tags.
        foreach ($return as $key => $results) {
            // Remove matching tags.
            if (\count($results) === $tagCount[$key]) {
                unset($tagCount[$key]);
            }
        }

        $arrFallbackIds = \array_keys($tagCount);

        // Second round, fetch fallback languages if not all items could be resolved.
        if (($activeLanguage !== $fallbackLanguage) && (\count($arrFallbackIds) > 0)) {
            // Cannot use array_merge here as it would renumber the keys.
            foreach ($this->getTranslatedDataFor($arrFallbackIds, $fallbackLanguage) as $id => $transValue) {
                foreach ((array) $transValue as $transId => $value) {
                    if (!$return[$id][$transId]) {
                        $return[$id][$transId] = $value;
                    }
                }
            }
        }

        return $return;
    }

    /**
     * {@inheritdoc}
     */
    public function searchFor($strPattern)
    {
        return $this->searchForInLanguages($strPattern, [$this->getMetaModel()->getActiveLanguage()]);
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
        $tableName       = $this->getTagSource();
        $idColName       = $this->getIdColumn();
        $langCodeColName = $this->getTagLangColumn();
        $sortColumn      = $this->getSortingColumn();

        if (!$this->isProperlyConfigured()) {
            return [];
        }

        $metaModelItemId = $this->getMetaModel()->getTableName() . '_id';

        $queryBuilder = $this->getConnection()->createQueryBuilder();

        $statement = $this->getConnection()->createQueryBuilder()
            ->select('v.*', 'r.item_id AS ' . $metaModelItemId)
            ->from($tableName, 'v')
            ->leftJoin(
                'v',
                'tl_metamodel_tag_relation',
                'r',
                $queryBuilder->expr()->andX()
                    ->add('r.att_id=:att')
                    ->add('r.value_id=v.' . $idColName)
                    ->add('v.' . $langCodeColName . '=:langcode')
            )
            ->where('r.item_id IN (:ids)')
            ->setParameter('att', $this->get('id'))
            ->setParameter('ids', $arrIds, Connection::PARAM_STR_ARRAY)
            ->setParameter('langcode', $strLangCode);

        if ($this->getTagSortSourceTable()) {
            $statement->join(
                'v',
                $this->getTagSortSourceTable(),
                's',
                $queryBuilder->expr()->eq('v.' . $idColName, 's.id')
            );

            if ($this->getTagSortSourceColumn()) {
                $statement->addSelect($this->getTagSortSourceColumn('s'));
                $statement->orderBy('srcsorting');
            }
        }

        if ($this->getWhereColumn()) {
            $statement->andWhere('(' . $this->getWhereColumn() . ')');
        }

        $statement->addOrderBy($sortColumn);

        return $this->convertRows($statement->execute(), $metaModelItemId, $idColName);
    }

    /**
     * Remove values for items in a certain language.
     *
     * @param string[] $arrIds      The ids for which values shall be removed.
     *
     * @param string   $strLangCode The language code for which the data shall be removed.
     *
     * @return void
     *
     * @throws \RuntimeException When an invalid id array has been passed.
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
    public function searchForInLanguages($pattern, $arrLanguages = [])
    {
        $tableName       = $this->getTagSource();
        $idColName       = $this->getIdColumn();
        $langCodeColName = $this->getTagLangColumn();
        $valueColumn     = $this->getValueColumn();
        $aliasColumn     = $this->getAliasColumn();

        $queryBuilder = $this->getConnection()->createQueryBuilder();

        $queryAndLanguages = null;
        if ($arrLanguages) {
            $queryAndLanguages = $queryBuilder->expr()->in($langCodeColName, $arrLanguages);
        }

        $builder = $this->getConnection()->createQueryBuilder()
            ->select('item_id')
            ->from('tl_metamodel_tag_relation')
            ->where(
                $queryBuilder->expr()->in(
                    'value_id',
                    $queryBuilder
                        ->select('DISTINCT ' . $idColName)
                        ->from($tableName)
                        ->where($queryBuilder->expr()->like($valueColumn, $pattern))
                        ->orWhere($queryBuilder->expr()->like($aliasColumn, $pattern))
                        ->andWhere($queryAndLanguages)
                        ->getSQL()
                )
            )
            ->andWhere('att_id=:att')
            ->setParameter('att', $this->get('id'));

        $filterRule = SimpleQuery::createFromQueryBuilder($builder, 'item_id');

        return $filterRule->getMatchingIds();
    }

    /**
     * Convert the database result to an result array.
     *
     * @param Statement $dbResult    The database result.
     * @param string    $idColumn    The id column name.
     * @param string    $valueColumn The value column name.
     *
     * @return array
     */
    private function convertRows(Statement $dbResult, $idColumn, $valueColumn)
    {
        $result = [];
        while ($row = $dbResult->fetch(\PDO::FETCH_ASSOC)) {
            if (!isset($result[$row[$idColumn]])) {
                $result[$row[$idColumn]] = [];
            }

            $data = $row;
            unset($data[$idColumn]);
            $result[$row[$idColumn]][$row[$valueColumn]] = $data;
        }

        return $result;
    }
}
