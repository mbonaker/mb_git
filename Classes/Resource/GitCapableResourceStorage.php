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


use MatteoBonaker\MbGit\Git\Remote;
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

	public function isGitVersioned(ResourceInterface $item) {
		return $this->getGitCapableLocalDriver()->getRepository($item) !== null;
	}

	public function gitCommit(ResourceInterface $item, $message, $mail, $name) {
		$this->getGitCapableLocalDriver()->gitCommit($item, $message, $mail, $name);
	}

	public function gitConfig(ResourceInterface $item, $key, $value) {
		$this->getGitCapableLocalDriver()->gitConfig($item, $key, $value);
	}

	public function gitClone(Folder $folder, $source) {
		$this->getGitCapableLocalDriver()->gitClone($folder, $source);
	}

	public function gitLog(ResourceInterface $item) {
		return $this->getGitCapableLocalDriver()->gitLog($item);
	}

	/**
	 * @param ResourceInterface $item
	 * @return Remote[]
	 */
	public function gitGetRemotes(ResourceInterface $item) {
		return $this->getGitCapableLocalDriver()->gitGetRemotes($item);
	}

	/**
	 * @param ResourceInterface $item
	 * @param Remote $remote
	 * @param string $newUrl
	 * @return Remote
	 */
	public function gitRemoteSetUrl(ResourceInterface $item, Remote $remote, $newUrl) {
		return $this->getGitCapableLocalDriver()->gitRemoteSetUrl($item, $remote, $newUrl);
	}

	/**
	 * @param ResourceInterface $item
	 * @param Remote $remote
	 * @param string $newName
	 * @return Remote
	 */
	public function gitRemoteRename(ResourceInterface $item, Remote $remote, $newName) {
		return $this->getGitCapableLocalDriver()->gitRemoteRename($item, $remote, $newName);
	}

	/**
	 * @param ResourceInterface $item
	 * @param Remote $remote
	 * @return void
	 */
	public function gitRemoteAdd(ResourceInterface $item, Remote $remote) {
		$this->getGitCapableLocalDriver()->gitRemoteAdd($item, $remote);
	}

	/**
	 * @param ResourceInterface $item
	 * @param string $remoteName
	 */
	public function gitRemoteRemove(ResourceInterface $item, $remoteName) {
		$this->getGitCapableLocalDriver()->gitRemoteRemove($item, $remoteName);
	}

	/**
	 * @param ResourceInterface $item
	 * @param Remote $remote
	 */
	public function gitFetch(ResourceInterface $item, Remote $remote) {
		$this->getGitCapableLocalDriver()->gitFetch($item, $remote);
	}
}
