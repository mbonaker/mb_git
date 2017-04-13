<?php

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

defined('TYPO3_MODE') or die();

if (TYPO3_MODE === 'BE') {
	// Get the configuration of the current filelist module
	//$currentConf = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::configureModule('file_FilelistList', null);
	// Add a module with the same configuration but slightly modified
	\TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerModule(
		'MatteoBonaker.MbGit',
		'file',
		'list',
		'',
		[
			'FileList' => 'index, search',
		],
		[
			'access' => 'user,group',
			'workspaces' => 'online,custom',
			'icon' => 'EXT:mb_git/Resources/Public/Icons/module-mbgit.svg',
			'labels' => 'LLL:EXT:lang/locallang_mod_file_list.xlf'
		]// + $currentConf
	);
}
