<?php

namespace MatteoBonaker\MbGit\Resource\Driver;


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
