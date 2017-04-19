<?php

namespace MatteoBonaker\MbGit\Git;

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

/**
 * The immutable representation of a git remote.
 * @package MatteoBonaker\MbGit\Git
 */
class Remote {

	const FETCH = 0b01;
	const PUSH  = 0b10;

	protected $url;
	protected $name;
	protected $direction;

	/**
	 * Remote constructor.
	 * @param string $name
	 * @param string $url
	 * @param int $direction Remote.FETCH, Remote.PUSH or both (use logical or)
	 */
	public function __construct($name, $url, $direction = 0) {
		$this->name = $name;
		$this->url = $url;
		$this->direction = $direction;
	}

	/**
	 * @return string
	 */
	public function getUrl() {
		return $this->url;
	}

	/**
	 * @return string
	 */
	public function getName() {
		return $this->name;
	}

	/**
	 * @return boolean
	 */
	public function isFetchActive() {
		return (bool) ($this->direction & self::FETCH);
	}

	/**
	 * @return boolean
	 */
	public function isPushActive() {
		return (bool) ($this->direction & self::PUSH);
	}

}
