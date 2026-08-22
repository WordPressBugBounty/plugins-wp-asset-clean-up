<?php
namespace WpAssetCleanUp;

/**
 *
 */
class DebugException extends \Exception
{
    /**
     * @var string
     */
    protected $debugHtml;

    /**
     * @param $data
     */
    public function __construct($data)
    {
        $this->debugHtml = '<pre style="background:#fff;color:#000;padding:20px;white-space:pre-wrap;z-index:999999;position:relative;">'
                           . htmlspecialchars(print_r($data, true), ENT_QUOTES, 'UTF-8')
                           . '</pre>';

        parent::__construct('Asset CleanUp: Debug output');
    }

    /**
     * @return string
     */
    public function getDebugHtml()
    {
        return $this->debugHtml;
    }
}
