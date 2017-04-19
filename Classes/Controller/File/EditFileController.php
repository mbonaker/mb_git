<?php

namespace MatteoBonaker\MbGit\Controller\File;

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

use TYPO3\CMS\Core\Resource\Exception\InsufficientFileAccessPermissionsException;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * This class is only present to extend its parent class so that the back button of it leads back to the MBGit module.
 *
 * @package MatteoBonaker\MbGit\Controller\File
 */
class EditFileController extends \TYPO3\CMS\Backend\Controller\File\EditFileController {


	/**
	 * @return string
	 */
	protected function getFileListModuleIdentifier() {
		return 'file_MbGitList';
	}

	/**
	 * Initialize script class
	 *
	 * @throws InsufficientFileAccessPermissionsException
	 * @throws \InvalidArgumentException
	 * @throws \RuntimeException
	 */
	protected function init() {
		parent::init();
		// This is for the back button of the editor to lead back to the MbGit module instead of the normal FileList
		$this->moduleTemplate->addJavaScriptCode(
			'FileEditBackToList',
			'function backToList() {
				top.goToModule(' . GeneralUtility::quoteJSvalue($this->getFileListModuleIdentifier()) . ');
			}'
		);
	}

}
