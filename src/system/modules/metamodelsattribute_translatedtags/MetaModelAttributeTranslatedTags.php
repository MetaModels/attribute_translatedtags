<?php
/**
 * The MetaModels extension allows the creation of multiple collections of custom items,
 * each with its own unique set of selectable attributes, with attribute extendability.
 * The Front-End modules allow you to build powerful listing and filtering of the
 * data in each collection.
 *
 * PHP version 5
 * @package	   MetaModels
 * @subpackage AttributeTranslatedTags
 * @author     Christian Schiffler <c.schiffler@cyberspectrum.de>
 * @copyright  The MetaModels team.
 * @license    LGPL.
 * @filesource
 */
if (!defined('TL_ROOT'))
{
	die('You cannot access this file directly!');
}

/**
 * This is the MetaModelAttribute class for handling tag attributes.
 *
 * @package	   MetaModels
 * @subpackage AttributeTags
 * @author     Christian Schiffler <c.schiffler@cyberspectrum.de>
 */
class MetaModelAttributeTranslatedTags extends MetaModelAttributeTags implements IMetaModelAttributeTranslated
{
	/**
	 * Get numbers of tag for the given ids.
	 */
	public function getTagCount($arrIds)
	{
		$objDB = Database::getInstance();
		$strTableName = $this->get('tag_table');
		$strColNameId = $this->get('tag_id');
		$arrReturn = array();

		if ($strTableName && $strColNameId)
		{
			$strMetaModelTableName = $this->getMetaModel()->getTableName();
			$strMetaModelTableNameId = $strMetaModelTableName.'_id';

			$objValue = $objDB->prepare(sprintf(
					'SELECT `item_id`, count(*) as count FROM `tl_metamodel_tag_relation`
						WHERE att_id = ? AND item_id IN (%1$s) group BY `item_id`',
					implode(',', $arrIds) // 1
					))
			->execute($this->get('id'));

			while ($objValue->next())
			{

				if(!$arrReturn[$objValue->item_id])
				{
					$arrReturn[$objValue->item_id] = array();
				}
				$arrReturn[$objValue->item_id] = $objValue->count;
			}
		}
		return $arrReturn;
	}

	/**
	 * Fetch the ids of options optionally limited to the items with the provided ids.
	 * NOTE: this does not take the actual availablility of an value in the current or
	 * fallback languages into account.
	 * This method is mainly intended as a helper for
	 * {@see MetaModelAttributeTranslatedTags::getFilterOptions()}
	 *
	 * @param int[] $arrIds a list of item ids that the result shall be limited to.
	 *
	 * @return int[] a list of all matching value ids.
	 */
	protected function getValueIds($arrIds = array())
	{
		// first off, we need to determine the option ids in the foreign table.
		$objDB = Database::getInstance();
		if ($arrIds)
		{
			$objValueIds = $objDB->prepare(sprintf('
				SELECT %1$s.%2$s
				FROM %1$s
				LEFT JOIN tl_metamodel_tag_relation ON (
					(tl_metamodel_tag_relation.att_id=?)
					AND (tl_metamodel_tag_relation.value_id=%1$s.%2$s)
				)
				WHERE tl_metamodel_tag_relation.item_id IN (%3$s) GROUP BY %1$s.%2$s',
				$this->get('tag_table'), // 1
				$this->get('tag_id'), // 2
				implode(',', $arrIds) // 3
			))
			->execute($this->get('id'));
		} else {
			$objValueIds = $objDB->prepare(sprintf('
				SELECT %1$s.%2$s
				FROM %1$s GROUP BY %1$s.%2$s',
				$this->get('tag_table'), // 1
				$this->get('tag_id') // 2
			))
			->execute();
		}
		return $objValueIds->fetchEach($this->get('tag_id'));
	}

	/**
	 * Fetch the values with the provided ids and given language.
	 * This method is mainly intended as a helper for
	 * {@see MetaModelAttributeTranslatedTags::getFilterOptions()}
	 *
	 * @param int[]  $arrValueIds a list of value ids that the result shall be limited to.
	 *
	 * @param string $strLangCode the language code for which the values shall be retrieved.
	 *
	 * @return Database_Result a database result containing all matching values.
	 */
	protected function getValues($arrValueIds, $strLangCode)
	{
		// now for the retrival, first with the real language.
		return Database::getInstance()->prepare(sprintf('
			SELECT %1$s.*
			FROM %1$s
			WHERE %1$s.%2$s IN (%3$s) AND (%1$s.%4$s=?)
			GROUP BY %1$s.%2$s',
			$this->get('tag_table'), // 1
			$this->get('tag_id'), // 2
			implode(',', $arrValueIds), // 3
			$this->get('tag_langcolumn')
		))
		->execute($strLangCode);
	}

	/////////////////////////////////////////////////////////////////
	// interface IMetaModelAttribute
	/////////////////////////////////////////////////////////////////

	public function getAttributeSettingNames()
	{
		return array_merge(parent::getAttributeSettingNames(), array(
			'tag_langcolumn'
		));
	}

	/**
	 * {@inheritdoc}
	 *
	 * Fetch filter options from foreign table.
	 *
	 */
	public function getFilterOptions($arrIds, $usedOnly)
	{
		$arrReturn = array();

		if ($this->get('tag_table') && ($strColNameId = $this->get('tag_id')))
		{
			// fetch the value ids
			$arrValueIds = $this->getValueIds($arrIds);

			$strColNameValue = $this->get('tag_column');
			$strColNameAlias = $this->getAliasCol();

			// now for the retrival, first with the real language.
			$objValue = $this->getValues($arrValueIds, $this->getMetaModel()->getActiveLanguage());
			$arrValueIdsRetrieved = array();
			while ($objValue->next())
			{
				$arrValueIdsRetrieved[] = $objValue->$strColNameId;
				$arrReturn[$objValue->$strColNameAlias] = $objValue->$strColNameValue;
			}
			// determine missing ids.
			$arrValueIds = array_diff($arrValueIds, $arrValueIdsRetrieved);
			// if there are missing ids and the fallback language is different than the current language, then fetch those now.
			if ($arrValueIds && ($this->getMetaModel()->getFallbackLanguage() != $this->getMetaModel()->getActiveLanguage()))
			{
				$objValue = $this->getValues($arrValueIds, $this->getMetaModel()->getFallbackLanguage());
				while ($objValue->next())
				{
					$arrReturn[$objValue->$strColNameAlias] = $objValue->$strColNameValue;
				}
			}
			// finally sort the result by the value to have an alphabetical list.
			asort($arrReturn);
		}
		return $arrReturn;
	}

	/////////////////////////////////////////////////////////////////
	// interface IMetaModelAttributeComplex
	/////////////////////////////////////////////////////////////////

	public function getDataFor($arrIds)
	{
		$strActiveLanguage = $this->getMetaModel()->getActiveLanguage();
		$strFallbackLanguage = $this->getMetaModel()->getFallbackLanguage();

		$arrReturn = $this->getTranslatedDataFor($arrIds, $strActiveLanguage);
		$arrTagCount = $this->getTagCount($arrIds);

		$arrFallbackIds = array();

		//check if we got all tags
		foreach ($arrReturn as $key => $results)
		{
			// remove matching tags
			if (count($results) == $arrTagCount[$key])
			{
				unset($arrTagCount[$key]);
			}
		}

		$arrFallbackIds = array_keys($arrTagCount);

		// second round, fetch fallback languages if not all items could be resolved.
		if ((count($arrFallbackIds) > 0) && ($strActiveLanguage != $strFallbackLanguage))
		{

			$arrFallbackData = $this->getTranslatedDataFor($arrFallbackIds, $strFallbackLanguage);

			// cannot use array_merge here as it would renumber the keys.
			foreach ($arrFallbackData as $intId => $arrTransValue)
			{
				foreach ($arrTransValue as $intTransID => $arrValue)
				{
					if (!$arrReturn[$intId][$intTransID])
					{
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

	/////////////////////////////////////////////////////////////////
	// interface IMetaModelAttributeTranslated
	/////////////////////////////////////////////////////////////////

	public function setTranslatedDataFor($arrValues, $strLangCode)
	{
		// although we are translated, we do not manipulate tertiary tables
		// in this attribute. Updating the reference table from plain setDataFor
		// will do just fine.
		$this->setDataFor($arrValues);
	}

	/**
	 * Get values for the given items in a certain language.
	 */
	public function getTranslatedDataFor($arrIds, $strLangCode)
	{
		$objDB = Database::getInstance();
		$strTableName = $this->get('tag_table');
		$strColNameId = $this->get('tag_id');
		$strColNameLangCode = $this->get('tag_langcolumn');
		$strSortColumn = $this->get('tag_sorting');
		$arrReturn = array();

		if ($strTableName && $strColNameId && $strColNameLangCode)
		{
			$strMetaModelTableName = $this->getMetaModel()->getTableName();
			$strMetaModelTableNameId = $strMetaModelTableName.'_id';

			$objValue = $objDB->prepare(sprintf('
				SELECT %1$s.*, tl_metamodel_tag_relation.item_id AS %2$s
				FROM %1$s
				LEFT JOIN tl_metamodel_tag_relation ON (
					(tl_metamodel_tag_relation.att_id=?)
					AND (tl_metamodel_tag_relation.value_id=%1$s.%3$s)
					AND (%1$s.%5$s=?)
				)
				WHERE tl_metamodel_tag_relation.item_id IN (%4$s)',
				$strTableName, // 1
				$strMetaModelTableNameId, // 2
				$strColNameId, // 3
				implode(',', $arrIds), // 4
				$strColNameLangCode // 5
			))
			->execute($this->get('id'), $strLangCode);
			while ($objValue->next())
			{

				if(!$arrReturn[$objValue->$strMetaModelTableNameId])
				{
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
	 * Remove values for items in a certain lanugage.
	 */
	public function unsetValueFor($arrIds, $strLangCode)
	{
		// FIXME: unimplemented
		throw new Exception('MetaModelAttributeTranslatedTags::unsetValueFor() is not yet implemented, please do it or find someone who can!', 1);
	}

	/**
	 * {@inheritdoc}
	 */
	public function searchForInLanguages($strPattern, $arrLanguages = array())
	{
		$arrParams = array($strPattern, $strPattern);

		$strTableName = $this->get('tag_table');
		$strColNameId = $this->get('tag_id');
		$strColNameLangCode = $this->get('tag_langcolumn');
		$strColumn = $this->get('tag_column');
		$strColAlias = $this->get('tag_alias') ? $this->get('tag_alias') : $strColNameId;
        
		$objFilterRule = new MetaModelFilterRuleSimpleQuery(
			sprintf('SELECT item_id FROM tl_metamodel_tag_relation WHERE value_id IN (SELECT DISTINCT %1$s FROM %2$s WHERE %3$s LIKE ? OR %6$s LIKE ?%4$s) AND att_id = %5$s',
				$strColNameId,  //1
				$strTableName,  //2
				$strColumn,     //3
				$arrLanguages ? sprintf(' AND %s IN (\'%s\')',$strColNameLangCode,implode('\',\'', $arrLanguages)) : '',    //4
				$this->get('id'),   //5
				$strColAlias    //6
			),
			$arrParams,
			'item_id'
		);

		return $objFilterRule->getMatchingIds();
	}
}

?>