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

namespace MatteoBonaker\MbGit\Controller;


use MatteoBonaker\MbGit\Exception\GitException;
use MatteoBonaker\MbGit\FileList;
use MatteoBonaker\MbGit\Resource\GitCapableResourceFactory;
use MatteoBonaker\MbGit\Resource\GitCapableResourceStorage;
use MatteoBonaker\MbGit\Service\ExtensionConfigurationService;
use TYPO3\CMS\Backend\Clipboard\Clipboard;
use TYPO3\CMS\Backend\Template\Components\ButtonBar;
use TYPO3\CMS\Backend\Template\DocumentTemplate;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Messaging\AbstractMessage;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Resource\Driver\LocalDriver;
use TYPO3\CMS\Core\Resource\DuplicationBehavior;
use TYPO3\CMS\Core\Resource\Exception;
use TYPO3\CMS\Core\Resource\Folder;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\File\ExtendedFileUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MathUtility;
use TYPO3\CMS\Fluid\View\StandaloneView;

class FileListController extends \TYPO3\CMS\Filelist\Controller\FileListController {

	/**
	 * Initialize variables, file object
	 * Incoming GET vars include id, pointer, table, imagemode
	 *
	 * @return void
	 * @throws \RuntimeException
	 * @throws Exception\InsufficientFolderAccessPermissionsException
	 */
	public function initializeObject() {
		$this->doc = GeneralUtility::makeInstance(DocumentTemplate::class);
		$this->getLanguageService()->includeLLFile('EXT:lang/locallang_mod_file_list.xlf');
		$this->getLanguageService()->includeLLFile('EXT:lang/locallang_misc.xlf');

		// Setting GPvars:
		$this->id = ($combinedIdentifier = GeneralUtility::_GP('id'));
		$this->pointer = GeneralUtility::_GP('pointer');
		$this->table = GeneralUtility::_GP('table');
		$this->imagemode = GeneralUtility::_GP('imagemode');
		$this->cmd = GeneralUtility::_GP('cmd');
		$this->overwriteExistingFiles = DuplicationBehavior::cast(GeneralUtility::_GP('overwriteExistingFiles'));

		try {
			if ($combinedIdentifier) {
				/** @var $resourceFactory GitCapableResourceFactory */
				$resourceFactory = GeneralUtility::makeInstance(GitCapableResourceFactory::class);
				$storage = $resourceFactory->getStorageObjectFromCombinedIdentifier($combinedIdentifier);
				$identifier = substr($combinedIdentifier, strpos($combinedIdentifier, ':') + 1);
				if (!$storage->hasFolder($identifier)) {
					$identifier = $storage->getFolderIdentifierFromFileIdentifier($identifier);
				}

				$this->folderObject = $resourceFactory->getFolderObjectFromCombinedIdentifier($storage->getUid() . ':' . $identifier);
				// Disallow access to fallback storage 0
				if ($storage->getUid() === 0) {
					throw new Exception\InsufficientFolderAccessPermissionsException('You are not allowed to access files outside your storages',
						1434539815);
				}
				// Disallow the rendering of the processing folder (e.g. could be called manually)
				if ($this->folderObject && $storage->isProcessingFolder($this->folderObject)) {
					$this->folderObject = $storage->getRootLevelFolder();
				}
			} else {
				// Take the first object of the first storage
				$fileStorages = $this->getBackendUser()->getFileStorages();
				$fileStorage = reset($fileStorages);
				if ($fileStorage) {
					$fileStorage = GitCapableResourceStorage::fromGitIncapableResourceStorage($fileStorage);
					$this->folderObject = $fileStorage->getRootLevelFolder();
				} else {
					throw new \RuntimeException('Could not find any folder to be displayed.', 1349276894);
				}
			}

			if ($this->folderObject && !$this->folderObject->getStorage()->isWithinFileMountBoundaries($this->folderObject)) {
				throw new \RuntimeException('Folder not accessible.', 1430409089);
			}
		} catch (Exception\InsufficientFolderAccessPermissionsException $permissionException) {
			$this->folderObject = null;
			$this->errorMessage = GeneralUtility::makeInstance(FlashMessage::class,
				sprintf(
					$this->getLanguageService()->getLL('missingFolderPermissionsMessage', true),
					htmlspecialchars($this->id)
				),
				$this->getLanguageService()->getLL('missingFolderPermissionsTitle', true),
				FlashMessage::NOTICE
			);
		} catch (Exception $fileException) {
			// Set folder object to null and throw a message later on
			$this->folderObject = null;
			// Take the first object of the first storage
			$fileStorages = $this->getBackendUser()->getFileStorages();
			$fileStorage = reset($fileStorages);
			if ($fileStorage instanceof \TYPO3\CMS\Core\Resource\ResourceStorage) {
				$this->folderObject = $fileStorage->getRootLevelFolder();
				if (!$fileStorage->isWithinFileMountBoundaries($this->folderObject)) {
					$this->folderObject = null;
				}
			}
			$this->errorMessage = GeneralUtility::makeInstance(FlashMessage::class,
				sprintf(
					$this->getLanguageService()->getLL('folderNotFoundMessage', true),
					htmlspecialchars($this->id)
				),
				$this->getLanguageService()->getLL('folderNotFoundTitle', true),
				FlashMessage::NOTICE
			);
		} catch (\RuntimeException $e) {
			$this->folderObject = null;
			$this->errorMessage = GeneralUtility::makeInstance(FlashMessage::class,
				$e->getMessage() . ' (' . $e->getCode() . ')',
				$this->getLanguageService()->getLL('folderNotFoundTitle', true),
				FlashMessage::NOTICE
			);
		}

		if ($this->folderObject && !$this->folderObject->getStorage()->checkFolderActionPermission('read',
				$this->folderObject)
		) {
			$this->folderObject = null;
		}

		// Configure the "menu" - which is used internally to save the values of sorting, displayThumbs etc.
		$this->menuConfig();
	}

	public function indexAction() {
		$pageRenderer = $this->view->getModuleTemplate()->getPageRenderer();
		$pageRenderer->setTitle($this->getLanguageService()->getLL('files'));

		// There there was access to this file path, continue, make the list
		if ($this->folderObject) {

			try {
				$this->processGitCommand();
			} catch(GitException $gitException) {
				// Render the error flash message fluid partial

				/** @var StandaloneView $result */
				$result = GeneralUtility::makeInstance(StandaloneView::class, $this->configurationManager->getContentObject());
				$result->assign('gitException', $gitException);
				$result->setTemplatePathAndFilename(ExtensionManagementUtility::extPath('mb_git') . '/Resources/Private/Partials/Exception.html');
				// TODO Translation
				$this->addFlashMessage($result->render(), 'Could not process the git command.', AbstractMessage::ERROR);
			}

			// Create fileListing object
			$this->filelist = GeneralUtility::makeInstance(FileList::class, $this);
			$this->filelist->thumbs = $GLOBALS['TYPO3_CONF_VARS']['GFX']['thumbnails'] && $this->MOD_SETTINGS['displayThumbs'];
			// Create clipboard object and initialize that
			$this->filelist->clipObj = GeneralUtility::makeInstance(Clipboard::class);
			$this->filelist->clipObj->fileMode = 1;
			$this->filelist->clipObj->initializeClipboard();
			$CB = GeneralUtility::_GET('CB');
			if ($this->cmd == 'setCB') {
				$CB['el'] = $this->filelist->clipObj->cleanUpCBC(array_merge(GeneralUtility::_POST('CBH'),
					(array)GeneralUtility::_POST('CBC')), '_FILE');
			}
			if (!$this->MOD_SETTINGS['clipBoard']) {
				$CB['setP'] = 'normal';
			}
			$this->filelist->clipObj->setCmd($CB);
			$this->filelist->clipObj->cleanCurrent();
			// Saves
			$this->filelist->clipObj->endClipboard();
			// If the "cmd" was to delete files from the list (clipboard thing), do that:
			if ($this->cmd == 'delete') {
				$items = $this->filelist->clipObj->cleanUpCBC(GeneralUtility::_POST('CBC'), '_FILE', 1);
				if (!empty($items)) {
					// Make command array:
					$FILE = [];
					foreach ($items as $v) {
						$FILE['delete'][] = ['data' => $v];
					}
					// Init file processing object for deleting and pass the cmd array.
					/** @var ExtendedFileUtility $fileProcessor */
					$fileProcessor = GeneralUtility::makeInstance(ExtendedFileUtility::class);
					$fileProcessor->init([], $GLOBALS['TYPO3_CONF_VARS']['BE']['fileExtensions']);
					$fileProcessor->setActionPermissions();
					$fileProcessor->setExistingFilesConflictMode($this->overwriteExistingFiles);
					$fileProcessor->start($FILE);
					$fileProcessor->processData();
				}
			}
			// Start up filelisting object, include settings.
			$this->pointer = MathUtility::forceIntegerInRange($this->pointer, 0, 100000);
			$this->filelist->start($this->folderObject, $this->pointer, $this->MOD_SETTINGS['sort'], $this->MOD_SETTINGS['reverse'], $this->MOD_SETTINGS['clipBoard'], $this->MOD_SETTINGS['bigControlPanel']);
			// Generate the list
			$this->filelist->generateList();
			// Set top JavaScript:
			$this->view->getModuleTemplate()->addJavaScriptCode(
				'FileListIndex',
				'if (top.fsMod) top.fsMod.recentIds["file"] = "' . rawurlencode($this->id) . '";' . $this->filelist->CBfunctions() . '
                function jumpToUrl(URL) {
                    window.location.href = URL;
                    return false;
                }
                ');
			$pageRenderer->loadRequireJsModule('TYPO3/CMS/Filelist/FileDelete');
			$pageRenderer->addInlineLanguageLabelFile(
				ExtensionManagementUtility::extPath('lang') . 'locallang_alt_doc.xlf',
				'buttons'
			);

			// Include DragUploader only if we have write access
			if ($this->folderObject->getStorage()->checkUserActionPermission('add', 'File')
				&& $this->folderObject->checkActionPermission('write')
			) {
				$pageRenderer->loadRequireJsModule('TYPO3/CMS/Backend/DragUploader');
				$pageRenderer->addInlineLanguageLabelFile(
					ExtensionManagementUtility::extPath('lang') . 'locallang_core.xlf',
					'file_upload'
				);
				$pageRenderer->addInlineLanguageLabelArray([
					'permissions.read' => $this->getLanguageService()->getLL('read'),
					'permissions.write' => $this->getLanguageService()->getLL('write'),
				]);
			}

			// Setting up the buttons
			$this->registerButtons();

			$pageRecord = [
				'_additional_info' => $this->filelist->getFolderInfo(),
				'combined_identifier' => $this->folderObject->getCombinedIdentifier(),
			];
			$this->view->getModuleTemplate()->getDocHeaderComponent()->setMetaInformation($pageRecord);

			$this->view->assign('headline', $this->getModuleHeadline());
			$this->view->assign('listHtml', $this->filelist->HTMLcode);

			$this->view->assign('checkboxes', [
				'bigControlPanel' => [
					'enabled' => $this->getBackendUser()->getTSConfigVal('options.file_list.enableDisplayBigControlPanel') === 'selectable',
					'label' => $this->getLanguageService()->getLL('bigControlPanel', true),
					'html' => BackendUtility::getFuncCheck($this->id, 'SET[bigControlPanel]',
						$this->MOD_SETTINGS['bigControlPanel'], '', '', 'id="bigControlPanel"'),
				],
				'displayThumbs' => [
					'enabled' => $GLOBALS['TYPO3_CONF_VARS']['GFX']['thumbnails'] && $this->getBackendUser()->getTSConfigVal('options.file_list.enableDisplayThumbnails') === 'selectable',
					'label' => $this->getLanguageService()->getLL('displayThumbs', true),
					'html' => BackendUtility::getFuncCheck($this->id, 'SET[displayThumbs]',
						$this->MOD_SETTINGS['displayThumbs'], '', '', 'id="checkDisplayThumbs"'),
				],
				'enableClipBoard' => [
					'enabled' => $this->getBackendUser()->getTSConfigVal('options.file_list.enableClipBoard') === 'selectable',
					'label' => $this->getLanguageService()->getLL('clipBoard', true),
					'html' => BackendUtility::getFuncCheck($this->id, 'SET[clipBoard]',
						$this->MOD_SETTINGS['clipBoard'], '', '', 'id="checkClipBoard"'),
				]
			]);
			$this->view->assign('showClipBoard', (bool)$this->MOD_SETTINGS['clipBoard']);
			$this->view->assign('clipBoardHtml', $this->filelist->clipObj->printClipboard());
			$this->view->assign('folderIdentifier', $this->folderObject->getCombinedIdentifier());
			$this->view->assign('fileDenyPattern', $GLOBALS['TYPO3_CONF_VARS']['BE']['fileDenyPattern']);
			$this->view->assign('maxFileSize', GeneralUtility::getMaxUploadFileSize() * 1024);
		} else {
			$this->forward('missingFolder');
		}
	}

	/**
	 * Create the panel of buttons for submitting the form or otherwise perform operations.
	 */
	protected function registerButtons() {
		/** @var ButtonBar $buttonBar */
		$buttonBar = $this->view->getModuleTemplate()->getDocHeaderComponent()->getButtonBar();

		/** @var IconFactory $iconFactory */
		$iconFactory = $this->view->getModuleTemplate()->getIconFactory();

		/** @var $resourceFactory ResourceFactory */
		$resourceFactory = GeneralUtility::makeInstance(ResourceFactory::class);

		$lang = $this->getLanguageService();

		// Refresh page
		$refreshLink = GeneralUtility::linkThisScript(
			[
				'target' => rawurlencode($this->folderObject->getCombinedIdentifier()),
				'imagemode' => $this->filelist->thumbs
			]
		);
		$refreshButton = $buttonBar->makeLinkButton()
			->setHref($refreshLink)
			->setTitle($lang->sL('LLL:EXT:lang/locallang_core.xlf:labels.reload'))
			->setIcon($iconFactory->getIcon('actions-refresh', Icon::SIZE_SMALL));
		$buttonBar->addButton($refreshButton, ButtonBar::BUTTON_POSITION_RIGHT);

		// Level up
		try {
			$currentStorage = $this->folderObject->getStorage();
			$parentFolder = $this->folderObject->getParentFolder();
			if ($parentFolder->getIdentifier() !== $this->folderObject->getIdentifier()
				&& $currentStorage->isWithinFileMountBoundaries($parentFolder)
			) {
				$levelUpClick = 'top.document.getElementsByName("navigation")[0].contentWindow.Tree.highlightActiveItem("file","folder'
					. GeneralUtility::md5int($parentFolder->getCombinedIdentifier()) . '_"+top.fsMod.currentBank)';
				$levelUpButton = $buttonBar->makeLinkButton()
					->setHref(BackendUtility::getModuleUrl('file_MbGitList', ['id' => $parentFolder->getCombinedIdentifier()]))
					->setOnClick($levelUpClick)
					->setTitle($lang->sL('LLL:EXT:lang/locallang_core.xlf:labels.upOneLevel'))
					->setIcon($iconFactory->getIcon('actions-view-go-up', Icon::SIZE_SMALL));
				$buttonBar->addButton($levelUpButton, ButtonBar::BUTTON_POSITION_LEFT, 1);
			}
		} catch (\Exception $e) {
		}

		// Shortcut
		if ($this->getBackendUser()->mayMakeShortcut()) {
			$shortCutButton = $buttonBar->makeShortcutButton()->setModuleName('file_MbGitList');
			$buttonBar->addButton($shortCutButton, ButtonBar::BUTTON_POSITION_RIGHT);
		}

		// Upload button (only if upload to this directory is allowed)
		if ($this->folderObject && $this->folderObject->getStorage()->checkUserActionPermission('add',
				'File') && $this->folderObject->checkActionPermission('write')
		) {
			$uploadButton = $buttonBar->makeLinkButton()
				->setHref(BackendUtility::getModuleUrl(
					'file_upload',
					[
						'target' => $this->folderObject->getCombinedIdentifier(),
						'returnUrl' => $this->filelist->listURL(),
					]
				))
				->setClasses('t3js-drag-uploader-trigger')
				->setTitle($lang->sL('LLL:EXT:lang/locallang_core.xlf:cm.upload'))
				->setIcon($iconFactory->getIcon('actions-edit-upload', Icon::SIZE_SMALL));
			$buttonBar->addButton($uploadButton, ButtonBar::BUTTON_POSITION_LEFT, 1);
		}

		// New folder button
		if ($this->folderObject && $this->folderObject->checkActionPermission('write')
			&& ($this->folderObject->getStorage()->checkUserActionPermission('add',
					'File') || $this->folderObject->checkActionPermission('add'))
		) {
			$newButton = $buttonBar->makeLinkButton()
				->setHref(BackendUtility::getModuleUrl(
					'file_newfolder',
					[
						'target' => $this->folderObject->getCombinedIdentifier(),
						'returnUrl' => $this->filelist->listURL(),
					]
				))
				->setTitle($lang->sL('LLL:EXT:lang/locallang_core.xlf:cm.new'))
				->setIcon($iconFactory->getIcon('actions-document-new', Icon::SIZE_SMALL));
			$buttonBar->addButton($newButton, ButtonBar::BUTTON_POSITION_LEFT, 1);
		}

		// Add paste button if clipboard is initialized
		if ($this->filelist->clipObj instanceof Clipboard && $this->folderObject->checkActionPermission('write')) {
			$elFromTable = $this->filelist->clipObj->elFromTable('_FILE');
			if (!empty($elFromTable)) {
				$addPasteButton = true;
				$elToConfirm = [];
				foreach ($elFromTable as $key => $element) {
					$clipBoardElement = $resourceFactory->retrieveFileOrFolderObject($element);
					if ($clipBoardElement instanceof Folder && $clipBoardElement->getStorage()->isWithinFolder($clipBoardElement,
							$this->folderObject)
					) {
						$addPasteButton = false;
					}
					$elToConfirm[$key] = $clipBoardElement->getName();
				}
				if ($addPasteButton) {
					$confirmText = $this->filelist->clipObj
						->confirmMsgText('_FILE', $this->folderObject->getReadablePath(), 'into', $elToConfirm);
					$pasteButton = $buttonBar->makeLinkButton()
						->setHref($this->filelist->clipObj
							->pasteUrl('_FILE', $this->folderObject->getCombinedIdentifier()))
						->setClasses('t3js-modal-trigger')
						->setDataAttributes([
							'severity' => 'warning',
							'content' => $confirmText,
							'title' => $lang->getLL('clip_paste')
						])
						->setTitle($lang->getLL('clip_paste'))
						->setIcon($iconFactory->getIcon('actions-document-paste-into', Icon::SIZE_SMALL));
					$buttonBar->addButton($pasteButton, ButtonBar::BUTTON_POSITION_LEFT, 2);
				}
			}
		}

		/** @var ButtonBar $buttonBar */
		$buttonBar = $this->view->getModuleTemplate()->getDocHeaderComponent()->getButtonBar();

		/** @var IconFactory $iconFactory */
		$iconFactory = $this->view->getModuleTemplate()->getIconFactory();

		// Git button
		$gitButton = null;
		if ($this->getGitStorage()->isGitVersioned($this->folderObject)) {

			$newButton = $buttonBar->makeInputButton()
				->setName('git-commit')
				->setValue((string)true)
				->setForm('GitCommitForm')
				->setTitle('Commit changes')// TODO Translation
				->setIcon($iconFactory->getIcon('octicons-check', Icon::SIZE_SMALL));
			$buttonBar->addButton($newButton, ButtonBar::BUTTON_POSITION_LEFT, 2);

			$newButton = $buttonBar->makeInputButton()
				->setName('git-log')
				->setValue((string)true)
				->setForm('GitLogForm')
				->setTitle('View log')// TODO Translation
				->setIcon($iconFactory->getIcon('octicons-history', Icon::SIZE_SMALL));
			$buttonBar->addButton($newButton, ButtonBar::BUTTON_POSITION_LEFT, 2);

			$newButton = $buttonBar->makeInputButton()
				->setName('git-remotes')
				->setValue((string)true)
				->setForm('GitRemotesForm')
				->setTitle('View and change remotes')// TODO Translation
				->setIcon($iconFactory->getIcon('octicons-server', Icon::SIZE_SMALL));
			$buttonBar->addButton($newButton, ButtonBar::BUTTON_POSITION_LEFT, 2);

			// TODO Git remote (octicons-server)

			// TODO Git push (octicons-cloud-upload)

		} elseif($this->folderObject) {

			$newButton = $buttonBar->makeInputButton()
				->setName('git-init')
				->setValue((string)true)
				->setForm('FileListController')
				->setTitle('Git init') // TODO Translation
				->setIcon($iconFactory->getIcon('tx-mbgit-git-logo', Icon::SIZE_SMALL));
			$buttonBar->addButton($newButton, ButtonBar::BUTTON_POSITION_LEFT, 2);

			$newButton = $buttonBar->makeInputButton()
				->setName('git-clone')
				->setValue((string)true)
				->setForm('GitCloneForm')
				->setTitle('Git clone') // TODO Translation
				->setIcon($iconFactory->getIcon('octicons-cloud-download', Icon::SIZE_SMALL));
			$buttonBar->addButton($newButton, ButtonBar::BUTTON_POSITION_LEFT, 2);

		}
	}

	/**
	 * @return GitCapableResourceStorage
	 */
	protected function getGitStorage() {
		$storage = $this->folderObject->getStorage();
		if(!$storage instanceof GitCapableResourceStorage) {
			throw new \RuntimeException('Could not get the git storage of the current folder.');
		}
		return $storage;
	}

	private function mayDoGitCommit() {
		return !$this->request->hasArgument('noGitCommitting') || !$this->request->getArgument('noGitCommitting');
	}

	/**
	 * Process the post commands given to this controller that are related to git.
	 */
	public function processGitCommand() {
		if (GeneralUtility::_POST('git-init')) {
			$extConf = ExtensionConfigurationService::getInstance();
			$name = $extConf->getGitConfigUserName();
			$mail = $extConf->getGitConfigUserEmail();
			if (!empty($name) && GeneralUtility::validEmail($mail)) {
				$this->getGitStorage()->gitInit($this->folderObject);
				$this->getGitStorage()->gitConfig($this->folderObject, 'user.name', $extConf->getGitConfigUserName());
				$this->getGitStorage()->gitConfig($this->folderObject, 'user.email', $extConf->getGitConfigUserEmail());
				// TODO Translation
				$this->addFlashMessage('Git init done.');
			} else {
				// TODO Translation
				$this->addFlashMessage('Please set the name and e-mail address in the ext settings correctly.', 'Could not init', AbstractMessage::ERROR);
			}
		}
		if (GeneralUtility::_POST('git-commit') && $this->mayDoGitCommit()) {
			$this->forward('commit', 'Git');
		}
	}

}
