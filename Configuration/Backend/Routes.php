<?php
use MatteoBonaker\MbGit\Controller;

return [

	// Rename a file
	'file_rename_gitcapable' => [
		'path' => '/file/rename/gitcapable',
		'target' => Controller\File\RenameFileController::class . '::mainAction'
	],

	// Editing the contents of a file
	'file_edit_gitcapable' => [
		'path' => '/file/editcontent/gitcapable',
		'target' => Controller\File\EditFileController::class . '::mainAction'
	],

];
