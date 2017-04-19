<?php

namespace MatteoBonaker\MbGit\Resource\Driver;

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


use Gitonomy\Git\Admin;
use Gitonomy\Git\Exception\ProcessException;
use Gitonomy\Git\Exception\RuntimeException;
use Gitonomy\Git\Repository;
use MatteoBonaker\MbGit\Exception\GitException;
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

	public function gitConfig(ResourceInterface $item, $key, $value) {
		// TODO Error handling
		$this->getRepository($item)->run('config', [
			$key,
			$value,
		]);
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
				$repository = new Repository($elementPath);
				$this->repositories[] = $repository;
				return $repository;
			}
		}
		return null;
	}

	public function gitCommit(ResourceInterface $item, $message, $mail, $name) {
		$author = $name . ' <' . $mail . '>';
		$repository = $this->getRepository($item);
		try {
			$repository->run('add', [
				'-N',
				'.',
			]);
		} catch(ProcessException $exception) {
			// TODO Translation
			throw new GitException('Could not add all untracked files to the index.', 1492246295, $exception);
		}
		try {
			$repository->run('commit', [
				'-a',
				'--author=' . $author,
				'--message=' . $message,
			]);
		} catch(ProcessException $exception) {
			// TODO Translation
			throw new GitException('Could not execute the commit.', 1492246408, $exception);
		}
	}

	public function gitClone(Folder $folder, $source) {
		$path = $this->getAbsoluteResourcePath($folder);
		try {
			$repository = Admin::cloneTo($path, $source, false);
		} catch(RuntimeException $exception) {
			$src = var_export($source, true);
			$tgt = $path;
			throw new GitException('Could not clone ' . $src . ' to ' . $tgt . '.', 1492271863, $exception);
		}
		$this->repositories[] = $repository;
	}

	public function gitLog(ResourceInterface $item) {
		$repository = $this->getRepository($item);
		$path = $this->getAbsoluteResourcePath($item);
		try {
			return $repository->getLog(null, $path);
		} catch(RuntimeException $exception) {
			throw new GitException('Could not get the git log of ' . $path . '.', 1492271863, $exception);
		}
	}
}
