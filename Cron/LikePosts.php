<?php

namespace MoazamShakoor\CommunityBooster\Cron;

use XF;

class LikePosts
{
	public static function likePosts()
	{
		$options = \XF::options();

		$sessionDataRepo = self::getSessionDataRepo();
		$session = $sessionDataRepo->getActiveSession();

		if (!is_null($session)) {
			$userIds = explode(',', $session->session_user_ids);
		} else {
			return;
		}

		$excludedNodes  = $options->ms_cb_exclude_nodes;
		$postsNewerThan = $options->ms_cb_posts_newer;
		$postsNewerThan = strtotime(date('Y-m-d', strtotime('-' . $postsNewerThan . ' days')));
		$postsLikesCount = $options->ms_cb_posts_likes;
		$reactionRepo = \XF::repository('XF:Reaction');
		$usergroupsExclude = $options->ms_cb_usergroups_exclude;

		$likesLimit = rand($options->ms_cb_x_to_y_likes['min'], $options->ms_cb_x_to_y_likes['max']);

		$users = \XF::finder('XF:User')->where('user_id', $userIds)->order('RAND()')->limit(20)->fetch();
		
		$posts = \XF::finder('XF:Post')->where('post_date', '>', $postsNewerThan)->with('Thread')->where('reaction_score', '>=', $postsLikesCount)->where('Thread.node_id', '!=', $excludedNodes)->order('RAND()')->limit(20)->fetch()->toArray();

		if (count($users) > 0 && count($posts) > 0) {
			foreach ($users as $user) {
				$randomIndex = array_rand($posts, 1);

				$existingReaction = $reactionRepo->getReactionByContentAndReactionUser('post', $randomIndex, $user->user_id);
				$reaction_score = $posts[$randomIndex]->reaction_score;

				if (!$existingReaction && $reaction_score < $likesLimit) {
					$u = \XF::finder('XF:User')->where('user_id', $posts[$randomIndex]->user_id)->fetchOne();

					if (!$u->isMemberOf($usergroupsExclude)) {
						$reaction = $reactionRepo->reactToContent(
							1,
							'post',
							$randomIndex,
							$user,
							true
						);

						if($reaction) {
							$alert = self::findPostReactionAlertsForUserByContentId($reaction->reaction_user_id, $reaction->content_id)->fetchOne();
							$event_date = $alert->event_date;
							$reaction->fastUpdate('reaction_date', $event_date);
							
							if (($key = array_search($user->user_id, $userIds)) !== false) {
								unset($userIds[$key]);
							}
						}

					}
					
				}
			}

			self::updateSessionUserIds($userIds);
		}
	}

	public static function findPostReactionAlertsForUserByContentId($userId, $contentId)
	{
		$finder = \XF::finder('XF:UserAlert')
			->where('user_id', $userId)
			->where('content_id', $contentId)
			->where('content_type', 'post')
			->where('action', 'reaction');

		return $finder;
	}

	protected static function getSessionDataRepo()
	{
		return \XF::repository('MoazamShakoor\CommunityBooster:SessionData');
	}

	public static function updateSessionUserIds($userIds) {
        $sessionDataRepo = self::getSessionDataRepo();
        $session = $sessionDataRepo->getActiveSession();
        $session->session_user_ids = implode(',', $userIds);
        $session->save();
    }

}
