<?php

namespace ObjectBridgeBundle\EventListener;

use ObjectBridgeBundle\Model\DataObject\ClassDefinition\Data\ObjectBridge;
use Pimcore\Event\Model\DataObjectEvent;
use Pimcore\Model\DataObject;
use Pimcore\Model\Element;

/**
 * Copy event listener
 */
class CopyEventListener
{
    /**
     * @var \Pimcore\Model\User
     */
    protected $user;

    /**
     * @var DataObject\Service
     */
    protected $dataObjectService;

    public function __construct()
    {
        $this->user = \Pimcore\Tool\Admin::getCurrentUser();
        $this->dataObjectService = new DataObject\Service($this->user);
    }

    /**
     * @param DataObjectEvent $dataObjectEvent
     * @return void
     * @throws \Exception
     */
    public function postCopy(DataObjectEvent $dataObjectEvent): void
    {
        $newDataObject = $dataObjectEvent->getObject();
        if (!$newDataObject instanceof DataObject\Concrete) {
            return;
        }

        foreach ($newDataObject->getClass()->getFieldDefinitions(['suppressEnrichment' => true]) as $fieldDefinition) {
            if (!$fieldDefinition instanceof ObjectBridge) {
                continue;
            }

            $this->copyObjectBridgeDataObjects($newDataObject, $fieldDefinition);
        }
    }

    /**
     * @param DataObject\Concrete $dataObject
     * @param ObjectBridge $objectBridge
     * @return void
     * @throws \Exception
     */
    protected function copyObjectBridgeDataObjects(DataObject\Concrete $dataObject, ObjectBridge $objectBridge): void
    {
        $sourceDataObjectGetter = 'get' . ucfirst($objectBridge->getBridgeField());
        $bridgeDataObjectsGetter = 'get' . ucfirst($objectBridge->getName());
        $bridgeDataObjectsSetter = 'set' . ucfirst($objectBridge->getName());
        unset($objectBridge);

        if (!method_exists($dataObject, $bridgeDataObjectsGetter) || !method_exists($dataObject, $bridgeDataObjectsSetter)) {
            return;
        }

        // Retrieve current bridge data objects
        $referencedBridgeDataObjects = $dataObject->$bridgeDataObjectsGetter();

        // Update bridge data objects
        $dataObject->$bridgeDataObjectsSetter(
            $this->copyBridgeDataObjects(
                $dataObject,
                $sourceDataObjectGetter,
                ...$referencedBridgeDataObjects
            )
        );
        $dataObject->save();
    }

    /**
     * @param DataObject\Concrete $dataObject
     * @param string $sourceDataObjectGetter
     * @param DataObject\Concrete ...$bridgeDataObjects
     * @return DataObject\Concrete[]
     * @throws \Exception
     */
    protected function copyBridgeDataObjects(
        DataObject\Concrete $dataObject,
        string $sourceDataObjectGetter,
        DataObject\Concrete ...$bridgeDataObjects
    ): array {
        $copiedBridgeDataObjects = [];
        foreach ($bridgeDataObjects as $bridgeDataObject) {
            if (!$bridgeDataObject instanceof DataObject\Concrete) {
                continue;
            }

            $sourceDataObject = $bridgeDataObject->$sourceDataObjectGetter();
            if (!$sourceDataObject instanceof DataObject\Concrete) {
                continue;
            }

            // Set key on original object to assure the copied object does not cause errors because of duplicate key
            $bridgeDataObject->setKey($dataObject->getId() . '_' . $sourceDataObject->getId());

            // Copy bridge data object and add it to the array
            $copiedBridgeDataObjects[] = $this->copyAsChild($bridgeDataObject->getParent(), $bridgeDataObject);
        }

        return $copiedBridgeDataObjects;
    }

    /**
     * Customized copy method without updateChilds, which causes an out of
     * memory fatal error when copying a large number of bridge data objects
     * @see DataObject\Service::copyAsChild()
     *
     * @param DataObject\AbstractObject $target Folder to copy data object to
     * @param DataObject\Concrete $source Data object to copy
     * @return DataObject\Concrete
     * @throws \Exception
     */
    protected function copyAsChild(DataObject\AbstractObject $target, DataObject\Concrete $source): DataObject\Concrete
    {
        /* @var $new DataObject\Concrete */
        $new = Element\Service::cloneMe($source);
        $new->setId(null);

        $new->setChildren(null);
        $new->setKey(Element\Service::getSaveCopyName('object', $new->getKey(), $target));
        $new->setParentId($target->getId());
        $new->setUserOwner($this->user->getId());
        $new->setUserModification($this->user->getId());
        $new->setDao(null);
        $new->setLocked(false);
        $new->setCreationDate(time());
        $new->save();

        return $new;
    }
}
