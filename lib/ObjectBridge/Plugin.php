<?php

namespace ObjectBridge;

use Pimcore\API\Plugin as PluginLib;

class Plugin extends PluginLib\AbstractPlugin implements PluginLib\PluginInterface
{

    public function init()
    {
        parent::init();
        $persistDelete = new PersistDelete();
        \Pimcore::getEventManager()->attach("object.preDelete", [$persistDelete, 'handleObjectPreDelete']);
    }

    public static function install()
    {
        return true;
    }

    public static function uninstall()
    {
        return false;
    }

    public static function isInstalled()
    {
        return true;
    }

    public static function getTranslationFile($language)
    {
        return '/PimcoreObjectBridge/config/texts/en.csv';
    }
}
