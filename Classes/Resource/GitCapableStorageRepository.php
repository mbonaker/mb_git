<?php

namespace MatteoBonaker\MbGit\Resource;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 *
 */


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
