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


use MatteoBonaker\MbGit\Resource\Driver\GitCapableLocalDriver;
use TYPO3\CMS\Core\Resource\Driver\LocalDriver;
use TYPO3\CMS\Core\Resource\Folder;
use TYPO3\CMS\Core\Resource\ResourceInterface;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class GitCapableResourceStorage extends \TYPO3\CMS\Core\Resource\ResourceStorage {

	/**
	 * Returns a @GitCapableResourceStorage from a normal @ResourceStorage.
	 *
	 * @param ResourceStorage $resourceStorage
	 * @return GitCapableResourceStorage
	 */
	static public function fromGitIncapableResourceStorage(ResourceStorage $resourceStorage) {
		if ($resourceStorage instanceof self) {
			return $resourceStorage;
		}
		$driver = $resourceStorage->driver;
		if (!$driver instanceof LocalDriver) {
			throw new \RuntimeException('Can not create a git capable resource storage from a storage with a driver of type ' . get_class($driver));
		}
		$gitCapableDriver = GitCapableLocalDriver::fromGitIncapableLocalDriver($driver);
		/** @var self $gitCapableStorage */
		$gitCapableStorage = GeneralUtility::makeInstance(self::class, $gitCapableDriver, $resourceStorage->storageRecord);
		return $gitCapableStorage;
	}

	/**
	 * Initialize a git repository at $folder.
	 *
	 * @param Folder $folder
	 * @return \Gitonomy\Git\Repository
	 */
	public function gitInit(Folder $folder) {
		return $this->getGitCapableLocalDriver()->gitInit($folder);
	}

	/**
	 * Same as `self::getDriver` but is certain to return a git capable driver.
	 *
	 * @return GitCapableLocalDriver
	 */
	protected function getGitCapableLocalDriver() {
		$driver = $this->getDriver();
		if(!$driver instanceof GitCapableLocalDriver) {
			throw new \RuntimeException('This storage has no git capable driver.');
		}
		return $driver;
	}

	/**
	 * Returns the folders on the root level of the storage
	 * or the first mount point of this storage for this user
	 * if $respectFileMounts is set.
	 *
	 * @param bool $respectFileMounts
	 * @return Folder
	 */
	public function getRootLevelFolder($respectFileMounts = true) {
		if ($respectFileMounts && !empty($this->fileMounts)) {
			$mount = reset($this->fileMounts);
			return $mount['folder'];
		} else {
			return GitCapableResourceFactory::getInstance()->createFolderObject($this, $this->driver->getRootLevelFolder(), '');
		}
	}

	public function gitCommit(ResourceInterface $item, $message, $mail, $name) {
		$this->getGitCapableLocalDriver()->gitCommit($item, $message, $mail, $name);
	}

	public function gitConfig(ResourceInterface $item, $key, $value) {
		$this->getGitCapableLocalDriver()->gitConfig($item, $key, $value);
	}
}
