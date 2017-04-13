<?php
/**
 * Created by PhpStorm.
 * User: matteo
 * Date: 13.04.17
 * Time: 22:07
 */

namespace MatteoBonaker\MbGit\Resource\Driver;


use Gitonomy\Git\Admin;
use Gitonomy\Git\Exception\RuntimeException;
use Gitonomy\Git\Repository;
use TYPO3\CMS\Core\Resource\Driver\LocalDriver;
use TYPO3\CMS\Core\Resource\Folder;
use TYPO3\CMS\Core\Resource\ResourceInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class GitCapableLocalDriver extends LocalDriver {

	/**
	 * Returns an instance of @GitCapableLocalDriver from a normal @LocalDriver.
	 *
	 * @param LocalDriver $driver
	 * @return GitCapableLocalDriver
	 */
	public static function fromGitIncapableLocalDriver(LocalDriver $driver) {
		if($driver instanceof self) {
			return $driver;
		}
		/** @var self $instance */
		$instance = GeneralUtility::makeInstance(self::class, $driver->configuration);
		return $instance;
	}

	/**
	 * A list of all git repositories on this drive.
	 *
	 * @var Repository[]
	 */
	protected $repositories = [];

	/**
	 * Initializes a repository and returns the instance.
	 *
	 * @param Folder $folder  the working directory
	 * @param bool   $bare    indicate to create a bare repository
	 *
	 * @return Repository
	 *
	 * @throws RuntimeException Directory exists or not writable (only if debug=true)
	 */
	public function gitInit(Folder $folder, $bare = false) {
		$repository = Admin::init($this->getAbsolutePath($folder->getIdentifier()), $bare);
		$this->repositories[] = $repository;
		return $repository;
	}

	/**
	 * Get a hierarchical list of resources that are part of the path of $item (including itself).
	 *
	 * @param ResourceInterface $item The resource of which to get the path items
	 * @return ResourceInterface[] The hierarchical list of resources that are part of the path of $item (including $item itself).
	 */
	protected function getPathResources(ResourceInterface $item) {
		/** @var ResourceInterface[] $elements */
		$elements = [];
		do {
			$elements[] = $item;
			$item = $item->getParentFolder();
		} while(!in_array($item, $elements));

		return $elements;
	}

	/**
	 * Returns the absolute path of a file or folder.
	 *
	 * @param ResourceInterface $item
	 * @return string
	 */
	protected function getAbsoluteResourcePath(ResourceInterface $item) {
		return $this->getAbsolutePath($item->getIdentifier());
	}

	/**
	 * Returns the git repository which $item is part of.
	 *
	 * @param ResourceInterface $item The folder being part of the returned repository
	 * @return Repository
	 */
	public function getRepository(ResourceInterface $item) {
		$elements = $this->getPathResources($item);
		foreach($elements as $element) {
			$elementPath = $this->getAbsoluteResourcePath($element);
			foreach($this->repositories as $repository) {
				if($repository->getWorkingDir() === $elementPath) {
					return $repository;
				}
			}
			if($element instanceof Folder && $element->hasFolder('.git')) {
				return new Repository($elementPath);
			}
		}
		return null;
	}
}
