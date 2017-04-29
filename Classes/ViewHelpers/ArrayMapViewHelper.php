<?php

namespace MatteoBonaker\MbGit\ViewHelpers;

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


use FluidTYPO3\Vhs\Traits\TemplateVariableViewHelperTrait;
use TYPO3\CMS\Fluid\Core\ViewHelper\AbstractViewHelper;

class ArrayMapViewHelper extends AbstractViewHelper {
	use TemplateVariableViewHelperTrait;

	public function initializeArguments() {
		$this->registerArgument('as', 'string', 'The variable name of the current key', true, 'current');

		$this->registerArgument('traversable', 'array', 'The array whose values are to be mapped', true);
	}


	public function render() {
		$viewHelper = $this;
		$renderChildrenClosure = function() use ($viewHelper) {
			return $this->renderChildren();
		};
		$as = $this->arguments['as'];
		$traversable = $this->arguments['traversable'];
		$resultArray = [];
		foreach($traversable as $item) {
			$variables = [$as => $item];
			$content = static::renderChildrenWithVariablesStatic(
				$variables,
				$this->renderingContext->getTemplateVariableContainer(),
				$renderChildrenClosure
			);
			$resultArray[] = $content;
		}
		return $resultArray;
	}

}
