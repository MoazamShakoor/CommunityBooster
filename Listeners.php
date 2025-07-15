<?php

namespace MoazamShakoor\CommunityBooster;

use DateTime;
use XF;

class Listeners
{
    public static $_thread, $_forum, $options;

    public static function appPubStartBegin(\XF\Pub\App $app)
    {

        $sessionDataRepo = self::getSessionDataRepo();
        $session = $sessionDataRepo->getActiveSession();
        self::$options = XF::options();

        if (!self::checkTimeActivity()) {
            return;
        }

        if (!is_null($session)) {
            if (self::isSessionExpired($session)) {
                self::deleteUserSessions($session);
                XF::db()->delete('xf_ms_session_data', 1);
                $session = self::initiateSession();
                if (!is_null($session)) {
                    self::updateActivities($session);
                }
            }

            if (self::activitiesTimedOut($session)) {
                self::updateActivities($session);
            }
        } else {
            $session = self::initiateSession();
            if ($session) {
                self::updateActivities($session);
            }
        }
    }

    public static function checkTimeActivity()
    {
        $options = self::$options;
        $currentTime = \XF::$time;
        $dt = new \DateTime('@' . $currentTime);

        $dt->setTimezone(new \DateTimeZone(\XF::language()->getTimeZone()->getName()));
        $hour = (int) $dt->format('H');

        if ($hour >= (int) $options->ms_cb_time_from_to['from'] and $hour <= (int) $options->ms_cb_time_from_to['to']) {
            return true;
        }

        return false;
    }

    public static function deleteUserSessions($session)
    {
        $currentTime = XF::$time;
        foreach (explode(',', $session->session_user_ids) as $user_id) {
            $lastActivity = mt_rand($currentTime, $session->activity_change_after);
            XF::db()->update('xf_user', ['last_activity' => $lastActivity], 'user_id = ?', $user_id);
        }
        if($session->session_user_ids != '') {
            XF::db()->delete('xf_session_activity', 'user_id IN (' . $session->session_user_ids . ') ');
        }
        
    }

    public static function updateActivities($session)
    {
        $userIds = explode(',', $session->session_user_ids);

        $currentTime = XF::$time;
        $startTime = $session->session_start_time;
        $minTimeOut = strtotime('+' . self::$options->ms_cb_x_to_y_minutes['min'] . ' minutes', $startTime);

        if ($minTimeOut < $currentTime && count($userIds) > 2) {
            list($part1, $part2) = array_chunk($userIds, ceil(count($userIds) / 2));
            if ($part2 && count($part2) > 0) {
                foreach ($part2 as $user_id) {
                    $viewDate = mt_rand($minTimeOut, $currentTime);
                    XF::db()->update('xf_session_activity', ['view_date' => $viewDate], 'user_id = ? ', $user_id);
                }
                self::updateSessionUserIds($part1);
                $activityRepo = XF::repository('XF:SessionActivity');
                $activityRepo->updateUserLastActivityFromSession();
            } else {
                $part1 = $userIds;
            }
        } else {
            $part1 = $userIds;
        }



        $threadUser = array_slice($part1, 0, (count($part1) / 2));
        $forumUser = array_slice($part1, (count($part1) / 2));
        self::activityUrl2(0, $forumUser);
        self::activityUrl2(1, $threadUser);
    }

    public static function activitiesTimedOut($session)
    {
        $currentTime = XF::$time;
        if($session->activity_change_after < $currentTime) {
            $activityUpdate = strtotime('+' . self::$options->ms_cb_activity_change . ' minutes', $currentTime);
            $session->activity_change_after = $activityUpdate;
            $session->save();
            return true;
        }
        return false;
    }

    public static function initiateSession()
    {
        $userIds = self::getfakeUserIds();

        if (!is_null($userIds)) {
            return self::addSessionRecord($userIds);
        } else {
            return false;
        }
    }

    public static function addSessionRecord($userIds)
    {
        $currentTime = XF::$time;
        $changeMinutes = (self::$options->ms_cb_activity_change != 0) ? self::$options->ms_cb_activity_change : 10;
        $activityUpdate = strtotime('+' . $changeMinutes . ' minutes', $currentTime);
        $num = mt_rand(self::$options->ms_cb_x_to_y_minutes['min'], self::$options->ms_cb_x_to_y_minutes['max']);
        $endTime = strtotime('+' . $num . ' minutes', $currentTime);

        $sessionData = XF::em()->create("MoazamShakoor\CommunityBooster:SessionData");
        $sessionData->session_start_time = $currentTime;
        $sessionData->session_end_time = $endTime;
        $sessionData->activity_change_after = $activityUpdate;
        $sessionData->session_user_ids = implode(',', $userIds);
        $sessionData->save();

        return $sessionData;
    }

    public static function isSessionExpired($session)
    {
        $currentTime = XF::$time;
        return $session->session_end_time < $currentTime;
    }

    public static function getfakeUserIds()
    {
        $userGroups = self::$options->ms_cb_usergroups;
        $excludedUserGroups = self::$options->ms_cb_usergroups_exclude_online;

        if ($userGroups) {
            $userids = self::getUsersByUserGroup($userGroups, $excludedUserGroups);
            return $userids;
        }
    }

    public static function getUsersByUserGroup($userGroupIds, $excludedUserGroupsIds)
    {
        $num = mt_rand(self::$options->ms_cb_x_to_y_users['min'], self::$options->ms_cb_x_to_y_users['max']);

        $finder = XF::finder('XF:User');
        $parts[] = "user_group_id IN (" . implode(',', $userGroupIds) . ")";

        foreach ($userGroupIds as $part) {
            $parts[] = 'FIND_IN_SET(' . $finder->quote($part) . ', secondary_group_ids)';
        }

        $joiner = ' OR ';

        $finder->whereSql(implode($joiner, $parts));


        if (count($excludedUserGroupsIds) > 0) {
            $parts = [];
            $parts[] = "user_group_id Not IN (" . implode(',', $excludedUserGroupsIds) . ")";

            foreach ($excludedUserGroupsIds as $part) {
                $parts[] = 'NOT FIND_IN_SET(' . $finder->quote($part) . ', secondary_group_ids)';
            }

            $joiner = ' AND ';

            $finder->whereSql(implode($joiner, $parts));
        }

        $res = $finder
            ->limit($num)
            ->pluckFrom('user_id')
            ->fetch()
            ->toArray();

        return $res;
    }

    private static function activityUrl2($number, $userIds, $guest = false, $guestIp = null)
    {
        if ($number) {
            if (!self::$_thread) {
                self::$_thread = XF::finder('XF:Thread')->order('RAND()')->limit(20)->pluckFrom('node_id')->fetch()->toArray();
            }
            $threadId = self::$_thread[array_rand(self::$_thread)];

            self::setSessionActivity2($userIds, "XF\Pub\Controller\Thread", ['thread_id' => $threadId], $guest, $guestIp);
        } else {
            if (!self::$_forum) {
                self::$_forum = XF::finder('XF:Forum')->order('RAND()')->limit(10)->pluckFrom('node_id')->fetch()->toArray();
            }
            $nodeId = self::$_forum[array_rand(self::$_forum)];

            self::setSessionActivity2($userIds, "XF\Pub\Controller\Forum", ['node_id' => $nodeId], $guest, $guestIp);
        }
    }

    private static function setSessionActivity2($userIds, $controller = "XF\Pub\Controller\Thread", $params, $guest = false, $guestIp = null)
    {
        if ($userIds) {
            $ip = ($guest) ? $guestIp : XF::app()->request()->getIp();
            $activityRepo = XF::repository('XF:SessionActivity');

            foreach ($userIds as $id) {
                $activityRepo->updateSessionActivity(
                    $id,
                    $ip,
                    $controller,
                    "Index",
                    $params,
                    "valid",
                    XF::app()->request()->getRobotName()
                );
            }
            $activityRepo->updateUserLastActivityFromSession();
        }
    }

    protected static function getSessionDataRepo()
    {
        return XF::repository('MoazamShakoor\CommunityBooster:SessionData');
    }

    public static function updateSessionUserIds($userIds)
    {
        $sessionDataRepo = self::getSessionDataRepo();
        $session = $sessionDataRepo->getActiveSession();
        $session->session_user_ids = implode(',', $userIds);
        $session->save();
    }
}
