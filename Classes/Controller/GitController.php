<?php

namespace MatteoBonaker\MbGit\Controller;


use MatteoBonaker\MbGit\Resource\GitCapableResourceFactory;
use MatteoBonaker\MbGit\Resource\GitCapableResourceStorage;
use TYPO3\CMS\Backend\View\BackendTemplateView;
use TYPO3\CMS\Core\Messaging\AbstractMessage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;

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

	public function commitAction() {
		$beUser = $this->getBackendUser();
		$name = $beUser->user['realName'];
		$mail = $beUser->user['email'];
		$alright = true;
		if (empty($name)) {
			// TODO Translation
			$this->addFlashMessage('You need to set the name of your backend user first.', 'Error', AbstractMessage::ERROR);
			$alright = false;
		}
		if (!GeneralUtility::validEmail($mail)) {
			// TODO Translation
			$this->addFlashMessage('You need to set a valid email of your backend user first.', 'Error', AbstractMessage::ERROR);
			$alright = false;
		}
		if ($alright && $this->request->hasArgument('run')) {
			/** @var $resourceFactory GitCapableResourceFactory */
			$resourceFactory = GeneralUtility::makeInstance(GitCapableResourceFactory::class);
			$folderObject = $resourceFactory->getFolderObjectFromCombinedIdentifier($this->request->getArgument('target'));
			// TODO Type checking
			/** @var GitCapableResourceStorage $storage */
			$storage = $folderObject->getStorage();
			$storage->gitCommit($folderObject, $this->request->getArgument('message'), $mail, $name);
			// TODO Translation
			$this->addFlashMessage('Successfully committed the current state.', '', AbstractMessage::OK);
			// TODO Set the cwd
			$this->forward('index', 'FileList');
		}
		if (!$alright) {
			// TODO Set the cwd
			$this->forward('index', 'FileList', null, [
				'noGitCommitting' => true
			] + $this->request->getArguments());
		}
		$this->view->assign('target', $this->request->getArgument('target'));
	}

}
