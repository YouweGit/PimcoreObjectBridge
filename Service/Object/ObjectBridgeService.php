<?php

namespace ObjectBridgeBundle\Service\Object;

use Pimcore\Model\DataObject\AbstractObject;
use Pimcore\Model\DataObject\Concrete;
use Pimcore\Model\Object;

class ObjectBridgeService
{
    /**
     *
     * @param AbstractObject|Concrete $object
     * @param string $key
     * @return mixed
     * Don't add here any default value because it will display wrong info to the user,
     * add default at run time in setters like set from resource,
     * in edit view add it in javascript not php because you will always set the value,
    */

    public static function getValueForObject($object, $key)
    {
        $getter = 'get' . ucfirst($key);
        $value = $object->$getter();

        return $value;
    }

    public static function getValueForObjectToString($object, $key)
    {
        return (string)self::getValueForObject($object, $key);
    }
}
