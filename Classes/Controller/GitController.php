<?php

namespace MatteoBonaker\MbGit\Controller;


use MatteoBonaker\MbGit\Exception\GitException;
use MatteoBonaker\MbGit\Resource\GitCapableResourceFactory;
use MatteoBonaker\MbGit\Resource\GitCapableResourceStorage;
use TYPO3\CMS\Backend\View\BackendTemplateView;
use TYPO3\CMS\Core\Messaging\AbstractMessage;
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

	public function processGitCommit() {
		/** @var $resourceFactory GitCapableResourceFactory */
		$resourceFactory = GeneralUtility::makeInstance(GitCapableResourceFactory::class);
		$folderObject = $resourceFactory->getFolderObjectFromCombinedIdentifier($this->request->getArgument('target'));
		// TODO Type checking
		/** @var GitCapableResourceStorage $storage */
		$storage = $folderObject->getStorage();
		$storage->gitCommit($folderObject, $this->request->getArgument('message'), $this->getBeUserEmail(), $this->getBeUserName());
		// TODO Translation
		$this->addFlashMessage('Successfully committed the current state.', '', AbstractMessage::OK);
		// TODO Set the cwd
		$this->forward('index', 'FileList');
	}

}
