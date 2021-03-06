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
use Gitonomy\Git\Exception\GitExceptionInterface;
use Gitonomy\Git\Exception\ProcessException;
use Gitonomy\Git\Exception\RuntimeException;
use Gitonomy\Git\Repository;
use MatteoBonaker\MbGit\Exception\GitException;
use MatteoBonaker\MbGit\Git\Remote;
use Symfony\Component\Process\ProcessBuilder;
use TYPO3\CMS\Core\Resource\Driver\LocalDriver;
use TYPO3\CMS\Core\Resource\Folder;
use TYPO3\CMS\Core\Resource\ResourceInterface;
use TYPO3\CMS\Core\Utility\DebugUtility;
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
	 * Returns the absolute path of a file or folder.
	 *
	 * @param ResourceInterface $item
	 * @return string
	 */
	protected function getAbsoluteResourcePath(ResourceInterface $item) {
		return $this->getAbsolutePath($item->getIdentifier());
	}

	/**
	 * This internal method is used to create a process object.
	 *
	 * Copied from the private Gitonomy\Git\Admin::getProcess
	 */
	private static function getProcess($command, array $args = array(), array $options = array()) {
		$is_windows = defined('PHP_WINDOWS_VERSION_BUILD');
		$options = array_merge(array(
			'environment_variables' => $is_windows ? array('PATH' => getenv('PATH')) : array(),
			'command' => 'git',
			'process_timeout' => 3600,
		), $options);

		$builder = ProcessBuilder::create(array_merge(array($options['command'], $command), $args));
		$builder->inheritEnvironmentVariables(false);

		$process = $builder->getProcess();
		$process->setEnv($options['environment_variables']);
		$process->setTimeout($options['process_timeout']);
		$process->setIdleTimeout($options['process_timeout']);

		return $process;
	}

	protected function getRepositoryPath(ResourceInterface $item) {
		$process = self::getProcess('rev-parse', [
			'--git-dir'
		]);
		if ($item instanceof Folder) {
			$path = realpath($this->getAbsoluteResourcePath($item));
		} else {
			$path = dirname(realpath($this->getAbsoluteResourcePath($item)));
		}
		$process->setWorkingDirectory($path);
		$process->run();
		if (!$process->isSuccessful()){
			return null;
		} else {
			// Stripping off the last newline (file names may end in new line chars, so rtrim(..., "\n") is no option
			$dir = substr($process->getOutput(), 0, -1);
			// If the dir is relative, it is relative to $path so we need to resolve it.
			if (substr($dir, 0, 1) !== '/') {
				$dir = realpath($path . '/' . $dir);
			}
			return $dir;
		}
	}

	protected function getWorkingDirectoryPath(ResourceInterface $item) {
		if ($item instanceof Folder) {
			$path = realpath($this->getAbsoluteResourcePath($item));
		} else {
			$path = dirname(realpath($this->getAbsoluteResourcePath($item)));
		}
		$process = self::getProcess('rev-parse', [
			'--is-inside-work-tree'
		]);
		$process->setWorkingDirectory($path);
		$process->run();
		if (!filter_var(rtrim($process->getOutput()), FILTER_VALIDATE_BOOLEAN)) {
			return null;
		}
		// We are in a working tree here!
		$process = self::getProcess('rev-parse', [
			'--show-toplevel'
		]);
		$process->setWorkingDirectory($path);
		$process->run();
		return substr($process->getOutput(), 0, -1);
	}

	/**
	 * Returns the git repository which $item is part of.
	 *
	 * @param ResourceInterface $item The folder being part of the returned repository
	 * @return Repository
	 */
	public function getRepository(ResourceInterface $item) {
		$path = $this->getRepositoryPath($item);

		if($path === null) {
			return null;
		}

		foreach($this->repositories as $repository) {
			if($repository->getPath() === $path) {
				return $repository;
			}
		}
		$repository = new Repository($path);
		$this->repositories[] = $repository;
		return $repository;
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
			return $repository->getLog($repository->getHead(), $path);
		} catch(RuntimeException $exception) {
			throw new GitException('Could not get the git log of ' . $path . '.', 1492622839, $exception);
		}
	}

	/**
	 * @param ResourceInterface $item
	 * @return Remote[]
	 * @throws GitException
	 */
	public function gitGetRemotes(ResourceInterface $item) {
		$repository = $this->getRepository($item);
		$remoteString = $repository->run('remote', ['-v']);
		if($remoteString === null) {
			throw new GitException('Could not get the remotes of this git repository.', 1492622830);
		}
		$remoteLines = explode("\n", $remoteString);
		/** @var Remote[] $remotes */
		$remotes = [];
		foreach($remoteLines as $remoteLineString) {
			if (empty($remoteLineString)) {
				continue;
			}
			list($name, $url) = preg_split('/[\\t\\s]/', $remoteLineString);
			// TODO Use $direction
			foreach($remotes as $remote) {
				if($remote->getName() == $name) {
					continue 2;
				}
			}
			$remotes[] = new Remote($name, $url);
		}
		return $remotes;
	}

	/**
	 * @param ResourceInterface $item
	 * @param Remote $remote
	 * @param string $newUrl
	 * @return Remote
	 * @throws GitException
	 */
	public function gitRemoteSetUrl(ResourceInterface $item, Remote $remote, $newUrl) {
		$repository = $this->getRepository($item);
		try {
			$repository->run('remote', [
				'set-url',
				$remote->getName(),
				$newUrl,
			]);
		} catch(RuntimeException $exception) {
			throw new GitException('Could not run the git command to set the url of ' . $remote->getName(), 1492629020, $exception);
		}
		return new Remote($remote->getName(), $newUrl);
	}

	/**
	 * @param ResourceInterface $item
	 * @param Remote $remote
	 * @param string $newName
	 * @return Remote
	 * @throws GitException
	 */
	public function gitRemoteRename(ResourceInterface $item, Remote $remote, $newName) {
		$repository = $this->getRepository($item);
		try {
			$repository->run('remote', [
				'rename',
				$remote->getName(),
				$newName,
			]);
		} catch(RuntimeException $exception) {
			throw new GitException('Could not run the git command to rename the remote ' . $remote->getName(), 1492629180, $exception);
		}
		return new Remote($newName, $remote->getUrl());
	}

	public function gitRemoteAdd(ResourceInterface $item, Remote $remote) {
		$repository = $this->getRepository($item);
		try {
			$repository->run('remote', [
				'add',
				$remote->getName(),
				$remote->getUrl(),
			]);
		} catch(RuntimeException $exception) {
			throw new GitException('Could not run the git command to add the remote ' . $remote->getName(), 1492629424, $exception);
		}
	}

	public function gitRemoteRemove(ResourceInterface $item, $remoteName) {
		$repository = $this->getRepository($item);
		try {
			$repository->run('remote', [
				'remove',
				$remoteName,
			]);
		} catch(RuntimeException $exception) {
			throw new GitException('Could not run the git command to remove the remote ' . $remoteName, 1492631239, $exception);
		}
	}

	public function gitFetch(ResourceInterface $item, Remote $remote) {
		$repository = $this->getRepository($item);
		try {
			$repository->run('fetch', [
				$remote->getName(),
			]);
		} catch(RuntimeException $exception) {
			throw new GitException('Could not fetch from ' . $remote->getUrl(), 1492632471, $exception);
		}
	}

	public function gitPush(ResourceInterface $item, Remote $remote) {
		$repository = $this->getRepository($item);
		try {
			$repository->run('push', [
				$remote->getName(),
				':'
			]);
		} catch(RuntimeException $exception) {
			throw new GitException('Could not push to ' . $remote->getUrl(), 1493492464, $exception);
		}
	}
}
