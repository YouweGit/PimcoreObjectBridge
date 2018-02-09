<?php

namespace ObjectBridgeBundle;

use Pimcore\Extension\Bundle\AbstractPimcoreBundle;
use Pimcore\Extension\Bundle\Traits\PackageVersionTrait;

class ObjectBridgeBundle extends AbstractPimcoreBundle
{
    use PackageVersionTrait;
    
    protected function getComposerPackageName(): string
    {
        // getVersion() will use this name to read the version from
        // PackageVersions and return a normalized value
        return 'youwe/pimcore-object-bridge';
    }
    
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
