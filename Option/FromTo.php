<?php

namespace MoazamShakoor\CommunityBooster\Option;

use XF\Option\AbstractOption;

class FromTo extends AbstractOption {

    public static function verifyOption(&$value, \XF\Entity\Option $option) {
        if ($value['from'] > $value['to']) {
            $option->error(\XF::phrase('from_time_should_be_less_than_to_time'), $option->option_id);
            return FALSE;
        }

        return TRUE;
    }

}
