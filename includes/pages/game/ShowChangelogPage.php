<?php

namespace HiveNova\Page\Game;

use HiveNova\Core\ReleaseNotesService;

class ShowChangelogPage extends AbstractGamePage
{
	public static $requireModule = 0;

	function __construct() 
	{
		parent::__construct();
	}
	
	function show() 
	{
		$html = '';
		foreach ((new ReleaseNotesService())->listReleases() as $release) {
			$html .= '<h3>' . htmlspecialchars($release['version'], ENT_QUOTES, 'UTF-8') . '</h3><ul>';
			foreach ($release['items'] as $item) {
				$html .= '<li>' . htmlspecialchars($item, ENT_QUOTES, 'UTF-8') . '</li>';
			}
			$html .= '</ul>';
		}

		$this->assign(array(
			'ChangelogList'	=> $html,
		));
		
		$this->display('page.changelog.default.tpl');
	}
}