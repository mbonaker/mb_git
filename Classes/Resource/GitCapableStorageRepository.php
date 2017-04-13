<?php

namespace MatteoBonaker\MbGit\Resource;


use TYPO3\CMS\Core\Utility\GeneralUtility;

class GitCapableStorageRepository extends \TYPO3\CMS\Core\Resource\StorageRepository {

	/**
	 * Creates this object.
	 */
	public function __construct() {
		parent::__construct();
		$this->factory = GeneralUtility::makeInstance(GitCapableResourceFactory::class);
	}

	/**
	 * @param int $uid
	 *
	 * @return NULL|GitCapableResourceStorage
	 */
	public function findByUid($uid) {
		$this->initializeLocalCache();
		if (isset(self::$storageRowCache[$uid])) {
			return $this->factory->getStorageObject($uid, self::$storageRowCache[$uid]);
		}
		return null;
	}
}
