<?php

namespace MoazamShakoor\CommunityBooster\Repository;

use XF\Mvc\Entity\Repository;

class SessionData extends Repository
{

	public function getActiveSession()
	{
		/** @var \MoazamShakoor\CommunityBooster\Finder\SessionData $finder */
		return $this->finder('MoazamShakoor\CommunityBooster:SessionData')->fetchOne();
	}

}