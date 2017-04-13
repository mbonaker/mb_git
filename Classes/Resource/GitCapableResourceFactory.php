<?php

namespace MatteoBonaker\MbGit\Resource;


use MatteoBonaker\MbGit\Resource\Driver\GitCapableDriverRegistry;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class GitCapableResourceFactory extends \TYPO3\CMS\Core\Resource\ResourceFactory {

	/**
	 * Gets a singleton instance of this class.
	 *
	 * @return GitCapableResourceFactory
	 */
	public static function getInstance() {
		return GeneralUtility::makeInstance(__CLASS__);
	}

	/** @var GitCapableResourceStorage[] */
	protected $storageInstances;

	/**
	 * Creates an instance of the storage from given UID. The $recordData can
	 * be supplied to increase performance.
	 *
	 * @param int $uid The uid of the storage to instantiate.
	 * @param array $recordData The record row from database.
	 * @param string $fileIdentifier Identifier for a file. Used for auto-detection of a storage, but only if $uid === 0 (Local default storage) is used
	 *
	 * @throws \InvalidArgumentException
	 * @return GitCapableResourceStorage
	 */
	public function getStorageObject($uid, array $recordData = [], &$fileIdentifier = null) {
		if (!is_numeric($uid)) {
			throw new \InvalidArgumentException('The UID of storage has to be numeric. UID given: "' . $uid . '"', 1314085991);
		}
		$uid = (int)$uid;
		if ($uid === 0 && $fileIdentifier !== null) {
			$uid = $this->findBestMatchingStorageByLocalPath($fileIdentifier);
		}
		if (!$this->storageInstances[$uid]) {
			$storageConfiguration = null;
			$storageObject = null;
			// If the built-in storage with UID=0 is requested:
			if ($uid === 0) {
				$recordData = [
					'uid' => 0,
					'pid' => 0,
					'name' => 'Fallback Storage',
					'description' => 'Internal storage, mounting the main TYPO3_site directory.',
					'driver' => 'Local',
					'processingfolder' => 'typo3temp/_processed_/',
					// legacy code
					'configuration' => '',
					'is_online' => true,
					'is_browsable' => true,
					'is_public' => true,
					'is_writable' => true,
					'is_default' => false,
				];
				$storageConfiguration = [
					'basePath' => '/',
					'pathType' => 'relative'
				];
			} elseif (count($recordData) === 0 || (int)$recordData['uid'] !== $uid) {
				/** @var $storageRepository GitCapableStorageRepository */
				$storageRepository = GeneralUtility::makeInstance(GitCapableStorageRepository::class);
				/** @var $storage GitCapableResourceStorage */
				$storageObject = $storageRepository->findByUid($uid);
			}
			if (!$storageObject instanceof GitCapableResourceStorage) {
				$storageObject = $this->createStorageObject($recordData, $storageConfiguration);
			}
			$this->emitPostProcessStorageSignal($storageObject);
			$this->storageInstances[$uid] = $storageObject;
		}
		return $this->storageInstances[$uid];
	}

	/**
	 * Creates a storage object from a storage database row.
	 *
	 * @param array $storageRecord
	 * @param array $storageConfiguration Storage configuration (if given, this won't be extracted from the FlexForm value but the supplied array used instead)
	 * @return GitCapableResourceStorage
	 */
	public function createStorageObject(array $storageRecord, array $storageConfiguration = null) {
		$className = GitCapableResourceStorage::class;
		if (!$storageConfiguration) {
			$storageConfiguration = $this->convertFlexFormDataToConfigurationArray($storageRecord['configuration']);
		}
		$driverType = $storageRecord['driver'];
		$driverObject = $this->getDriverObject($driverType, $storageConfiguration);
		return GeneralUtility::makeInstance($className, $driverObject, $storageRecord);
	}

	/**
	 * Creates a driver object for a specified storage object.
	 *
	 * @param string $driverIdentificationString The driver class (or identifier) to use.
	 * @param array $driverConfiguration The configuration of the storage
	 * @return \TYPO3\CMS\Core\Resource\Driver\DriverInterface
	 * @throws \InvalidArgumentException
	 */
	public function getDriverObject($driverIdentificationString, array $driverConfiguration) {
		/** @var $driverRegistry GitCapableDriverRegistry */
		$driverRegistry = GeneralUtility::makeInstance(GitCapableDriverRegistry::class);
		$driverClass = $driverRegistry->getDriverClass($driverIdentificationString);
		$driverObject = GeneralUtility::makeInstance($driverClass, $driverConfiguration);
		return $driverObject;
	}

	/**
	 * Gets a storage object from a combined identifier
	 *
	 * @param string $identifier An identifier of the form [storage uid]:[object identifier]
	 * @return GitCapableResourceStorage
	 */
	public function getStorageObjectFromCombinedIdentifier($identifier) {
		$parts = GeneralUtility::trimExplode(':', $identifier);
		$storageUid = count($parts) === 2 ? $parts[0] : null;
		return $this->getStorageObject($storageUid);
	}
}
