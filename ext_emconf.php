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

/***************************************************************
 * Extension Manager/Repository config file for ext "mb_git".
 ***************************************************************/

$EM_CONF[$_EXTKEY] = array (
	'title' => 'Git user interface ',
	'description' => '',
	'category' => 'fe',
	'author' => 'Matteo Bonaker',
	'author_email' => 'tig-txe@m.softwar3.com',
	'state' => 'beta',
	'version' => '0.0.1',
	'autoload' =>
		array(
			'psr-4' => array(
				'MatteoBonaker\\MbGit\\' => 'Classes',
				'Gitonomy\\' => 'Resources/Private/Contrib/Gitonomy',
				'Symfony\\' => 'Resources/Private/Contrib/Symfony',
				'Psr\\' => 'Resources/Private/Contrib/Psr/Psr',
			),
		),
	'constraints' =>
		array (
			'depends' =>  array (
				'typo3' => '7.6.0-7.6.99',
				'php' => '7.0.0-7.1.99',
			),
			'conflicts' =>  array (
			),
			'suggests' =>  array (
			),
		),
	'suggests' => array (),
);
