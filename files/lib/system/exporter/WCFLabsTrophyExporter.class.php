<?php
namespace wcf\system\exporter;
use wcf\data\language\LanguageList;
use wcf\data\trophy\Trophy;
use wcf\system\database\util\PreparedStatementConditionBuilder;
use wcf\system\importer\ImportHandler;

/**
 * Exports trophies from the old WCFLabs trophy system.
 *
 * @author	Joshua Ruesweg
 * @copyright	2015-2017 WCFLabs.de
 * @license	GNU Lesser General Public License <http://opensource.org/licenses/lgpl-license.php>
 * @package	WCFLabs\System\Exporter\Trophy
 */
class WCFLabsTrophyExporter extends AbstractExporter {
	/**
	 * wcf installation number
	 * @var	integer
	 */
	protected $dbNo = 0;
	
	/**
	 * The language ids. 
	 * @var integer[]
	 */
	private $languageIDs;
	
	/**
	 * @inheritDoc
	 */
	protected $methods = [
		'com.woltlab.wcf.trophy.category' => 'TrophyCategories',
		'com.woltlab.wcf.trophy' => 'Trophies',
		'com.woltlab.wcf.userTrophy' => 'UserTrophies',
		'de.wcflabs.trophies.trophyExporter.user.fakeImport' => 'UserFakeImport'
	];
	
	/**
	 * @inheritDoc
	 */
	protected $limits = [
		'com.woltlab.wcf.trophy' => 100,
		'com.woltlab.wcf.userTrophy' => 100,
		'de.wcflabs.trophies.trophyExporter.user.fakeImport' => 250
	];
	
	/**
	 * @inheritDoc
	 */
	public function init() {
		parent::init();
		
		if (preg_match('/^wcf(\d+)_$/', $this->databasePrefix, $match)) {
			$this->dbNo = $match[1];
		}
		
		// fix file system path
		if (!empty($this->fileSystemPath)) {
			if (!@file_exists($this->fileSystemPath . 'lib/core.functions.php') && @file_exists($this->fileSystemPath . 'wcf/lib/core.functions.php')) {
				$this->fileSystemPath = $this->fileSystemPath . 'wcf/';
			}
		}
	}
	
	/**
	 * @inheritDoc
	 */
	public function getSupportedData() {
		return [
			'com.woltlab.wcf.trophy.category' => [],
			'de.wcflabs.trophies.trophyExporter.user.fakeImport' => [],
			'com.woltlab.wcf.trophy' => [
				'com.woltlab.wcf.userTrophy'
			]
		];
	}
	
	/**
	 * @inheritDoc
	 */
	public function validateDatabaseAccess() {
		parent::validateDatabaseAccess();
		
		$sql = "SELECT COUNT(*) FROM wcf".$this->dbNo."_trophy";
		$statement = $this->database->prepareStatement($sql);
		$statement->execute();
	}
	
	/**
	 * @inheritDoc
	 */
	public function validateFileAccess() {
		if (in_array('com.woltlab.wcf.trophy', $this->selectedData)) {
			if (empty($this->fileSystemPath) || (!@file_exists($this->fileSystemPath . 'lib/core.functions.php') && !@file_exists($this->fileSystemPath . 'wcf/lib/core.functions.php'))) return false;
		}
		
		return true;
	}
	
	/**
	 * @inheritDoc
	 */
	public function getQueue() {
		$queue = [];
		if (in_array('com.woltlab.wcf.trophy.category', $this->selectedData)) $queue[] = 'com.woltlab.wcf.trophy.category';
		
		if (in_array('com.woltlab.wcf.trophy', $this->selectedData)) {
			if (in_array('de.wcflabs.trophyExporter.user.fakeImport', $this->selectedData)) $queue[] = 'de.wcflabs.trophies.trophyExporter.user.fakeImport';
			$queue[] = 'com.woltlab.wcf.trophy';
			if (in_array('com.woltlab.wcf.userTrophy', $this->selectedData)) $queue[] = 'com.woltlab.wcf.userTrophy';
		}
		
		return $queue;
	}
	
	/**
	 * @inheritDoc
	 */
	public function getDefaultDatabasePrefix() {
		return 'wcf1_';
	}
	
	/**
	 * Counts trophy categories.
	 */
	public function countTrophyCategories() {
		$sql = "SELECT	COUNT(*)
			FROM	wcf".$this->dbNo."_category
			WHERE	objectTypeID = ?";
		$statement = $this->database->prepareStatement($sql);
		$statement->execute([$this->getObjectTypeID('com.woltlab.wcf.category', 'de.wcflabs.trophies.trophy.category')]);
		
		return $statement->fetchColumn();
	}
	
	/**
	 * Exports trophy categories.
	 *
	 * @param	integer		$offset
	 * @param	integer		$limit
	 */
	public function exportTrophyCategories($offset, $limit) {
		$sql = "SELECT		*
			FROM		wcf".$this->dbNo."_category
			WHERE		objectTypeID = ?
			ORDER BY	parentCategoryID, categoryID";
		$statement = $this->database->prepareStatement($sql, $limit, $offset);
		$statement->execute([$this->getObjectTypeID('com.woltlab.wcf.category', 'de.wcflabs.trophies.trophy.category')]);
		$imports = []; 
		$languageFetch = [];
		
		while ($row = $statement->fetchArray()) {
			if ($row['title'] == 'wcf.category.category.title.category'.$row['categoryID']) {
				$languageFetch[] = 'wcf.category.category.title.category'.$row['categoryID'];
			}
			
			if ($row['description'] == 'wcf.category.category.description.category'.$row['categoryID']) {
				$languageFetch[] = 'wcf.category.category.description.category'.$row['categoryID'];
			}
			
			$imports[$row['categoryID']] = [
				'title' => $row['title'],
				'description' => $row['description'],
				'showOrder' => $row['showOrder'],
				'time' => $row['time'],
				'isDisabled' => $row['isDisabled']
			];
		}
		
		if (!empty($languageFetch)) {
			$languageFetches = $this->fetchLanguageItems($languageFetch);
		}
		
		foreach ($imports as $categoryID => $data) {
			$additionalData = [];
			
			if ($data['title'] == 'wcf.category.category.title.category'.$categoryID && isset($languageFetches['wcf.category.category.title.category'.$categoryID])) {
				$additionalData['i18n']['title'] = $languageFetches['wcf.category.category.title.category'.$categoryID];
			}
			
			if ($data['description'] == 'wcf.category.category.description.category'.$categoryID && isset($languageFetches['wcf.category.category.description.category'.$categoryID])) {
				$additionalData['i18n']['description'] = $languageFetches['wcf.category.category.description.category'.$categoryID];
			}
			
			ImportHandler::getInstance()->getImporter('com.woltlab.wcf.trophy.category')->import($categoryID, $data, $additionalData);
		}
	}
	
	/**
	 * Counts all user from the old database.
	 *
	 * @return string
	 * @throws \wcf\system\database\exception\DatabaseQueryException
	 * @throws \wcf\system\database\exception\DatabaseQueryExecutionException
	 */
	public function countUserFakeImport() {
		$sql = "SELECT	COUNT(*)
			FROM	wcf".$this->dbNo."_user";
		$statement = $this->database->prepareStatement($sql);
		$statement->execute();
		
		return $statement->fetchColumn();
	}
	
	/**
	 * Export all users from the old database, without importing them.
	 *
	 * @param $offset
	 * @param $limit
	 * @throws \wcf\system\database\exception\DatabaseQueryException
	 * @throws \wcf\system\database\exception\DatabaseQueryExecutionException
	 * @throws \wcf\system\exception\SystemException
	 */
	public function exportUserFakeImport($offset, $limit) {
		$sql = "SELECT	userID
			FROM	wcf".$this->dbNo."_user";
		$statement = $this->database->prepareStatement($sql, $offset, $limit);
		$statement->execute();
		
		while ($userID = $statement->fetchColumn()) {
			ImportHandler::getInstance()->saveNewID('com.woltlab.wcf.user', $userID, $userID);
		}
	}
	
	/**
	 * Count trophies.
	 *
	 * @return string
	 * @throws \wcf\system\database\exception\DatabaseQueryException
	 * @throws \wcf\system\database\exception\DatabaseQueryExecutionException
	 */
	public function countTrophies() {
		$sql = "SELECT	COUNT(*)
			FROM	wcf".$this->dbNo."_trophy";
		$statement = $this->database->prepareStatement($sql);
		$statement->execute();
		
		return $statement->fetchColumn();
	}
	
	/**
	 * Export trophies.
	 *
	 * @param $offset
	 * @param $limit
	 * @throws \wcf\system\database\exception\DatabaseQueryException
	 * @throws \wcf\system\database\exception\DatabaseQueryExecutionException
	 * @throws \wcf\system\exception\SystemException
	 */
	public function exportTrophies($offset, $limit) {
		$sql = "SELECT		*
			FROM		wcf".$this->dbNo."_trophy
			ORDER BY	trophyID";
		$statement = $this->database->prepareStatement($sql, $limit, $offset);
		$statement->execute();
		
		$languageFetch = $importData = [];
		
		while ($row = $statement->fetchArray()) {
			if ($row['title'] == 'wcf.user.trophy.title'.$row['trophyID']) {
				$languageFetch[] = 'wcf.user.trophy.title'.$row['trophyID'];
			}
			
			if ($row['description'] == 'wcf.user.trophy.description'.$row['trophyID']) {
				$languageFetch[] = 'wcf.user.trophy.description'.$row['trophyID'];
			}
			
			$data = [
				'title' => $row['title'],
				'description' => $row['description'],
				'categoryID' => $row['categoryID'],
				'isDisabled' => $row['isDisabled']
			];
			
			if (empty($row['iconFile'])) {
				$importData[$row['trophyID']] = [
					'data' => array_merge($data, [
						'type' => Trophy::TYPE_BADGE,
						'iconName' => $row['iconName'],
						'iconColor' => $row['iconColor'],
						'badgeColor' => $row['badgeColor']
					]),
					'additionalData' => []
				];
			} 
			else {
				$importData[$row['trophyID']] = [
					'data' => array_merge($data, [
						'type' => Trophy::TYPE_IMAGE
					]),
					'additionalData' => [
						'fileLocation' => $this->fileSystemPath . 'images/trophies/' .$row['iconFile']
					]
				];
			}
		}
		
		if (!empty($languageFetch)) {
			$languageFetches = $this->fetchLanguageItems($languageFetch);
		}
		
		foreach ($importData as $trophyID => $data) {
			$additionalData = [];
			
			if ($data['data']['title'] == 'wcf.user.trophy.title'.$trophyID && isset($languageFetches['wcf.user.trophy.title'.$trophyID])) {
				$additionalData['i18n']['title'] = $languageFetches['wcf.user.trophy.title'.$trophyID];
			}
			
			if ($data['data']['description'] == 'wcf.user.trophy.description'.$trophyID && isset($languageFetches['wcf.user.trophy.description'.$trophyID])) {
				$additionalData['i18n']['description'] = $languageFetches['wcf.user.trophy.description'.$trophyID];
			}
			
			ImportHandler::getInstance()->getImporter('com.woltlab.wcf.trophy')->import($trophyID, $data['data'], array_merge($additionalData, $data['additionalData']));
		}
	}
	
	/**
	 * Count user trophies.
	 *
	 * @return string
	 * @throws \wcf\system\database\exception\DatabaseQueryException
	 * @throws \wcf\system\database\exception\DatabaseQueryExecutionException
	 */
	public function countUserTrophies() {
		$sql = "SELECT	COUNT(*)
			FROM	wcf".$this->dbNo."_user_trophy";
		$statement = $this->database->prepareStatement($sql);
		$statement->execute();
		
		return $statement->fetchColumn();
	}
	
	/**
	 * Export user trophies.
	 *
	 * @param $offset
	 * @param $limit
	 * @throws \wcf\system\database\exception\DatabaseQueryException
	 * @throws \wcf\system\database\exception\DatabaseQueryExecutionException
	 * @throws \wcf\system\exception\SystemException
	 */
	public function exportUserTrophies($offset, $limit) {
		$sql = "SELECT		*
			FROM		wcf".$this->dbNo."_user_trophy
			ORDER BY	userTrophyID";
		$statement = $this->database->prepareStatement($sql, $limit, $offset);
		$statement->execute();
		
		$languageFetch = $importData = [];
		while ($row = $statement->fetchArray()) {
			if (!$row['useTrophyDescription'] && $row['description'] == 'wcf.user.trophy.userTrophy.description'.$row['userTrophyID']) {
				$languageFetch[] = 'wcf.user.trophy.userTrophy.description'.$row['userTrophyID'];
			}
			
			$importData[$row['userTrophyID']] = [
				'trophyID' => $row['trophyID'],
				'userID' => $row['userID'],
				'time' => $row['time'],
				'useCustomDescription' => $row['useTrophyDescription'] ? 0 : 1,
				'description' => $row['useTrophyDescription'] ? '' : $row['description']
			];
		}
		
		if (!empty($languageFetch)) {
			$languageFetches = $this->fetchLanguageItems($languageFetch);
		}
		
		foreach ($importData as $userTrophyID => $data) {
			$additionalData = [];
			
			if ($data['description'] == 'wcf.user.trophy.userTrophy.description'.$userTrophyID && isset($languageFetches['wcf.user.trophy.userTrophy.description'.$userTrophyID])) {
				$additionalData['i18n']['description'] = $languageFetches['wcf.user.trophy.userTrophy.description'.$userTrophyID];
			}
			
			ImportHandler::getInstance()->getImporter('com.woltlab.wcf.userTrophy')->import($userTrophyID, $data, $additionalData);
		}
	}
	
	/**
	 * Returns the id of an object type in the imported system or null if no such
	 * object type exists.
	 *
	 * @param        string $definitionName
	 * @param        string $objectTypeName
	 * @return        integer|null
	 * @throws \wcf\system\database\exception\DatabaseQueryException
	 * @throws \wcf\system\database\exception\DatabaseQueryExecutionException
	 */
	private function getObjectTypeID($definitionName, $objectTypeName) {
		$sql = "SELECT	objectTypeID
			FROM	wcf".$this->dbNo."_object_type
			WHERE	objectType = ?
				AND definitionID = (
					SELECT definitionID FROM wcf".$this->dbNo."_object_type_definition WHERE definitionName = ?
				)";
		$statement = $this->database->prepareStatement($sql, 1);
		$statement->execute([$objectTypeName, $definitionName]);
		$row = $statement->fetchArray();
		if ($row !== false) return $row['objectTypeID'];
		
		return null;
	}
	
	/**
	 * Returns the language id of the given language code. 
	 * 
	 * @param $languageCode
	 * @return int|null
	 */
	private function getLanguageIDByLanguageCode($languageCode) {
		if ($this->languageIDs === null) {
			$languageList = new LanguageList();
			$languageList->readObjects(); 
			
			foreach ($languageList as $language) {
				$this->languageIDs[$language->languageCode] = $language->languageID; 
			}
		}
		
		return (isset($this->languageIDs[$languageCode])) ? $this->languageIDs[$languageCode] : null;
	}
	
	/**
	 * Fetch language items and returns them. 
	 * 
	 * @param $items
	 * @return array
	 * @throws \wcf\system\database\exception\DatabaseQueryException
	 * @throws \wcf\system\database\exception\DatabaseQueryExecutionException
	 */
	private function fetchLanguageItems($items) {
		$languageFetches = [];
		
		$conditionBuilder = new PreparedStatementConditionBuilder();
		$conditionBuilder->add('language_item.languageItem IN (?)', [$items]);
		
		$sql = "SELECT	language_item.languageItem, language_item.languageItemValue, language_table.languageCode
			FROM		wcf".$this->dbNo."_language_item language_item
			LEFT JOIN	wcf".$this->dbNo."_language language_table ON (language_item.languageID = language_table.languageID)
			". $conditionBuilder;
		$statement = $this->database->prepareStatement($sql);
		$statement->execute($conditionBuilder->getParameters());
		
		while ($row = $statement->fetchArray()) {
			if ($this->getLanguageIDByLanguageCode($row['languageCode']) !== null) {
				if (!isset($languageFetches[$row['languageItem']])) $languageFetches[$row['languageItem']] = [];
				
				$languageFetches[$row['languageItem']][$this->getLanguageIDByLanguageCode($row['languageCode'])] = $row['languageItemValue'];
			}
		}
		
		return $languageFetches;
	}
}