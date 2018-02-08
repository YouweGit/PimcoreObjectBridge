<?php

namespace ObjectBridgeBundle;

use Pimcore\Extension\Bundle\AbstractPimcoreBundle;

class ObjectBridgeBundle extends AbstractPimcoreBundle
{
    public function getJsPaths() {
        return [
            '/bundles/objectbridge/js/pimcore/objects/tags/objectBridge.js',
            '/bundles/objectbridge/js/pimcore/objects/classes/data/objectBridge.js'
        ];
    }

    public function getCssPaths() {
        return [
            '/bundles/objectbridge/css/object-bridge.css'
        ];
    }
}
