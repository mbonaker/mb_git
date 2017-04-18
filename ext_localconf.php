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

/** @var \TYPO3\CMS\Core\Imaging\IconRegistry $iconRegistry */
$iconRegistry = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(
	\TYPO3\CMS\Core\Imaging\IconRegistry::class
);
$iconRegistry->registerIcon(
	'tx-mbgit-git-logo',
	\TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider::class,
	['source' => 'EXT:mb_git/Resources/Public/Icons/git-logo.svg']
);
$iconRegistry->registerIcon(
	'octicons-check',
	\TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider::class,
	['source' => 'EXT:mb_git/Resources/Public/Octicons/check.svg']
);
$iconRegistry->registerIcon(
	'octicons-cloud-download',
	\TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider::class,
	['source' => 'EXT:mb_git/Resources/Public/Octicons/cloud-download.svg']
);
