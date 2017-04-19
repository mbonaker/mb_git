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

namespace MatteoBonaker\MbGit;


use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\Folder;
use TYPO3\CMS\Core\Type\Bitmask\JsConfirmation;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Filelist\FileListEditIconHookInterface;

class FileList extends \TYPO3\CMS\Filelist\FileList {

	protected function getFileListModuleIdentifier() {
		return 'file_MbGitList';
	}

	protected function getFileRenameModuleIdentifier() {
		return 'file_rename_gitcapable';
	}

	protected function getFileEditModuleIdentifier() {
		return 'file_edit_gitcapable';
	}

	/**
	 * Wraps the directory-titles ($code) in a link to filelist/Modules/Filelist/index.php (id=$path) and sorting commands...
	 *
	 * @param string $code String to be wrapped
	 * @param string $folderIdentifier ID (path)
	 * @param string $col Sorting column
	 * @return string HTML
	 */
	public function linkWrapSort($code, $folderIdentifier, $col) {
		$params = ['id' => $folderIdentifier, 'SET' => [ 'sort' => $col ]];

		if ($this->sort === $col) {
			// Check reverse sorting
			$params['SET']['reverse'] = ($this->sortRev ? '0' : '1');
			$sortArrow = $this->iconFactory->getIcon('status-status-sorting-light-' . ($this->sortRev ? 'desc' : 'asc'), Icon::SIZE_SMALL)->render();
		} else {
			$params['SET']['reverse'] = 0;
			$sortArrow = '';
		}
		$href = BackendUtility::getModuleUrl($this->getFileListModuleIdentifier(), $params);
		return '<a href="' . htmlspecialchars($href) . '">' . $code . ' ' . $sortArrow . '</a>';
	}

	/**
	 * Wraps the directory-titles
	 *
	 * @param string $title String to be wrapped in links
	 * @param Folder $folderObject Folder to work on
	 * @return string HTML
	 */
	public function linkWrapDir($title, Folder $folderObject) {
		$href = BackendUtility::getModuleUrl($this->getFileListModuleIdentifier(), ['id' => $folderObject->getCombinedIdentifier()]);
		$onclick = ' onclick="' . htmlspecialchars(('top.document.getElementsByName("navigation")[0].contentWindow.Tree.highlightActiveItem("file","folder' . GeneralUtility::md5int($folderObject->getCombinedIdentifier()) . '_"+top.fsMod.currentBank)')) . '"';
		// Sometimes $code contains plain HTML tags. In such a case the string should not be modified!
		if ((string)$title === strip_tags($title)) {
			return '<a href="' . htmlspecialchars($href) . '"' . $onclick . ' title="' . htmlspecialchars($title) . '">' . GeneralUtility::fixed_lgd_cs($title, $this->fixedL) . '</a>';
		} else {
			return '<a href="' . htmlspecialchars($href) . '"' . $onclick . '>' . $title . '</a>';
		}
	}

	/**
	 * Returns list URL; This is the URL of the current script with id and imagemode parameters, that's all.
	 * The URL however is not relative, otherwise GeneralUtility::sanitizeLocalUrl() would say that
	 * the URL would be invalid
	 *
	 * @param string $altId
	 * @param string $table Table name to display. Enter "-1" for the current table.
	 * @param string $exclList Comma separated list of fields NOT to include ("sortField", "sortRev" or "firstElementNumber")
	 *
	 * @return string URL
	 */
	public function listURL($altId = '', $table = '-1', $exclList = '')
	{
		return GeneralUtility::linkThisScript([
			'target' => rawurlencode($this->folderObject->getCombinedIdentifier()),
			'imagemode' => $this->thumbs
		]);
	}

	/**
	 * Creates the edit control section
	 *
	 * @param File|Folder $fileOrFolderObject Array with information about the file/directory for which to make the edit control section for the listing.
	 * @return string HTML-table
	 */
	public function makeEdit($fileOrFolderObject)
	{
		$cells = [];
		$fullIdentifier = $fileOrFolderObject->getCombinedIdentifier();

		// Edit file content (if editable)
		if ($fileOrFolderObject instanceof File && $fileOrFolderObject->checkActionPermission('write') && GeneralUtility::inList($GLOBALS['TYPO3_CONF_VARS']['SYS']['textfile_ext'], $fileOrFolderObject->getExtension())) {
			$url = BackendUtility::getModuleUrl($this->getFileEditModuleIdentifier(), ['target' => $fullIdentifier]);
			$editOnClick = 'top.content.list_frame.location.href=' . GeneralUtility::quoteJSvalue($url) . '+\'&returnUrl=\'+top.rawurlencode(top.content.list_frame.document.location.pathname+top.content.list_frame.document.location.search);return false;';
			$cells['edit'] = '<a href="#" class="btn btn-default" onclick="' . htmlspecialchars($editOnClick) . '" title="' . $this->getLanguageService()->sL('LLL:EXT:lang/locallang_core.xlf:cm.editcontent') . '">'
				. $this->iconFactory->getIcon('actions-page-open', Icon::SIZE_SMALL)->render()
				. '</a>';
		} else {
			$cells['edit'] = $this->spaceIcon;
		}
		if ($fileOrFolderObject instanceof File) {
			$fileUrl = $fileOrFolderObject->getPublicUrl(true);
			if ($fileUrl) {
				$aOnClick = 'return top.openUrlInWindow(' . GeneralUtility::quoteJSvalue($fileUrl) . ', \'WebFile\');';
				$cells['view'] = '<a href="#" class="btn btn-default" onclick="' . htmlspecialchars($aOnClick) . '" title="' . $this->getLanguageService()->sL('LLL:EXT:lang/locallang_core.xlf:cm.view') . '">' . $this->iconFactory->getIcon('actions-document-view', Icon::SIZE_SMALL)->render() . '</a>';
			} else {
				$cells['view'] = $this->spaceIcon;
			}
		} else {
			$cells['view'] = $this->spaceIcon;
		}

		// replace file
		if ($fileOrFolderObject instanceof File && $fileOrFolderObject->checkActionPermission('replace')) {
			$url = BackendUtility::getModuleUrl('file_replace', ['target' => $fullIdentifier, 'uid' => $fileOrFolderObject->getUid()]);
			$replaceOnClick = 'top.content.list_frame.location.href = ' . GeneralUtility::quoteJSvalue($url) . '+\'&returnUrl=\'+top.rawurlencode(top.content.list_frame.document.location.pathname+top.content.list_frame.document.location.search);return false;';
			$cells['replace'] = '<a href="#" class="btn btn-default" onclick="' . $replaceOnClick . '"  title="' . $this->getLanguageService()->sL('LLL:EXT:lang/locallang_core.xlf:cm.replace') . '">' . $this->iconFactory->getIcon('actions-edit-replace', Icon::SIZE_SMALL)->render() . '</a>';
		}

		// rename the file
		if ($fileOrFolderObject->checkActionPermission('rename')) {
			$url = BackendUtility::getModuleUrl($this->getFileRenameModuleIdentifier(), ['target' => $fullIdentifier]);
			$renameOnClick = 'top.content.list_frame.location.href = ' . GeneralUtility::quoteJSvalue($url) . '+\'&returnUrl=\'+top.rawurlencode(top.content.list_frame.document.location.pathname+top.content.list_frame.document.location.search);return false;';
			$cells['rename'] = '<a href="#" class="btn btn-default" onclick="' . htmlspecialchars($renameOnClick) . '"  title="' . $this->getLanguageService()->sL('LLL:EXT:lang/locallang_core.xlf:cm.rename') . '">' . $this->iconFactory->getIcon('actions-edit-rename', Icon::SIZE_SMALL)->render() . '</a>';
		} else {
			$cells['rename'] = $this->spaceIcon;
		}
		if ($fileOrFolderObject->checkActionPermission('read')) {
			$infoOnClick = '';
			if ($fileOrFolderObject instanceof Folder) {
				$infoOnClick = 'top.launchView( \'_FOLDER\', ' . GeneralUtility::quoteJSvalue($fullIdentifier) . ');return false;';
			} elseif ($fileOrFolderObject instanceof File) {
				$infoOnClick = 'top.launchView( \'_FILE\', ' . GeneralUtility::quoteJSvalue($fullIdentifier) . ');return false;';
			}
			$cells['info'] = '<a href="#" class="btn btn-default" onclick="' . htmlspecialchars($infoOnClick) . '" title="' . $this->getLanguageService()->sL('LLL:EXT:lang/locallang_core.xlf:cm.info') . '">' . $this->iconFactory->getIcon('actions-document-info', Icon::SIZE_SMALL)->render() . '</a>';
		} else {
			$cells['info'] = $this->spaceIcon;
		}

		// delete the file
		if ($fileOrFolderObject->checkActionPermission('delete')) {
			$identifier = $fileOrFolderObject->getIdentifier();
			if ($fileOrFolderObject instanceof Folder) {
				$referenceCountText = BackendUtility::referenceCount('_FILE', $identifier, ' ' . $this->getLanguageService()->sL('LLL:EXT:lang/locallang_core.xlf:labels.referencesToFolder'));
			} else {
				$referenceCountText = BackendUtility::referenceCount('sys_file', $fileOrFolderObject->getUid(), ' ' . $this->getLanguageService()->sL('LLL:EXT:lang/locallang_core.xlf:labels.referencesToFile'));
			}

			if ($this->getBackendUser()->jsConfirmation(JsConfirmation::DELETE)) {
				$confirmationCheck = '1';
			} else {
				$confirmationCheck = '0';
			}

			$deleteUrl = BackendUtility::getModuleUrl('tce_file');
			$confirmationMessage = sprintf($this->getLanguageService()->sL('LLL:EXT:lang/locallang_core.xlf:mess.delete'), $fileOrFolderObject->getName()) . $referenceCountText;
			$title = $this->getLanguageService()->sL('LLL:EXT:lang/locallang_core.xlf:cm.delete');
			$cells['delete'] = '<a href="#" class="btn btn-default t3js-filelist-delete" data-content="' . htmlspecialchars($confirmationMessage)
				. '" data-check="' . $confirmationCheck
				. '" data-delete-url="' . htmlspecialchars($deleteUrl)
				. '" data-title="' . htmlspecialchars($title)
				. '" data-identifier="' . htmlspecialchars($fileOrFolderObject->getCombinedIdentifier())
				. '" data-veri-code="' . $this->getBackendUser()->veriCode()
				. '" title="' . htmlspecialchars($title) . '">'
				. $this->iconFactory->getIcon('actions-edit-delete', Icon::SIZE_SMALL)->render() . '</a>';
		} else {
			$cells['delete'] = $this->spaceIcon;
		}

		// Hook for manipulating edit icons.
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['fileList']['editIconsHook'])) {
			$cells['__fileOrFolderObject'] = $fileOrFolderObject;
			foreach ($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['fileList']['editIconsHook'] as $classData) {
				$hookObject = GeneralUtility::getUserObj($classData);
				if (!$hookObject instanceof FileListEditIconHookInterface) {
					throw new \UnexpectedValueException(
						$classData . ' must implement interface ' . FileListEditIconHookInterface::class,
						1235225797
					);
				}
				$hookObject->manipulateEditIcons($cells, $this);
			}
			unset($cells['__fileOrFolderObject']);
		}
		// Compile items into a DIV-element:
		return '<div class="btn-group">' . implode('', $cells) . '</div>';
	}
}
