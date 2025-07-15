<?php

namespace MoazamShakoor\CommunityBooster;

use XF\AddOn\AbstractSetup;
use XF\AddOn\StepRunnerInstallTrait;
use XF\AddOn\StepRunnerUninstallTrait;
use XF\AddOn\StepRunnerUpgradeTrait;

class Setup extends AbstractSetup
{
	use StepRunnerInstallTrait;
	use StepRunnerUpgradeTrait;
	use StepRunnerUninstallTrait;

	public function installStep1(){

        $sm = $this->schemaManager();
        $sm->createTable('xf_ms_session_data', function(\XF\Db\Schema\Create $table)
        {
            $table->addColumn('session_id','int')->autoIncrement();
            $table->addColumn('session_start_time', 'int');
            $table->addColumn('session_end_time', 'int');
			$table->addColumn('activity_change_after', 'int');
			$table->addColumn('session_user_ids', 'longtext');
            $table->addPrimaryKey('session_id');
        });
    }

	public function uninstallStep1(){

        $sm = $this->schemaManager();
        $sm->dropTable('xf_ms_session_data');
    }

}