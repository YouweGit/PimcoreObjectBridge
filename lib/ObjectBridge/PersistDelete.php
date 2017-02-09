<?php

namespace ObjectBridge;

/**
 * Class PersistDelete
 *
 * This class is suppose to persist deletion of the bridge object
 * when one of the host object or source object has been deleted
 * something like persist delete in doctrine
 *
 * @package ObjectBridge
 */
use Pimcore\Model;
use Pimcore\Model\Object;
use Zend_EventManager_Event;

class PersistDelete
{

    public function handleObjectPreDelete(Zend_EventManager_Event $event)
    {

        /** @var Object\Concrete $target */
        $target = $event->getTarget();
        if (!$target instanceof Object\Concrete) {
            return;
        }
        // Check if target is the host object of object bridge if so then delete the objects
        $this->deleteSourceObjectsIfHasField($target);

        // Deleting bridge objects if target is the source
        $this->deleteObjectSourceBridgeDependencies($target);
    }

    /**
     * @param Object\Concrete $target
     * @param Object\ClassDefinition\Data\ObjectBridge $fieldDefinition
     */
    private function deleteBridgeDependencies($target, $fieldDefinition)
    {
        $dependencies = Model\Dependency::getBySourceId($target->getId(), "object");
        foreach ($dependencies->getRequires() as $dependency) {
            $this->deleteBridgeIfObject($fieldDefinition, $dependency);
        }
    }

    /**
     * @param Object\Concrete $target
     */
    private function deleteSourceObjectsIfHasField($target)
    {
        $classDef = Object\ClassDefinition::getById($target->getClassId());
        foreach ($classDef->getFieldDefinitions() as $fieldDefinition) {
            if ($fieldDefinition instanceof Object\ClassDefinition\Data\ObjectBridge) {
                $this->deleteBridgeDependencies($target, $fieldDefinition);
            }
        }
    }

    /**
     * @param Object\ClassDefinition\Data\ObjectBridge $fieldDefinition
     * @param Object\Concrete $target
     */
    private function deleteDependenciesForSource($fieldDefinition, $target)
    {
        $fullClassName = '\\Pimcore\\Model\\Object\\' . $fieldDefinition->sourceAllowedClassName;
        if ($target instanceof $fullClassName) {
            $dependencies = Model\Dependency::getBySourceId($target->getId(), "object");
            foreach ($dependencies->getRequiredBy() as $dependency) {
                $this->deleteBridgeIfObject($fieldDefinition, $dependency);
            }
        }
    }

    /**
     * @param Object\Concrete $target
     */
    private function deleteObjectSourceBridgeDependencies($target)
    {
        $classDefinitions = new  Object\ClassDefinition\Listing();
        /** @var Object\ClassDefinition $classDef */
        foreach ($classDefinitions->load() as $classDef) {
            foreach ($classDef->getFieldDefinitions() as $fieldDefinition) {
                if ($fieldDefinition instanceof Object\ClassDefinition\Data\ObjectBridge) {
                    $this->deleteDependenciesForSource($fieldDefinition, $target);
                }
            }
        }
    }

    /**
     * @param Object\ClassDefinition\Data\ObjectBridge $fieldDefinition
     * @param Model\Dependency $dependency
     */
    private function deleteBridgeIfObject($fieldDefinition, $dependency)
    {
        if ($dependency['type'] === 'object') {
            $dependentObject = Object\Concrete::getById($dependency['id']);
            $fullClassName = '\\Pimcore\\Model\\Object\\' . $fieldDefinition->bridgeAllowedClassName;
            if ($dependentObject && $dependentObject instanceof $fullClassName) {
                $dependentObject->delete();
            }
        }
    }
}