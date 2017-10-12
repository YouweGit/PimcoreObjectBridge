<?php

namespace ObjectBridge\Object;

use Pimcore\Model\Object;

class Service
{
    /**
     * @param Object\AbstractObject|Object\Concrete $object
     * @param string $key
     * @param bool $fallbackDefaultValue
     * @return mixed
     */
    public static function getValueForObject($object, $key, $fallbackDefaultValue = false)
    {
        $getter = 'get' . ucfirst($key);
        $value = $object->$getter();


        if (strlen((string)$value) === 0  && $fallbackDefaultValue) {
            $fd = $object->getClass()->getFieldDefinition($key);

            if (method_exists($fd, 'getDefaultValue')) {
                $value = $fd->getDefaultValue();
            }
        }

        return $value;
    }

    public static function getValueForObjectToString($object, $key)
    {
        return (string)self::getValueForObject($object, $key);
    }
}
