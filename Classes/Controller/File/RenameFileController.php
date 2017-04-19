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


class RenameFileController extends \TYPO3\CMS\Backend\Controller\File\RenameFileController {
	/**
	 * Initialize
	 *
	 * @throws \TYPO3\CMS\Core\Resource\Exception\InsufficientFileAccessPermissionsException
	 */
	protected function init() {
		parent::init();
		// Add javaScript
		$this->moduleTemplate->addJavaScriptCode(
			'RenameFileInlineJavaScript',
			'function backToList() {top.goToModule("file_MbGitList");}'
		);
	}

}
