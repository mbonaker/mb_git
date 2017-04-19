<?php

namespace MatteoBonaker\MbGit\Controller;


use MatteoBonaker\MbGit\Exception\GitException;
use MatteoBonaker\MbGit\Resource\GitCapableResourceFactory;
use MatteoBonaker\MbGit\Resource\GitCapableResourceStorage;
use MatteoBonaker\MbGit\Service\ExtensionConfigurationService;
use TYPO3\CMS\Backend\View\BackendTemplateView;
use TYPO3\CMS\Core\Messaging\AbstractMessage;
use TYPO3\CMS\Core\Resource\Folder;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Fluid\View\StandaloneView;

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
		// TODO Add an arrow button to go back
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
		if ($alright && $this->request->hasArgument('run')) {
			try {
				$this->processGitCommit();
			} catch(GitException $gitException) {
				$this->handleGitException($gitException);
			}
		}
		if (!$alright) {
			// TODO Set the cwd
			$this->forward('index', 'FileList', null, [
				'noGitCommitting' => true
			] + $this->request->getArguments());
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
		// TODO Set the cwd
		$this->forward('index', 'FileList');
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
		// TODO Set the cwd
		$this->forward('index', 'FileList');
	}

	public function logAction() {
		$this->view->assign('gitLog', $this->getCurrentStorage()->gitLog($this->getCurrentFolder()));
	}

}
