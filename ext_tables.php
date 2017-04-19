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
	\TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerModule(
		'MatteoBonaker.MbGit',
		'file',
		'list',
		'',
		[
			'FileList' => implode(', ', [
				'index',
				'search',
			]),
			'Git' => implode(', ', [
				'commit',
				'clone',
				'log',
			]),
		],
		[
			'access' => 'user,group',
			'workspaces' => 'online,custom',
			'icon' => 'EXT:mb_git/Resources/Public/Icons/module-mbgit.svg',
			'labels' => 'LLL:EXT:lang/locallang_mod_file_list.xlf'
		]
	);
}
