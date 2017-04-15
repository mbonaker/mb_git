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


use TYPO3\CMS\Core\Resource\Driver\LocalDriver;

class GitCapableDriverRegistry extends \TYPO3\CMS\Core\Resource\Driver\DriverRegistry {
	/**
	 * Returns a class name for a given class name or short name.
	 * Instead of instances of LocalDriver this returns instances of GitCapableLocalDriver.
	 *
	 * @param string $shortName
	 * @return string The class name
	 * @throws \InvalidArgumentException
	 */
	public function getDriverClass($shortName) {
		$driverClass = parent::getDriverClass($shortName);
		if ($driverClass === LocalDriver::class) {
			$driverClass = GitCapableLocalDriver::class;
		}
		return $driverClass;
	}

}
