<?php

namespace MoazamShakoor\CommunityBooster\XF\Entity;

use XF;

class UserAlert extends XFCP_UserAlert
{
    protected function _postSave()
    {
        parent::_postSave();

        if($this->isInsert()) {
            
            $sessionDataRepo = $this->getSessionDataRepo();
            $session = $sessionDataRepo->getActiveSession();
            if(!is_null($session)) {
                $userIds = explode(',', $session->session_user_ids);

                $startTime = $session->session_start_time;
				$currentTime = XF::$time;
                if($this->content_type == 'post' && $this->action == 'reaction' && in_array($this->user_id, $userIds)) {
                    $this->fastUpdate('event_date', mt_rand($startTime, $currentTime));
                }
                
            }
        }
        
    }

    protected function getSessionDataRepo()
	{
		return \XF::repository('MoazamShakoor\CommunityBooster:SessionData');
	}

}
