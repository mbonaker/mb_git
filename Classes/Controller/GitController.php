<?php

namespace MatteoBonaker\MbGit\Controller;


use MatteoBonaker\MbGit\Exception\GitException;
use MatteoBonaker\MbGit\Git\Remote;
use MatteoBonaker\MbGit\Resource\GitCapableResourceFactory;
use MatteoBonaker\MbGit\Resource\GitCapableResourceStorage;
use MatteoBonaker\MbGit\Service\ExtensionConfigurationService;
use TYPO3\CMS\Backend\Template\Components\ButtonBar;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Backend\View\BackendTemplateView;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Messaging\AbstractMessage;
use TYPO3\CMS\Core\Resource\Folder;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Fluid\View\StandaloneView;
use TYPO3\CMS\Lang\LanguageService;

class GitController extends ActionController {

	/**
	 * @var BackendTemplateView
	 */
	protected $view;

	/**
	 * BackendTemplateView Container
	 *
	 * @var BackendTemplateView
	 */
	protected $defaultViewObjectName = BackendTemplateView::class;

	/**
	 * @var Folder
	 */
	protected $currentFolder = null;

	protected function getFileListModuleIdentifier() {
		return 'file_MbGitList';
	}

	/**
	 * Returns the current BE user.
	 *
	 * @return \TYPO3\CMS\Core\Authentication\BackendUserAuthentication
	 */
	protected function getBackendUser() {
		return $GLOBALS['BE_USER'];
	}

	protected function getBeUserName() {
		$beUser = $this->getBackendUser();
		return $beUser->user['realName'];
	}

	protected function getBeUserEmail() {
		$beUser = $this->getBackendUser();
		return $beUser->user['email'];
	}

	protected function handleGitException(GitException $gitException) {
		// Render the error flash message fluid partial

		/** @var StandaloneView $result */
		$result = GeneralUtility::makeInstance(StandaloneView::class, $this->configurationManager->getContentObject());
		$result->assign('gitException', $gitException);
		$result->setTemplatePathAndFilename(ExtensionManagementUtility::extPath('mb_git') . '/Resources/Private/Partials/Exception.html');
		// TODO Translation
		$this->addFlashMessage($result->render(), 'Could not process the git command.', AbstractMessage::ERROR);
	}

	public function cloneAction() {
		$this->addBackButton();
		if ($this->request->hasArgument('source')) {
			try {
				$this->processGitClone();
			} catch(GitException $exception) {
				$this->handleGitException($exception);
			}
		}
		$this->view->assign('target', $this->request->getArgument('target'));
	}

	public function commitAction() {
		$this->addBackButton();
		$alright = true;
		if (!$this->getBeUserName()) {
			// TODO Translation
			$this->addFlashMessage('You need to set the name of your backend user first.', 'Error', AbstractMessage::ERROR);
			$alright = false;
		}
		if (!GeneralUtility::validEmail($this->getBeUserEmail())) {
			// TODO Translation
			$this->addFlashMessage('You need to set a valid email of your backend user first.', 'Error', AbstractMessage::ERROR);
			$alright = false;
		}
		if ($alright) {

			$saveAndCloseButton = $this->getButtonBar()->makeInputButton()
				->setName('_saveandclosedok')
				->setValue('1')
				->setForm('GitCommitForm')
				->setTitle($this->getLanguageService()->sL('LLL:EXT:lang/locallang_core.xlf:rm.saveCloseDoc'))
				->setShowLabelText(true)
				->setIcon($this->getIconFactory()->getIcon('actions-document-save-close', Icon::SIZE_SMALL));

			$this->getButtonBar()->addButton($saveAndCloseButton, ButtonBar::BUTTON_POSITION_LEFT, 1);

			$shallCloseAndSave = GeneralUtility::_GP('_saveandclosedok');
			if ($shallCloseAndSave) {
				try {
					$this->processGitCommit();
				} catch (GitException $gitException) {
					$this->handleGitException($gitException);
				}
			}
		} else {
			$this->forwardToFileList();
		}
		$this->view->assign('target', $this->request->getArgument('target'));
	}

	/**
	 * @return Folder The current folder, selected via GUI
	 */
	protected function getCurrentFolder() {
		if (!$this->currentFolder) {
			$resourceFactory = GitCapableResourceFactory::getInstance();
			$this->currentFolder = $resourceFactory->getFolderObjectFromCombinedIdentifier($this->request->getArgument('target'));
		}
		return $this->currentFolder;
	}

	/**
	 * @return GitCapableResourceStorage
	 */
	protected function getCurrentStorage() {
		// TODO Type checking
		/** @var GitCapableResourceStorage $storage */
		$storage = $this->getCurrentFolder()->getStorage();
		return $storage;
	}

	public function processGitCommit() {
		$this->getCurrentStorage()->gitCommit($this->getCurrentFolder(), $this->request->getArgument('message'), $this->getBeUserEmail(), $this->getBeUserName());
		// TODO Translation
		$this->addFlashMessage('Successfully committed the current state.', '', AbstractMessage::OK);
		$this->forwardToFileList();
	}

	private function processGitClone() {
		$extConf = ExtensionConfigurationService::getInstance();
		$name = $extConf->getGitConfigUserName();
		$mail = $extConf->getGitConfigUserEmail();
		$source = $this->request->getArgument('source');
		if (!empty($name) && GeneralUtility::validEmail($mail)) {
			$this->getCurrentStorage()->gitClone($this->getCurrentFolder(), $source);
			$this->getCurrentStorage()->gitConfig($this->getCurrentFolder(), 'user.name', $extConf->getGitConfigUserName());
			$this->getCurrentStorage()->gitConfig($this->getCurrentFolder(), 'user.email', $extConf->getGitConfigUserEmail());
			$sourceVar = var_export($source, true);
			// TODO Translation
			$this->addFlashMessage('Successfully cloned from ' . $sourceVar . '.', '', AbstractMessage::OK);
		} else {
			// TODO Translation
			$this->addFlashMessage('Please set the name and e-mail address in the ext settings correctly.', 'Could not clone', AbstractMessage::ERROR);
		}
		$this->forwardToFileList();
	}

	public function logAction() {
		$this->addBackButton();
		$this->view->assign('gitLog', $this->getCurrentStorage()->gitLog($this->getCurrentFolder()));
	}

	/**
	 * Returns the Language Service
	 * @return LanguageService
	 */
	protected function getLanguageService() {
		return $GLOBALS['LANG'];
	}

	/**
	 * @return ButtonBar
	 */
	protected function getButtonBar() {
		/** @var ButtonBar $buttonBar */
		$buttonBar = $this->view->getModuleTemplate()->getDocHeaderComponent()->getButtonBar();
		return $buttonBar;
	}

	/**
	 * @return IconFactory
	 */
	protected function getIconFactory() {
		/** @var IconFactory $iconFactory */
		$iconFactory = $this->view->getModuleTemplate()->getIconFactory();
		return $iconFactory;
	}

	protected function addBackButton($uri = null) {
		if($uri === null) {
			$uri = BackendUtility::getModuleUrl($this->getFileListModuleIdentifier(), ['id' => $this->request->getArgument('target')]);
		}
		$lang = $this->getLanguageService();

		// Back
		$returnButton = $this->getButtonBar()->makeLinkButton()
			->setHref($uri)
			->setTitle($lang->sL('LLL:EXT:lang/locallang_core.xlf:labels.goBack'))
			->setIcon($this->getIconFactory()->getIcon('actions-view-go-back', Icon::SIZE_SMALL));
		$this->getButtonBar()->addButton($returnButton, ButtonBar::BUTTON_POSITION_LEFT, 0);
	}

	public function remotesAction() {
		$this->addBackButton();
		$cmd = $this->request->hasArgument('cmd') ? $this->request->getArgument('cmd') : null;
		if ($cmd === 'delete') {
			$remoteName = $this->request->getArgument('remote');
			try {
				$this->getCurrentStorage()->gitRemoteRemove($this->getCurrentFolder(), $remoteName);
				// TODO Translate
				$this->addFlashMessage('Removed the remote ' . $remoteName);
			} catch (GitException $gitException) {
				$this->handleGitException($gitException);
			}
		}
		// Add the buttons
		$buttonBar = $this->getButtonBar();
		$iconFactory = $this->getIconFactory();

		$newButton = $buttonBar->makeInputButton()
			->setName('add')
			->setValue((string)true)
			->setForm('AddRemoteForm')
			->setTitle('Add remote')// TODO Translation
			->setIcon($iconFactory->getIcon('actions-document-new', Icon::SIZE_SMALL));
		$buttonBar->addButton($newButton, ButtonBar::BUTTON_POSITION_LEFT, 1);

		// Get the current remotes
		$remotes = $this->getCurrentStorage()->gitGetRemotes($this->getCurrentFolder());

		$this->view->assign('remotes', $remotes);
		$this->view->assign('target', $this->request->getArgument('target'));
	}

	/**
	 * @param string $remoteName
	 * @return \MatteoBonaker\MbGit\Git\Remote
	 */
	protected function getRemote($remoteName) {
		$remotes = $this->getCurrentStorage()->gitGetRemotes($this->getCurrentFolder());

		foreach ($remotes as $remote) {
			if ($remote->getName() == $remoteName) {
				return $remote;
				break;
			}
		}
		throw new \RuntimeException('Could not get the remote ' . var_export($remoteName, true), 1492628119);
	}

	public function remoteAction() {
		$this->addBackButton();
		// Save it
		$shallCloseAndSave = GeneralUtility::_GP('_saveandclosedok');
		$shallSave = GeneralUtility::_GP('_savedok');
		$shallSave = $shallSave || $shallCloseAndSave;
		try {
			if ($shallSave) {
				$remoteNameBefore = $this->request->getArgument('remote');
				$newName = $this->request->getArgument('name');
				$newUrl = $this->request->getArgument('url');
				if ($remoteNameBefore) {
					$remote = $this->getRemote($remoteNameBefore);
					if ($remote->getUrl() != $newUrl) {
						$remote = $this->getCurrentStorage()->gitRemoteSetUrl($this->getCurrentFolder(), $remote, $newUrl);
					}
					if ($remote->getName() != $newName) {
						$remote = $this->getCurrentStorage()->gitRemoteRename($this->getCurrentFolder(), $remote, $newName);
					}
					// TODO Translate
					$this->addFlashMessage('Changed the remote ' . $remote->getName());
				} else {
					$remote = new Remote($newName, $newUrl);
					$this->getCurrentStorage()->gitRemoteAdd($this->getCurrentFolder(), $remote);
					// TODO Translate
					$this->addFlashMessage('Added the remote ' . $remote->getName());
				}
			}
			if ($shallCloseAndSave) {
				$this->forward('remotes', 'Git', null, [
					'target' => $this->request->getArgument('target'),
				]);
			}
		} catch(GitException $gitException) {
			$this->handleGitException($gitException);
		}

		// Add the buttons
		$buttonBar = $this->getButtonBar();
		$iconFactory = $this->getIconFactory();
		$lang = $this->getLanguageService();

		$saveButton = $buttonBar->makeInputButton()
			->setName('_savedok')
			->setValue('1')
			->setForm('RemoteForm')
			->setIcon($iconFactory->getIcon('actions-document-save', Icon::SIZE_SMALL))
			->setTitle($lang->sL('LLL:EXT:lang/locallang_core.xlf:rm.saveDoc'));

		$saveAndCloseButton = $buttonBar->makeInputButton()
			->setName('_saveandclosedok')
			->setValue('1')
			->setForm('RemoteForm')
			->setTitle($lang->sL('LLL:EXT:lang/locallang_core.xlf:rm.saveCloseDoc'))
			->setIcon($iconFactory->getIcon('actions-document-save-close', Icon::SIZE_SMALL));

		$splitButtonElement = $buttonBar->makeSplitButton()
			->addItem($saveButton)
			->addItem($saveAndCloseButton);

		$buttonBar->addButton($splitButtonElement, ButtonBar::BUTTON_POSITION_LEFT, 1);

		// Assign the current remote
		$editName = $this->request->hasArgument('remote') ? $this->request->getArgument('remote') : null;
		if ($editName) {
			$this->view->assign('remote', isset($remote) ? $remote : $this->getRemote($editName));
		}

		$this->view->assign('target', $this->request->getArgument('target'));
	}

	public function pushAction() {
		/** @var Remote[] $allRemotes */
		$allRemotes = $this->getCurrentStorage()->gitGetRemotes($this->getCurrentFolder());
		$remoteName = GeneralUtility::_GP('git-push');
		$correctRemote = null;
		foreach($allRemotes as $remote) {
			if($remote->getName() === $remoteName) {
				$correctRemote = $remote;
			}
		}
		if($correctRemote === null) {
			throw new \RuntimeException('Could not push, because the remote ' . var_export($remoteName, true) . ' seems not to exist.', 1493492141);
		}
		try {
			$this->getCurrentStorage()->gitPush($this->getCurrentFolder(), $correctRemote);
			// TODO Translation
			$this->addFlashMessage('Pushed data to ' . $correctRemote->getName() . '.');
		} catch (GitException $gitException) {
			$this->handleGitException($gitException);
		}
		$this->forwardToFileList();
	}

	private function forwardToFileList() {
		GeneralUtility::_GETset('id', $this->request->getArgument('target'));
		$this->forward('index', 'FileList');
	}

}
