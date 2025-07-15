<?php

namespace MoazamShakoor\CommunityBooster\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property string addon_id
 * @property string title
 * @property string version_string
 * @property int version_id
 * @property string json_hash
 * @property bool active
 * @property bool is_legacy
 * @property bool is_processing
 * @property string|null last_pending_action
 * 
 */
class SessionData extends Entity
{

	public static function getStructure(Structure $structure)
	{
		$structure->table = 'xf_ms_session_data';
		$structure->shortName = 'MoazamShakoor\CommunityBooster:SessionData';
		$structure->primaryKey = 'session_id';
		$structure->columns = [
			'session_id' => ['type' => self::UINT, 'autoIncrement' => true],
			'session_start_time' => ['type' => self::UINT, 'default' => 0],
			'session_end_time' => ['type' => self::UINT, 'default' => 0],
			'activity_change_after' => ['type' => self::UINT, 'default' => 0],
			'session_user_ids' => ['type' => self::STR, 'default' => ''],
		];
        
		$structure->relations = [];

		return $structure;
	}

}