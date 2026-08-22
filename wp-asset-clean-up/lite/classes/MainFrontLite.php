<?php
namespace WpAssetCleanUpLite;

use WpAssetCleanUp\Main;
use WpAssetCleanUp\Misc;

class MainFrontLite
{
    /**
     * @param bool $shouldStop
     *
     * @return bool
     */
    public function shouldStopSetVarsAfterUpdate($shouldStop)
    {
        if ( ! Main::instance()->isUpdateable ) {
            return true;
        }

        return $shouldStop;
    }

    /**
     * @param bool   $useGlobalUnloadOnly
     * @param string $assetType
     * @param array  $globalUnload
     *
     * @return bool
     */
    public function useGlobalUnloadOnly($useGlobalUnloadOnly, $assetType, $globalUnload)
    {
        $nonAssetConfigPage = ! Main::instance()->isUpdateable && ! Misc::getShowOnFront();

        if ($nonAssetConfigPage && ! empty($globalUnload[$assetType])) {
            return true;
        }

        return $useGlobalUnloadOnly;
    }
}
