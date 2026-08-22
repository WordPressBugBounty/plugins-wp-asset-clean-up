<?php
namespace WpAssetCleanUpLite;

use WpAssetCleanUp\Main;
use WpAssetCleanUp\MainFront;

/**
 * Class MainLite
 *
 * Lite-only logic that used to live in Main.php between [wpacu_lite] markers.
 * Main.php is now shared by Lite and Pro; this class should be loaded only by Lite.
 *
 * @package WpAssetCleanUpLite
 */
class MainLite
{
    /**
     * @var MainLite|null
     */
    private static $singleton;

    /**
     * @var bool
     */
    public $isUpdateable = true;

    /**
     * @return MainLite|null
     */
    public static function instance()
    {
        if ( self::$singleton === null ) {
            self::$singleton = new self();
        }

        return self::$singleton;
    }

    /**
     * Undetectable? The page is not a singular one nor the home page.
     * It's likely an archive, category page (WooCommerce), 404 page manageable in the Pro version etc.
     *
     * @param int $currentPostId
     * @param Main $main
     *
     * @return void
     */
    public function setUpdateableStatus($currentPostId, Main $main)
    {
        $this->isUpdateable = true;
        $main->isUpdateable = true;

        if ( ! $currentPostId && ! MainFront::isHomePage() ) {
            $this->isUpdateable = false;
            $main->isUpdateable = false;
        }
    }
}
