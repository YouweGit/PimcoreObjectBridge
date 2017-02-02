<?php

namespace Pimcore\Model\Object\ClassDefinition\Data;

use ObjectBridge\Object\Service;
use PDO;
use Pimcore\Logger;
use Pimcore\Model;
use Pimcore\Model\Object;
use Pimcore\Model\Element;
use Pimcore\Model\Object\AbstractObject;
use Pimcore\Model\Object\ClassDefinition;
use Pimcore\Db;
use Pimcore\Model\Object\Concrete;

/** @noinspection ClassOverridesFieldOfSuperClassInspection
 * We need to overwrite public properties because pimcore uses them for storing data
 */
class ObjectBridge extends Model\Object\ClassDefinition\Data\ObjectsMetadata {

    /**
     * @var string
     */
    public $sourceAllowedClassName;

    /**
     * @var string
     */
    public $bridgeAllowedClassName;

    /**
     * @var string
     */
    public $sourceVisibleFields;
    /**
     * @var string
     */
    public $bridgeVisibleFields;

    /** @var string */
    public $bridgeField;

    /** @var string */
    public $bridgeFolder;

    /** @var string */
    public $sourceHiddenFields;

    /** @var string */
    public $bridgeHiddenFields;

    /**
     * Static type of this element
     *
     * @var string
     */
    public $fieldtype = 'objectBridge';

    /**
     * Type for the generated phpdoc
     *
     * @var string
     */

    public $phpdocType = "\\Pimcore\\Model\\Object\\AbstractObject[]";

    /** @var string */
    public $sourceVisibleFieldDefinitions;

    /** @var string */
    public $bridgeVisibleFieldDefinitions;
    /** @var  bool */
    public $autoResize;

    /** @var  bool */
    public $newLineSplit;

    /** @var int */
    public $maxWidthResize;

    /**
     * Converts sql data to object
     * @see Object\ClassDefinition\Data::getDataFromResource
     * @param array $data
     * @param null|Model\Object\AbstractObject $object
     * @param mixed $params
     * @return array
     */
    public function getDataFromResource($data, $object = null, $params = []) {
        $objects = [];
        if (is_array($data) && count($data) > 0) {
            foreach ($data as $objectData) {
                $o = AbstractObject::getById($objectData['dest_id']);
                if ($o instanceof Object\Concrete) {
                    $objects[] = $o;
                }
            }
        }

        //must return array - otherwise this means data is not loaded
        return $objects;
    }

    /**
     * @see Object\ClassDefinition\Data::getDataForResource
     * @param array $data
     * @param null|Model\Object\AbstractObject $object
     * @param mixed $params
     * @return array
     */
    public function getDataForResource($data, $object = null, $params = []) {
        $return = [];

        if (is_array($data) && count($data) > 0) {
            $counter = 1;
            foreach ($data as $objectData) {
                if ($objectData instanceof Object\Concrete) {
                    $return[] = [
                        'dest_id'   => $objectData->getId(),
                        'type'      => 'object',
                        'fieldname' => $this->getName(),
                        'index'     => $counter,
                    ];
                }
                $counter++;
            }

            return $return;
        } elseif (is_array($data) && count($data) === 0) {
            //give empty array if data was not null
            return [];
        } else {
            //return null if data was null - this indicates data was not loaded
            return null;
        }
    }


    /**
     * Returns what it should be added in query table for this field
     * @param array $data
     * @param null $object
     * @param mixed $params
     * @return null|string
     * @throws \Exception
     */
    public function getDataForQueryResource($data, $object = null, $params = []) {

        //return null when data is not set
        if (!$data) {
            return null;
        }

        $ids = [];

        if (is_array($data) && count($data) > 0) {
            foreach ($data as $objectData) {
                if ($objectData instanceof Object\Concrete) {
                    $ids[] = $objectData->getId();
                }
            }

            return ',' . implode(',', $ids) . ',';
        } elseif (is_array($data) && count($data) === 0) {
            return '';
        } else {
            throw new \BadFunctionCallException('invalid data passed to getDataForQueryResource - must be array and it is: ' . print_r($data, true));
        }
    }

    /**
     * @see Object\ClassDefinition\Data::getDataForEditmode
     * @param array $data
     * @param null|Model\Object\AbstractObject $object
     * @param mixed $params
     * @return array
     * @throws \Exception
     */
    public function getDataForEditmode($data, $object = null, $params = []) {
        $return = [];
        if (is_array($data) && count($data) > 0) {

            $sourceClassDef = $this->getSourceClassDefinition();
            $bridgeClassDef = $this->getBridgeClassDefinition();

            $bridgeVisibleFieldsArray = $this->removeRestrictedKeys($this->getBridgeVisibleFieldsAsArray());

            foreach ($data as $bridgeObject) {

                if ($bridgeObject instanceof Object\Concrete) {

                    $bridgeIdKey = ucfirst($bridgeClassDef->getName()) . '_id';
                    $columnData = [];
                    $columnData[$bridgeIdKey] = $bridgeObject->getId();

                    foreach ($bridgeVisibleFieldsArray as $bridgeVisibleField) {
                        $fd = $bridgeClassDef->getFieldDefinition($bridgeVisibleField);
                        $key = ucfirst($bridgeClassDef->getName()) . '_' . $bridgeVisibleField;
                        if ($fd instanceof Object\ClassDefinition\Data\Href) {
                            $valueObject = Service::getValueForObject($bridgeObject, $bridgeVisibleField);
                            $columnData[$key] = $valueObject ? $valueObject->getId() : null;
                        } else {
                            $columnData[$key] = Service::getValueForObject($bridgeObject, $bridgeVisibleField);
                        }
                    }

                    $bridgeFieldGetter = 'get' . $this->getBridgeField();
                    $sourceObject = $bridgeObject->$bridgeFieldGetter();

                    if (!$sourceObject instanceof Object\Concrete) {
                        throw new \RuntimeException(sprintf('Database has an inconsistency, please remove object with id %s to fix the error', $bridgeObject->getId()));
                    }

                    $sourceIdKey = ucfirst($sourceClassDef->getName()) . '_id';
                    $columnData[$sourceIdKey] = $sourceObject->getId();
                    $sourceVisibleFieldsArray = $this->removeRestrictedKeys($this->getSourceVisibleFieldsAsArray());

                    foreach ($sourceVisibleFieldsArray as $sourceVisibleField) {
                        $fd = $sourceClassDef->getFieldDefinition($sourceVisibleField);
                        $key = ucfirst($sourceClassDef->getName()) . '_' . $sourceVisibleField;

                        if ($fd instanceof Object\ClassDefinition\Data\Href) {
                            $valueObject = Service::getValueForObjectToString($sourceObject, $sourceVisibleField);
                            $columnData[$key] = $valueObject;
                        } else {
                            $columnData[$key] = Service::getValueForObject($sourceObject, $sourceVisibleField);
                        }
                    }
                    $return[] = $columnData;
                }
            }
        }
        return $return;
    }

    /**
     * @see Model\Object\ClassDefinition\Data::getDataFromEditmode
     * @param array $data
     * @param null|Concrete $object
     * @param mixed $params
     * @return array
     * @throws \Zend_Db_Statement_Exception
     * @throws \Exception
     * @throws \InvalidArgumentException
     * @throws \DI\NotFoundException
     * @throws \DI\DependencyException
     */
    public function getDataFromEditmode($data, $object = null, $params = []) {

        //if not set, return null
        if ($data === null || $data === false) {
            return null;
        }

        $objectsBridge = [];
        if (is_array($data) && count($data) > 0) {
            $sourceClassDef = $this->getSourceClassDefinition();
            $bridgeClassDef = $this->getBridgeClassDefinition();
            /** @var AbstractObject|string $sourceClass */
            $sourceClass = $this->getSourceFullClassName();
            /** @var AbstractObject|string $bridgeClass */
            $bridgeClass = $this->getBridgeFullClassName();
            $idSourceFieldKey = $sourceClassDef->getName() . '_id';

            foreach ($data as $objectData) {
                $sourceId = $objectData[$idSourceFieldKey];
                $bridgeObjectId = $this->getBridgeIdBySourceAndOwner($object, $bridgeClass, $sourceId);


                $sourceObject = $sourceClass::getById($sourceId);
                /** @var Object\Concrete $bridgeObject */
                if (!$bridgeObjectId) {
                    $bridgeObject = new $bridgeClass;
                    $parent = Object\Service::createFolderByPath($this->bridgeFolder);
                    if (!$parent instanceof AbstractObject) {
                        throw new \InvalidArgumentException(sprintf('Parent not found at "%s" please check your object bridge configuration "Bridge folder"', $this->bridgeFolder));
                    }
                    $bridgeObject->setParent($parent);
                    $bridgeObject->setKey($object->getId() . '_' . $sourceObject->getId());
                    $bridgeObject->setPublished(false);
                    // Make sure its unique else saving will throw an error
                    $bridgeObject->setKey(Object\Service::getUniqueKey($bridgeObject));
                } else {
                    $bridgeObject = $bridgeClass::getById($bridgeObjectId);
                }

                // Store data to bridge object
                $bridgeVisibleFieldsArray = $this->getBridgeVisibleFieldsAsArray();
                // Id should be never be edited
                $bridgeVisibleFieldsArray = $this->removeRestrictedKeys($bridgeVisibleFieldsArray);

                foreach ($bridgeVisibleFieldsArray as $bridgeVisibleField) {
                    $fd = $bridgeClassDef->getFieldDefinition($bridgeVisibleField);

                    $key = ucfirst($bridgeClassDef->getName()) . '_' . $bridgeVisibleField;
                    if (array_key_exists($key, $objectData)) {
                        $setter = 'set' . ucfirst($bridgeVisibleField);

                        if ($fd instanceof Object\ClassDefinition\Data\Href) {
                            $valueObject = $this->getObjectForHref($fd, $objectData[$key]);
                            $bridgeObject->$setter($valueObject);
                        } else {
                            $bridgeObject->$setter($objectData[$key]);
                        }

                    }
                }

                $bridgeFieldSetter = 'set' . ucfirst($this->bridgeField);
                $bridgeObject->$bridgeFieldSetter($sourceObject);
                $bridgeObject->setOmitMandatoryCheck(true);
                // because the main object might not be published yet - do not publish the bridge
                $bridgeObject->setPublished(false);
                $bridgeObject->save();

                if ($bridgeObject && $bridgeObject->getClassName() === $this->getBridgeAllowedClassName()) {
                    $objectsBridge[] = $bridgeObject;
                }
            }
        }

        //must return array if data shall be set
        return $objectsBridge;
    }

    /**
     * @return mixed|null|ClassDefinition
     * @throws \Exception
     */
    private function getSourceClassDefinition() {
        return ClassDefinition::getByName($this->sourceAllowedClassName);
    }

    /**
     * @return mixed|null|ClassDefinition
     */
    private function getBridgeClassDefinition() {
        return ClassDefinition::getByName($this->bridgeAllowedClassName);
    }

    /**
     * @param array $visibleFieldsArray
     * @return array
     */
    private function removeRestrictedKeys($visibleFieldsArray) {
        // Id should be never edited and it will always be added by default
        if (($key = array_search('id', $visibleFieldsArray, true)) !== false) {
            unset($visibleFieldsArray[$key]);
        }
        // Bridge object should be handled separately
        if (($key = array_search($this->bridgeField, $visibleFieldsArray, true)) !== false) {
            unset($visibleFieldsArray[$key]);
        }

        return $visibleFieldsArray;
    }

    /**
     * @return array
     */
    public function getBridgeVisibleFieldsAsArray() {
        return explode(',', $this->bridgeVisibleFields);
    }

    /**
     * @return string
     */
    public function getBridgeField() {
        return $this->bridgeField;
    }

    /**
     * @param string $bridgeField
     * @return $this
     */
    public function setBridgeField($bridgeField) {
        $this->bridgeField = $bridgeField;

        return $this;
    }

    /**
     * @return array
     */
    public function getSourceVisibleFieldsAsArray() {
        return explode(',', $this->sourceVisibleFields);
    }


    /**
     * @return string
     */
    private function getSourceFullClassName() {
        return '\\Pimcore\\Model\\Object\\' . ucfirst($this->sourceAllowedClassName);
    }

    /**
     * @return string
     */
    private function getBridgeFullClassName() {
        return '\\Pimcore\\Model\\Object\\' . ucfirst($this->bridgeAllowedClassName);
    }

    /**
     * @param Concrete $object
     * @param Concrete|string $bridgeClass
     * @param int $sourceId
     * @return null|int
     * @throws \Zend_Db_Statement_Exception
     */
    private function getBridgeIdBySourceAndOwner($object, $bridgeClass, $sourceId) {
        $db = Db::get();
        $select = $db->select()
            ->from(['dor' => 'object_relations_' . $object::classId()], [])
            ->joinInner(['dp_objects' => 'object_' . $bridgeClass::classId()], 'dor.dest_id = dp_objects.oo_id', ['oo_id'])
            ->where('dor.src_id = ?', $object->getId())
            ->where('dp_objects.' . $this->bridgeField . '__id = ?', $sourceId);


        $stmt = $db->query($select);

        return $stmt->fetch(PDO::FETCH_COLUMN, 0);
    }

    /**
     * @param ClassDefinition\Data\Href $fd
     * @param string|int $value
     * @return null|AbstractObject
     */
    private function getObjectForHref($fd, $value) {
        $class = current($fd->getClasses());
        if ($class && is_array($class) && array_key_exists('classes', $class)) {
            $class = $class['classes'];
            /** @var AbstractObject $className */
            $className = '\\Pimcore\\Model\\Object\\' . $class;
            return $className::getById($value);
        }

        return null;
    }

    /**
     * @return string
     */
    public function getBridgeAllowedClassName() {
        return $this->bridgeAllowedClassName;
    }

    /**
     * @param string $bridgeAllowedClassName
     * @return ObjectBridge
     */
    public function setBridgeAllowedClassName($bridgeAllowedClassName) {
        $this->bridgeAllowedClassName = $bridgeAllowedClassName;

        return $this;
    }

    /**
     * @param array $data
     * @param null|Concrete $object
     * @param array $params
     * @return array
     */
    public function getDataForGrid($data, $object = null, $params = []) {
        if (is_array($data)) {
            $paths = [];
            foreach ($data as $eo) {
                if ($eo instanceof Element\ElementInterface) {
                    $paths[] = (string)$eo;
                }
            }
            return $paths;
        }
        return null;
    }

    /**
     * @see Object\ClassDefinition\Data::getVersionPreview
     * @param array $data
     * @param null|Object\AbstractObject $object
     * @param mixed $params
     * @return string
     */
    public function getVersionPreview($data, $object = null, $params = []) {
        if (is_array($data) && count($data) > 0) {
            $paths = [];
            foreach ($data as $o) {
                if ($o instanceof Element\ElementInterface) {
                    $paths[] = (string)$o;
                }
            }
            return implode('<br />', $paths);
        }
        return null;
    }

    /**
     * Checks if data is valid for current data field
     *
     * @param mixed $data
     * @param boolean $omitMandatoryCheck
     * @throws \Exception
     */
    public function checkValidity($data, $omitMandatoryCheck = false) {
        if (!$omitMandatoryCheck && $this->getMandatory() && empty($data)) {
            throw new Element\ValidationException('Empty mandatory field [ ' . $this->getName() . ' ]');
        }
        if (is_array($data)) {
            /** @var Object\Concrete $objectBridge */
            foreach ($data as $objectBridge) {
                $bridgeClassFullName = $this->getBridgeFullClassName();
                if (!($objectBridge instanceof $bridgeClassFullName)) {
                    throw new Element\ValidationException('Expected ' . $bridgeClassFullName);
                }
                foreach ($objectBridge->getClass()->getFieldDefinitions() as $fieldDefinition) {
                    $fieldDefinition->checkValidity(Service::getValueForObject($objectBridge, $fieldDefinition->getName()), $omitMandatoryCheck);
                }

                if (!($objectBridge instanceof Object\Concrete) || $objectBridge->getClassName() !== $this->getBridgeAllowedClassName()) {
                    $id = $objectBridge instanceof Object\Concrete ? $objectBridge->getId() : '??';
                    throw new Element\ValidationException('Invalid object relation to object [' . $id . '] in field ' . $this->getName(), null, null);
                }
            }
        }
    }

    /**
     * Default functionality from ClassDefinition\Data\Object::getFromCsvImport
     * Converts object data to a simple string value or CSV Export
     * @abstract
     * @param Object\AbstractObject $object
     * @param array $params
     * @return string
     */
    public function getForCsvExport($object, $params = []) {
        /** @var array $data */
        $data = $this->getDataFromObjectParam($object, $params);
        if (is_array($data)) {
            $paths = [];
            foreach ($data as $eo) {
                if ($eo instanceof Element\ElementInterface) {
                    $paths[] = $eo->getRealFullPath();
                }
            }
            return implode(',', $paths);
        } else {
            return null;
        }
    }

    /**
     * Default functionality from ClassDefinition\Data\Object::getFromCsvImport
     * Will import comma separated paths
     * @param mixed $importValue
     * @param null|Model\Object\AbstractObject $object
     * @param mixed $params
     * @return array|mixed
     */
    public function getFromCsvImport($importValue, $object = null, $params = []) {
        $values = explode(',', $importValue);

        $value = [];
        foreach ($values as $element) {
            if ($el = AbstractObject::getByPath($element)) {
                $value[] = $el;
            }
        }

        return $value;
    }

    /**
     * Default functionality from ClassDefinition\Data\Object::getCacheTags
     * This is a dummy and is mostly implemented by relation types
     *
     * @param array $data
     * @param array $tags
     * @return array
     */
    public function getCacheTags($data, $tags = []) {
        $tags = is_array($tags) ? $tags : [];

        if (is_array($data) && count($data) > 0) {
            foreach ($data as $object) {
                if ($object instanceof Element\ElementInterface && !array_key_exists($object->getCacheTag(), $tags)) {
                    $tags = $object->getCacheTags($tags);
                }
            }
        }

        return $tags;
    }

    /**
     * Default functionality from ClassDefinition\Data\Object::resolveDependencies
     * @param $data
     * @return array
     */
    public function resolveDependencies($data) {
        $dependencies = [];

        if (is_array($data) && count($data) > 0) {
            foreach ($data as $o) {
                if ($o instanceof Object\AbstractObject) {
                    $dependencies['object_' . $o->getId()] = [
                        'id'   => $o->getId(),
                        'type' => 'object',
                    ];
                }
            }
        }

        return $dependencies;
    }

    /**
     * Default functionality from ClassDefinition\Data\Object::getForWebserviceExport
     * @param Object\AbstractObject $object
     * @param mixed $params
     * @return array|mixed|null
     */
    public function getForWebserviceExport($object, $params = []) {
        $data = $this->getDataFromObjectParam($object, $params);
        if (is_array($data)) {
            $items = [];
            foreach ($data as $eo) {
                if ($eo instanceof Element\ElementInterface) {
                    $items[] = [
                        'type' => $eo->getType(),
                        'id'   => $eo->getId(),
                    ];
                }
            }

            return $items;
        } else {
            return null;
        }
    }

    /** @noinspection MoreThanThreeArgumentsInspection
     * Method has to stay compatible with pimcore
     */

    /**
     * Default functionality from ClassDefinition\Data\Object::getFromWebserviceImport
     * @param array $value
     * @param null $object
     * @param mixed $params
     * @param null $idMapper
     * @return array|mixed
     * @throws \Exception
     */
    public function getFromWebserviceImport($value, $object = null, $params = [], $idMapper = null) {
        $relatedObjects = [];
        if (!$value) {
            return null;
        } elseif (is_array($value)) {
            foreach ($value as $key => $item) {
                $item = (array)$item;
                $id = $item['id'];

                if ($idMapper) {
                    $id = $idMapper->getMappedId('object', $id);
                }

                $relatedObject = null;
                if ($id) {
                    $relatedObject = AbstractObject::getById($id);
                }

                if ($relatedObject instanceof Object\AbstractObject) {
                    $relatedObjects[] = $relatedObject;
                } else {
                    if (!$idMapper || !$idMapper->ignoreMappingFailures()) {
                        throw new \InvalidArgumentException('Cannot get values from web service import - references unknown object with id [ ' . $item['id'] . ' ]');
                    } else {
                        $idMapper->recordMappingFailure('object', $object->getId(), 'object', $item['id']);
                    }
                }
            }
        } else {
            throw new \InvalidArgumentException('Cannot get values from web service import - invalid data');
        }

        return $relatedObjects;
    }

    /**
     * Default functionality from ClassDefinition\Data\Object::save
     * @param AbstractObject $object
     * @param array $params
     * @throws \Exception
     */
    public function save($object, $params = []) {
        $db = Db::get();
        $data = $this->getDataFromObjectParam($object, $params);
        $relations = $this->getDataForResource($data, $object, $params);

        if (is_array($relations) && !empty($relations)) {
            foreach ($relations as $relation) {
                $this->enrichRelation($object, $params, $classId, $relation);


                /*relation needs to be an array with src_id, dest_id, type, fieldname*/
                try {
                    $db->insert('object_relations_' . $classId, $relation);
                } catch (\Exception $e) {
                    Logger::error('It seems that the relation ' . $relation['src_id'] . ' => ' . $relation['dest_id']
                        . ' (fieldname: ' . $this->getName() . ') already exist -> please check immediately!');
                    Logger::error($e);

                    // try it again with an update if the insert fails, shouldn't be the case, but it seems that
                    // sometimes the insert throws an exception

                    throw $e;
                }
            }
        }
    }


    /**
     * Default functionality from ClassDefinition\Data\Object::preGetData
     * @param AbstractObject\ $object
     * @param array $params
     * @return array|null
     */
    public function preGetData($object, $params = []) {
        $data = null;
        if ($object instanceof Object\Concrete) {
            $data = $object->{$this->getName()};
            if ($this->getLazyLoading() && !in_array($this->getName(), $object->getO__loadedLazyFields(), true)) {
                $data = $this->load($object, ['force' => true]);

                $setter = 'set' . ucfirst($this->getName());
                if (method_exists($object, $setter)) {
                    $object->$setter($data);
                }
            }
        } elseif ($object instanceof Object\Localizedfield) {
            $data = $params['data'];
        } elseif ($object instanceof Object\Fieldcollection\Data\AbstractData) {
            $data = $object->{$this->getName()};
        } elseif ($object instanceof Object\Objectbrick\Data\AbstractData) {
            $data = $object->{$this->getName()};
        }


        if (is_array($data) && Object\AbstractObject::doHideUnpublished()) {
            $publishedList = [];
            foreach ($data as $listElement) {
                if (Element\Service::isPublished($listElement)) {
                    $publishedList[] = $listElement;
                }
            }

            return $publishedList;
        }

        return is_array($data) ? $data : [];
    }

    /**
     * Dummy function used just to overwrite default metadata behavior
     * @param Element\AbstractElement $object
     * @param array $params
     * @return null|void
     */
    public function delete($object, $params = []) {
        return null;
    }

    /**
     * Dummy function used just to overwrite default metadata behavior
     * @param $columns
     * @return $this|null
     */
    public function setColumns($columns) {
        return null;
    }

    /**
     * Default pimcore functionality
     * Rewrites id from source to target, $idMapping contains
     * array(
     *  "document" => array(
     *      SOURCE_ID => TARGET_ID,
     *      SOURCE_ID => TARGET_ID
     *  ),
     *  "object" => array(...),
     *  "asset" => array(...)
     * )
     * @param mixed $object
     * @param array $idMapping
     * @param array $params
     * @return Element\ElementInterface
     */
    public function rewriteIds($object, $idMapping, $params = []) {
        $data = $this->getDataFromObjectParam($object, $params);
        $data = $this->rewriteIdsService($data, $idMapping);

        return $data;
    }

    /**
     * @param Object\ClassDefinition\Data|self $masterDefinition
     */
    public function synchronizeWithMasterDefinition(Object\ClassDefinition\Data $masterDefinition) {
        $this->sourceAllowedClassName = $masterDefinition->sourceAllowedClassName;
        $this->sourceVisibleFields = $masterDefinition->sourceVisibleFields;
        $this->bridgeAllowedClassName = $masterDefinition->bridgeAllowedClassName;
        $this->bridgeVisibleFields = $masterDefinition->bridgeVisibleFields;
        $this->sourceHiddenFields = $masterDefinition->sourceHiddenFields;
        $this->bridgeVisibleFields = $masterDefinition->bridgeVisibleFields;
        $this->bridgeHiddenFields = $masterDefinition->bridgeHiddenFields;
        $this->bridgeField = $masterDefinition->bridgeField;
        $this->bridgeFolder = $masterDefinition->bridgeFolder;
    }

    /**
     * Adds fields details like read only, type , title ..
     * and data for select boxes and href's
     * @param AbstractObject $object
     * @throws \Exception
     */
    public function enrichLayoutDefinition($object) {
        if (!$this->sourceAllowedClassName || !$this->bridgeAllowedClassName || !$this->sourceVisibleFields || !$this->bridgeVisibleFields) {
            return;
        }

        $this->sourceVisibleFieldDefinitions = [];
        $this->bridgeVisibleFieldDefinitions = [];

        $sourceClass = ClassDefinition::getByName($this->sourceAllowedClassName);
        $bridgeClass = ClassDefinition::getByName($this->bridgeAllowedClassName);

        foreach ($this->getSourceVisibleFieldsAsArray() as $field) {
            $fd = $sourceClass->getFieldDefinition($field);
            if (!$fd) {
                $fieldFound = false;
                /** @var ClassDefinition $localizedfields */
                if ($localizedfields = $sourceClass->getFieldDefinitions()['localizedfields']) {
                    /**
                     * @var Object\ClassDefinition\Data $fd
                     */
                    if ($fd = $localizedfields->getFieldDefinition($field)) {
                        $fieldFound = true;
                        $isHidden = $this->sourceFieldIsHidden($field);
                        $this->setFieldDefinition('sourceVisibleFieldDefinitions', $fd, $field, true, $isHidden);
                    }
                }
                // Give up it's a system field
                if (!$fieldFound) {
                    $isHidden = $this->sourceFieldIsHidden($field);
                    $this->setSystemFieldDefinition('sourceVisibleFieldDefinitions', $field, $isHidden);
                }
            } else {
                $isHidden = $this->sourceFieldIsHidden($field);
                $this->setFieldDefinition('sourceVisibleFieldDefinitions', $fd, $field, true, $isHidden);
            }
        }

        foreach ($this->getBridgeVisibleFieldsAsArray() as $field) {
            $fd = $bridgeClass->getFieldDefinition($field);

            if (!$fd) {
                $fieldFound = false;
                if ($localizedfields = $bridgeClass->getFieldDefinitions()['localizedfields']) {
                    /** @var Object\ClassDefinition\Data $fd */
                    if ($fd = $localizedfields->getFieldDefinition($field)) {
                        $fieldFound = true;
                        $isHidden = $this->bridgeFieldIsHidden($field);
                        $this->setFieldDefinition('bridgeVisibleFieldDefinitions', $fd, $field, false, $isHidden);
                    }
                }

                // Give up it's a system field
                if (!$fieldFound) {
                    $isHidden = $this->bridgeFieldIsHidden($field);
                    $this->setSystemFieldDefinition('bridgeVisibleFieldDefinitions', $field, $isHidden);
                }

            } else {
                $isHidden = $this->bridgeFieldIsHidden($field);
                $this->setFieldDefinition('bridgeVisibleFieldDefinitions', $fd, $field, false, $isHidden);
            }
        }
    }

    /**
     * @param string $fieldName
     * ex . sourceVisibleFieldDefinitions or bridgeVisibleFieldDefinitions
     * @param Object\ClassDefinition\Data $fd
     * @param string $field
     * @param bool $readOnly
     * @param bool $hidden
     */
    private function setFieldDefinition($fieldName, $fd, $field, $readOnly, $hidden) {
        $this->$fieldName[$field]['name'] = $fd->getName();
        $this->$fieldName[$field]['title'] = $this->formatTitle($fd->getTitle());
        $this->$fieldName[$field]['fieldtype'] = $fd->getFieldtype();
        $this->$fieldName[$field]['readOnly'] = $readOnly;
        $this->$fieldName[$field]['hidden'] = $hidden;
        $this->$fieldName[$field]['mandatory'] = $fd->getMandatory();

        if ($fd instanceof Object\ClassDefinition\Data\Select) {
            $this->$fieldName[$field]['options'] = $fd->getOptions();
        }
    }

    /**
     * @param string $fieldName
     * ex . sourceVisibleFieldDefinitions or bridgeVisibleFieldDefinitions
     * @param string $field
     * @param bool $hidden
     * @throws \Zend_Exception
     */
    private function setSystemFieldDefinition($fieldName, $field, $hidden) {
        $t = \Zend_Registry::get('Zend_Translate');
        $this->$fieldName[$field]['name'] = $field;
        $this->$fieldName[$field]['title'] = $this->formatTitle($t->translate($field));
        $this->$fieldName[$field]['fieldtype'] = 'input';
        $this->$fieldName[$field]['readOnly'] = true;
        $this->$fieldName[$field]['hidden'] = $hidden;
    }

    private function formatTitle($title){
        if($this->newLineSplit){
            return preg_replace('/\s+/', '<br/>', $title);
        }
        return $title;
    }


    /**
     * When this field is localized or included in a object brick this function will be called
     * Encode value for packing it into a single column.
     * @param array $value
     * @param Model\Object\AbstractObject $object
     * @param mixed $params
     * @return mixed
     */
    public function marshal($value, $object = null, $params = []) {
        if (is_array($value)) {
            $result = [];
            foreach ($value as $element) {
                $type = Element\Service::getType($element);
                $id = $element->getId();
                $result[] = [
                    'type' => $type,
                    'id'   => $id,
                ];
            }

            return $result;
        }

        return null;
    }

    /**
     * When this field is localized or included in a object brick this function will be called
     * Used to transform back to object data stored in marshal
     * @param array $value
     * @param Model\Object\AbstractObject $object
     * @param mixed $params
     * @return mixed
     */
    public function unmarshal($value, $object = null, $params = []) {
        if (is_array($value)) {
            $result = [];
            foreach ($value as $elementData) {
                $type = $elementData['type'];
                $id = $elementData['id'];
                $element = Element\Service::getElementById($type, $id);
                if ($element) {
                    $result[] = $element;
                }
            }

            return $result;
        }

        return null;
    }

    /**
     * @return mixed
     */
    public function getSourceAllowedClassName() {
        return $this->sourceAllowedClassName;
    }

    /**
     * @param $sourceAllowedClassName
     * @return $this
     */
    public function setSourceAllowedClassName($sourceAllowedClassName) {
        $this->sourceAllowedClassName = $sourceAllowedClassName;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getSourceVisibleFields() {
        return $this->sourceVisibleFields;
    }

    /**
     * @param $sourceVisibleFields
     * @return $this
     */
    public function setSourceVisibleFields($sourceVisibleFields) {
        /**
         * @extjs6
         */
        if (is_array($sourceVisibleFields)) {
            $sourceVisibleFields = implode(',', $sourceVisibleFields);
        }

        $this->sourceVisibleFields = $sourceVisibleFields;

        return $this;
    }

    /**
     * @return string
     */
    public function getBridgeVisibleFields() {
        return $this->bridgeVisibleFields;
    }

    /**
     * @param $bridgeVisibleFields
     * @return $this
     */
    public function setBridgeVisibleFields($bridgeVisibleFields) {
        /**
         * @extjs6
         */
        if (is_array($bridgeVisibleFields)) {
            $bridgeVisibleFields = implode(',', $bridgeVisibleFields);
        }

        $this->bridgeVisibleFields = $bridgeVisibleFields;

        return $this;
    }

    /**
     * @return string
     */
    public function getBridgeFolder() {
        return $this->bridgeFolder;
    }

    /**
     * @param string $bridgeFolder
     * @return $this
     */
    public function setBridgeFolder($bridgeFolder) {
        $this->bridgeFolder = $bridgeFolder;

        return $this;
    }

    /**
     * @return string
     */
    public function getPhpdocType() {
        return $this->getBridgeFullClassName() . '[]';
    }

    /**
     * @return string
     */
    public function getSourceHiddenFields() {
        return $this->sourceHiddenFields;
    }

    /**
     * @return string
     */
    public function getSourceHiddenFieldsAsArray() {
        return explode(',', $this->sourceHiddenFields);
    }

    /**
     * @param $sourceHiddenFields
     * @return $this
     */
    public function setSourceHiddenFields($sourceHiddenFields) {
        /**
         * @extjs6
         */
        if (is_array($sourceHiddenFields)) {
            $sourceHiddenFields = implode(',', $sourceHiddenFields);
        }

        $this->sourceHiddenFields = $sourceHiddenFields;

        return $this;
    }

    /**
     * @return string
     */
    public function getBridgeHiddenFields() {
        return $this->bridgeHiddenFields;
    }

    /**
     * @return string
     */
    public function getBridgeHiddenFieldsAsArray() {
        return explode(',', $this->bridgeHiddenFields);
    }


    /**
     * @param $bridgeHiddenFields
     * @return $this
     */
    public function setBridgeHiddenFields($bridgeHiddenFields) {
        /**
         * @extjs6
         */
        if (is_array($bridgeHiddenFields)) {
            $bridgeHiddenFields = implode(',', $bridgeHiddenFields);
        }

        $this->bridgeHiddenFields = $bridgeHiddenFields;

        return $this;
    }

    /**
     * @param string $name
     * @return bool
     */
    private function bridgeFieldIsHidden($name) {
        return in_array($name, $this->getBridgeHiddenFieldsAsArray(), true);
    }

    /**
     * @param string $name
     * @return bool
     */
    private function sourceFieldIsHidden($name) {
        return in_array($name, $this->getSourceHiddenFieldsAsArray(), true);
    }

    /**
     * @return bool
     */
    public function getAutoResize() {
        return $this->autoResize;
    }

    /**
     * @param bool $autoResize
     */
    public function setAutoResize($autoResize) {
        $this->autoResize = $autoResize;
    }

    /**
     * @return int
     */
    public function getMaxWidthResize() {
        return $this->maxWidthResize;
    }

    /**
     * @param int $maxWidthResize
     */
    public function setMaxWidthResize($maxWidthResize) {
        $this->maxWidthResize = $maxWidthResize;
    }

    /**
     * @return boolean
     */
    public function getNewLineSplit() {
        return $this->newLineSplit;
    }

    /**
     * @param boolean $newLineSplit
     */
    public function setNewLineSplit($newLineSplit) {
        $this->newLineSplit = $newLineSplit;
    }
}
