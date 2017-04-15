<?php

namespace MatteoBonaker\MbGit\Service;

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



class ExtensionConfigurationService implements \TYPO3\CMS\Core\SingletonInterface {

	/**
	 * @return self
	 */
	static public function getInstance() {
		/** @var self $instance */
		$instance = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(
			self::class
		);
		return $instance;
	}

	/**
	 * @var array
	 */
	protected $configuration;

	/**
	 * @return array
	 */
	public function getConfiguration() {
		if (!isset($this->configuration)) {
			if (!empty($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['mb_git'])) {
				$this->configuration = array_merge(
					(array) $this->configuration,
					(array) unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['mb_git'])
				);
			}
		}

		return $this->configuration;
	}

	/**
	 * @param string $path
	 * @return NULL|string
	 */
	public function get($path) {
		try {
			$sanitizedPath = str_replace('.', './', $path);
			$value = \TYPO3\CMS\Core\Utility\ArrayUtility::getValueByPath($this->getConfiguration(), $sanitizedPath, '/');
		} catch (\RuntimeException $exception) {
			$value = NULL;
		}

		return $value;
	}

	public function getGitConfigUserName() {
		return $this->get('git.config.user.name');
	}

	public function getGitConfigUserEmail() {
		return $this->get('git.config.user.email');
	}

}
