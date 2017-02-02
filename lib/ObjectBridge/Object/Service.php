<?php
namespace ObjectBridge\Object;

use Pimcore\Model\Object;
use Pimcore\Model\Object\Service as PimcoreObjectService;

class Service {
    /**
     * @param Object\AbstractObject|Object\Concrete $object
     * @param string $key
     * @return mixed
     */
    public static function getValueForObject($object, $key) {
        $getter = 'get' . ucfirst($key);
        $value = $object->$getter();
//        if (null === $value) {
//            $parent = PimcoreObjectService::hasInheritableParentObject($object);
//            if (null !== $parent) {
//                return self::getValueForObject($parent, $key);
//            }
//        }
        return $value;
    }

    public static function getValueForObjectToString($object, $key) {
        return (string)self::getValueForObject($object, $key);
    }
}
