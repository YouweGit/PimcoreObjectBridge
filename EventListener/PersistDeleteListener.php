<?php
/**
 * Class PersistDelete
 *
 * This class is suppose to persist deletion of the bridge object
 * when one of the host object or source object has been deleted
 * something like persist delete in doctrine
 *
 * @package ObjectBridge
 */

namespace ObjectBridgeBundle\EventListener;


use ObjectBridgeBundle\Model\Object\ClassDefinition\Data\ObjectBridge;
use Pimcore\Event\Model\ElementEventInterface;
use Pimcore\Model\DataObject\ClassDefinition;
use Pimcore\Model\DataObject\Concrete;
use Pimcore\Model\Dependency;

class PersistDeleteListener
{
    public function objectPreDelete(ElementEventInterface $event)
    {
        /** @var Concrete $target */
        $target = $event->getObject();
        if (!$target instanceof Concrete) {
            return;
        }

        // Check if target is the host object of object bridge if so then delete the objects
        $this->deleteSourceObjectsIfHasField($target);

        // Deleting bridge objects if target is the source
        $this->deleteObjectSourceBridgeDependencies($target);
    }

    /**
     * @param Concrete $target
     * @param ObjectBridge $fieldDefinition
     */
    private function deleteBridgeDependencies($target, $fieldDefinition)
    {
        $dependencies = Dependency::getBySourceId($target->getId(), "object");
        foreach ($dependencies->getRequires() as $dependency) {
            $this->deleteBridgeIfObject($fieldDefinition, $dependency);
        }
    }

    /**
     * @param Concrete $target
     */
    private function deleteSourceObjectsIfHasField($target)
    {
        $classDef = ClassDefinition::getById($target->getClassId());
        foreach ($classDef->getFieldDefinitions() as $fieldDefinition) {
            if ($fieldDefinition instanceof ObjectBridge) {
                $this->deleteBridgeDependencies($target, $fieldDefinition);
            }
        }
    }

    /**
     * @param ObjectBridge $fieldDefinition
     * @param Concrete $target
     */
    private function deleteDependenciesForSource($fieldDefinition, $target)
    {
        $fullClassName = '\\Pimcore\\Model\\DataObject\\' . $fieldDefinition->sourceAllowedClassName;
        if ($target instanceof $fullClassName) {
            $dependencies = Dependency::getBySourceId($target->getId(), "object");
            foreach ($dependencies->getRequiredBy() as $dependency) {
                $this->deleteBridgeIfObject($fieldDefinition, $dependency);
            }
        }
    }

    /**
     * @param Concrete $target
     */
    private function deleteObjectSourceBridgeDependencies($target)
    {
        $classDefinitions = new ClassDefinition\Listing();
        /** @var ClassDefinition $classDef */
        foreach ($classDefinitions->load() as $classDef) {
            foreach ($classDef->getFieldDefinitions() as $fieldDefinition) {
                if ($fieldDefinition instanceof ObjectBridge) {
                    $this->deleteDependenciesForSource($fieldDefinition, $target);
                }
            }
        }
    }

    /**
     * @param ObjectBridge $fieldDefinition
     * @param Dependency $dependency
     */
    private function deleteBridgeIfObject($fieldDefinition, $dependency)
    {
        if ($dependency['type'] === 'object') {
            $dependentObject = Concrete::getById($dependency['id']);
            $fullClassName = '\\Pimcore\\Model\\DataObject\\' . $fieldDefinition->bridgeAllowedClassName;
            if ($dependentObject && $dependentObject instanceof $fullClassName) {
                $dependentObject->delete();
            }
        }
    }
}