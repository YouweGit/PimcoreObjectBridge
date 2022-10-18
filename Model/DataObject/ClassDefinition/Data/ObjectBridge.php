<?php

namespace ObjectBridgeBundle\Model\DataObject\ClassDefinition\Data;

use PDO;
use Pimcore\Model;
use Pimcore\Model\Element;
use Pimcore\Model\DataObject;
use Pimcore\Model\DataObject\AbstractObject;
use Pimcore\Model\DataObject\ClassDefinition;
use Pimcore\Db;
use Pimcore\Model\DataObject\Concrete;
use ObjectBridgeBundle\Service\DataObject\ObjectBridgeService;

/** @noinspection ClassOverridesFieldOfSuperClassInspection
 * We need to overwrite public properties because pimcore uses them for storing data
 */
class ObjectBridge extends ClassDefinition\Data\Relations\AbstractRelations implements ClassDefinition\Data\QueryResourcePersistenceAwareInterface
{
    use DataObject\ClassDefinition\Data\Extension\Relation;
    use ClassDefinition\Data\Extension\QueryColumnType;

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
     * Type for the column to query (required for localized fields)
     *
     * @var string
     */
    public $queryColumnType = 'text';

    /**
     * @var bool
     */
    public $relationType = true;

    /**
     * Type for the generated phpdoc
     *
     * @var string
     */
    public $phpdocType = "\\Pimcore\\Model\\DataObject\\AbstractObject[]";

    /** @var string */
    public $sourceVisibleFieldDefinitions;

    /** @var string */
    public $bridgeVisibleFieldDefinitions;

    /** @var bool */
    public $autoResize;

    /** @var bool */
    public $newLineSplit;

    /** @var int */
    public $maxWidthResize;

    /** @var bool */
    public $enableFiltering;

    /** @var bool */
    public $enableBatchEdit;

    /** @var bool */
    public $allowCreate;

    /** @var bool */
    public $allowDelete;

    /** @var string */
    public $bridgePrefix;

    /** @var string */
    public $sourcePrefix;

    /** @var string */
    public $decimalPrecision;

    /** @var bool */
    public $disableUpDown;

    /**
     * Disable lazy loading by default as it is not actually supported
     *
     * @var bool
     */
    public $lazyLoading = false;

    /**
     * @return bool
     */
    public function getLazyLoading()
    {
        return false;
    }

    /**
     * Override to ensure lazy loading stays disabled by default
     *
     * @param bool $lazyLoading
     * @return self
     */
    public function setLazyLoading($lazyLoading)
    {
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function prepareDataForPersistence($data, $object = null, $params = [])
    {
        if (!is_array($data)) {
            return null;
        }

        $counter = 1;
        $preparedData = [];
        foreach ($data as $object) {
            if ($object instanceof DataObject\Concrete) {
                $preparedData[] = [
                    'dest_id'   => $object->getId(),
                    'type'      => 'object',
                    'fieldname' => $this->getName(),
                    'index'     => $counter,
                ];
            }

            $counter++;
        }

        return $preparedData;
    }

    /**
     * @inheritdoc
     */
    public function loadData($data, $object = null, $params = [])
    {
        $objects = [
            'dirty' => false,
            'data' => []
        ];

        if (!is_array($data)) {
            return $objects;
        }

        foreach ($data as $object) {
            $distinationObject = DataObject::getById($object['dest_id']);
            if ($distinationObject instanceof DataObject\Concrete) {
                $objects['data'][] = $distinationObject;
            } else {
                $objects['dirty'] = true;
            }
        }

        return $objects;
    }

    /**
     * @see QueryResourcePersistenceAwareInterface::getDataForQueryResource
     *
     * @param array $data
     * @param null|Model\DataObject\AbstractObject $object
     * @param mixed $params
     *
     * @throws \Exception
     *
     * @return string|null
     */
    public function getDataForQueryResource($data, $object = null, $params = [])
    {
        if ($this->isNullOrFalse($data)) {
            return null;
        }

        if (!is_array($data)) {
            throw new \Exception(
                'invalid data passed to getDataForQueryResource. Must be array and it is of type: ' . gettype($data),
                1574671785
            );
        }

        $ids = [];
        foreach ($data as $object) {
            if ($object instanceof DataObject\Concrete) {
                $ids[] = $object->getId();
            }
        }

        if (empty($ids)) {
            return '';
        }

        return ',' . implode(',', $ids) . ',';
    }

    /**
     * Converts sql data to object
     * @see ClassDefinition\Data::getDataFromResource
     * @param array $data
     * @param null|AbstractObject $object
     * @param mixed $params
     * @return array
     */
    public function getDataFromResource($data, $object = null, $params = [])
    {
        $objects = [];
        if ($this->isNotArrayOrEmpty($data)) {
            return $objects;
        }

        foreach ($data as $objectData) {
            $distinationObject = AbstractObject::getById($objectData['dest_id']);
            if ($distinationObject instanceof Concrete) {
                $objects[] = $distinationObject;
            }
        }

        //must return array - otherwise this means data is not loaded
        return $objects;
    }

    /**
     * @return bool
     */
    public function supportsDirtyDetection()
    {
        return false;
    }

    /**
     * @see ClassDefinition\Data::getDataForEditmode
     * @param array $data
     * @param null|AbstractObject $object
     * @param mixed $params
     * @return array
     * @throws \Exception
     */
    public function getDataForEditmode($data, $object = null, $params = [])
    {
        $dataForEditMode = [];
        if ($this->isNotArrayOrEmpty($data)) {
            return $dataForEditMode;
        }

        $sourceClassDefinition = $this->getSourceClassDefinition();
        $bridgeClassDefinition = $this->getBridgeClassDefinition();

        $bridgeVisibleFieldsArray = $this->removeRestrictedKeys($this->getBridgeVisibleFieldsAsArray());

        foreach ($data as $bridgeObject) {
            if (!$bridgeObject instanceof Concrete) {
                continue;
            }

            $bridgeIdKey = ucfirst($bridgeClassDefinition->getName()) . '_id';
            $columnData = [];
            $columnData[$bridgeIdKey] = $bridgeObject->getId();

            foreach ($bridgeVisibleFieldsArray as $bridgeVisibleField) {
                $fieldDefinition = $bridgeClassDefinition->getFieldDefinition($bridgeVisibleField);
                $key = ucfirst($bridgeClassDefinition->getName()) . '_' . $bridgeVisibleField;

                if (!$fieldDefinition instanceof ClassDefinition\Data\ManyToOneRelation) {
                    $columnData[$key] = $bridgeObject->get($bridgeVisibleField);
                    continue;
                }

                $valueObject = $bridgeObject->get($bridgeVisibleField);

                // To avoid making too many requests to the server we add the display property on
                // run time but default path, but you can implement whatever to string method
                // Javascript will check if have a display property and use it

                $columnData[$key] = $valueObject ? $valueObject->getId() : null;
                $columnData[$key . '_display'] = $valueObject ? (string)$valueObject : null;
            }

            $bridgeFieldName = $this->getBridgeField();
            $bridgeFieldGetter = 'get' . $bridgeFieldName;
            $sourceObject = $bridgeObject->$bridgeFieldGetter();

            if (empty($sourceObject)) {
                throw new \RuntimeException(
                    "Relation '" . $bridgeFieldName . "' can not be empty for object with id " . $bridgeObject->getId(),
                    1574671786
                );
            }

            if (!$sourceObject instanceof Concrete) {
                throw new \RuntimeException(
                    sprintf('Database has an inconsistency, please remove object with id %s to fix the error', $bridgeObject->getId()),
                    1574671787
                );
            }

            $sourceIdKey = ucfirst($sourceClassDefinition->getName()) . '_id';
            $columnData[$sourceIdKey] = $sourceObject->getId();
            $sourceVisibleFieldsArray = $this->removeRestrictedKeys($this->getSourceVisibleFieldsAsArray());

            foreach ($sourceVisibleFieldsArray as $sourceVisibleField) {
                $fieldDefinition = $sourceClassDefinition->getFieldDefinition($sourceVisibleField);
                $key = ucfirst($sourceClassDefinition->getName()) . '_' . $sourceVisibleField;

                if ($fieldDefinition instanceof ClassDefinition\Data\ManyToOneRelation) {
                    $valueObject = (string)$sourceObject->get($sourceVisibleField);
                    $columnData[$key] = $valueObject;
                } else {
                    $columnData[$key] = $sourceObject->get($sourceVisibleField);
                }
            }
            $dataForEditMode[] = $columnData;
        }

        return $dataForEditMode;
    }

    /**
     * @see ClassDefinition\Data::getDataFromEditmode
     * @param array $data
     * @param null|Concrete $object
     * @param mixed $params
     * @return array
     * @throws \Exception
     */
    public function getDataFromEditmode($data, $object = null, $params = [])
    {
        //if not set, return null
        if ($data === null || $data === false) {
            return null;
        }

        $objectsBridge = [];
        if ($this->isNotArrayOrEmpty($data)) {
            return $objectsBridge;
        }

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
            /** @var Concrete $bridgeObject */
            if (!$bridgeObjectId) {
                $bridgeObject = new $bridgeClass;
                $parent = Model\DataObject\Service::createFolderByPath($this->bridgeFolder);
                if (!$parent instanceof AbstractObject) {
                    throw new \InvalidArgumentException(
                        sprintf('Parent not found at "%s" please check your object bridge configuration "Bridge folder"', $this->bridgeFolder),
                        1574671788
                    );
                }
                $bridgeObject->setParent($parent);
                $bridgeObject->setKey($this->bridgePrefix . $object->getId() . '_' . $sourceObject->getId());
                // Make sure its unique else saving will throw an error
                $bridgeObject->setKey(Model\DataObject\Service::getUniqueKey($bridgeObject));
            } else {
                $bridgeObject = $bridgeClass::getById($bridgeObjectId);
            }

            // Store data to bridge object
            $bridgeVisibleFieldsArray = $this->getBridgeVisibleFieldsAsArray();
            // Id should be never be edited
            $bridgeVisibleFieldsArray = $this->removeRestrictedKeys($bridgeVisibleFieldsArray);

            foreach ($bridgeVisibleFieldsArray as $bridgeVisibleField) {
                $fieldDefinition = $bridgeClassDef->getFieldDefinition($bridgeVisibleField);

                $key = ucfirst($bridgeClassDef->getName()) . '_' . $bridgeVisibleField;
                if (!array_key_exists($key, $objectData)) {
                    continue;
                }

                if ($fieldDefinition instanceof ClassDefinition\Data\ManyToOneRelation) {
                    $valueObject = $this->getObjectForManyToOneRelation($fieldDefinition, $objectData[$key]);
                    $bridgeObject->set($bridgeVisibleField, $valueObject);
                } elseif ($fieldDefinition instanceof ClassDefinition\Data\Date) {
                    if (!empty($objectData[$key]['date']) && is_string($objectData[$key]['date'])) {
                        $bridgeObject->set($bridgeVisibleField, new \DateTime($objectData[$key]['date']));
                    } elseif (!empty($objectData[$key]) && is_string($objectData[$key])) {
                        $bridgeObject->set($bridgeVisibleField, new \DateTime($objectData[$key]));
                    }
                } else {
                    $bridgeObject->set($bridgeVisibleField, $objectData[$key]);
                }
            }

            $bridgeObject->set($this->bridgeField, $sourceObject);
            $bridgeObject->setOmitMandatoryCheck(true);
            $bridgeObject->setPublished(true);
            $bridgeObject->save();

            if ($bridgeObject && $bridgeObject->getClassName() === $this->getBridgeAllowedClassName()) {
                $objectsBridge[] = $bridgeObject;
            }
        }

        return $objectsBridge;
    }

    /**
     * @return mixed|null|ClassDefinition
     * @throws \Exception
     */
    private function getSourceClassDefinition()
    {
        return ClassDefinition::getByName($this->sourceAllowedClassName);
    }

    /**
     * @return mixed|null|ClassDefinition
     */
    private function getBridgeClassDefinition()
    {
        return ClassDefinition::getByName($this->bridgeAllowedClassName);
    }

    /**
     * @param array $visibleFieldsArray
     * @return array
     */
    private function removeRestrictedKeys($visibleFieldsArray)
    {
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
    public function getBridgeVisibleFieldsAsArray()
    {
        return explode(',', $this->bridgeVisibleFields);
    }

    /**
     * @return string
     */
    public function getBridgeField()
    {
        return $this->bridgeField;
    }

    /**
     * @param string $bridgeField
     * @return $this
     */
    public function setBridgeField($bridgeField)
    {
        $this->bridgeField = $bridgeField;

        return $this;
    }

    /**
     * @return array
     */
    public function getSourceVisibleFieldsAsArray()
    {
        return explode(',', $this->sourceVisibleFields);
    }


    /**
     * @return string
     */
    private function getSourceFullClassName()
    {
        return '\\Pimcore\\Model\\DataObject\\' . ucfirst($this->sourceAllowedClassName);
    }

    /**
     * @return string
     */
    private function getBridgeFullClassName()
    {
        return '\\Pimcore\\Model\\DataObject\\' . ucfirst($this->bridgeAllowedClassName);
    }

    /**
     * @param Concrete $object
     * @param Concrete|string $bridgeClass
     * @param int $sourceId
     * @return null|int
     */
    private function getBridgeIdBySourceAndOwner($object, $bridgeClass, $sourceId)
    {
        $db = Db::get();
        $select = $db->select()
            ->from(['dor' => 'object_relations_' . $object::classId()], [])
            ->joinInner(['dp_objects' => 'object_' . $bridgeClass::classId()], 'dor.dest_id = dp_objects.oo_id AND dor.type = "object"', ['oo_id'])
            ->where('dor.src_id = ?', $object->getId())
            ->where('dp_objects.' . $this->bridgeField . '__id = ?', $sourceId);


        $stmt = $db->query($select);

        return $stmt->fetch(PDO::FETCH_COLUMN, 0);
    }

    /**
     * @param ClassDefinition\Data\ManyToOneRelation $fieldDefinition
     * @param string|int $value
     * @return null|AbstractObject
     */
    private function getObjectForManyToOneRelation(ClassDefinition\Data\ManyToOneRelation $fieldDefinition, $value)
    {
        $object = null;
        $class = current($fieldDefinition->getClasses());
        if (is_array($class) && array_key_exists('classes', $class)) {
            $class = $class['classes'];
            /** @var AbstractObject $className */
            $className = '\\Pimcore\\Model\\DataObject\\' . $class;

            $object = $className::getById($value);
        }

        return $object;
    }

    /**
     * @return string
     */
    public function getBridgeAllowedClassName()
    {
        return $this->bridgeAllowedClassName;
    }

    /**
     * @param string $bridgeAllowedClassName
     * @return ObjectBridge
     */
    public function setBridgeAllowedClassName($bridgeAllowedClassName)
    {
        $this->bridgeAllowedClassName = $bridgeAllowedClassName;

        return $this;
    }

    /**
     * @param array $data
     * @param null|Concrete $object
     * @param array $params
     * @return array
     */
    public function getDataForGrid($data, $object = null, $params = [])
    {
        if (!is_array($data)) {
            return null;
        }

        $paths = [];
        foreach ($data as $element) {
            if ($element instanceof Element\ElementInterface) {
                $paths[] = (string)$element;
            }
        }

        return $paths;
    }

    /**
     * @see ClassDefinition\Data::getVersionPreview
     * @param array $data
     * @param null|AbstractObject $object
     * @param mixed $params
     * @return string|null
     */
    public function getVersionPreview($data, $object = null, $params = [])
    {
        if ($this->isNotArrayOrEmpty($data)) {
            return null;
        }

        $paths = [];
        foreach ($data as $element) {
            if ($element instanceof Element\ElementInterface) {
                $paths[] = (string)$element;
            }
        }

        return implode('<br />', $paths);
    }

    /**
     * Checks if data is valid for current data field
     *
     * @param mixed $data
     * @param bool $omitMandatoryCheck
     * @throws \Exception
     */
    public function checkValidity($data, $omitMandatoryCheck = false, $params = [])
    {
        if (!$omitMandatoryCheck && $this->getMandatory() && empty($data)) {
            throw new Element\ValidationException(
                'Empty mandatory field [ ' . $this->getName() . ' ]',
                1574671789
            );
        }

        if (!is_array($data)) {
            return;
        }

        /** @var Concrete $objectBridge */
        foreach ($data as $objectBridge) {
            $bridgeClassFullName = $this->getBridgeFullClassName();
            if (!($objectBridge instanceof $bridgeClassFullName)) {
                throw new Element\ValidationException('Expected ' . $bridgeClassFullName, 1574671790);
            }

            foreach ($objectBridge->getClass()->getFieldDefinitions() as $fieldDefinition) {
                $fieldDefinition->checkValidity(
                    $objectBridge->get($fieldDefinition->getName()),
                    $omitMandatoryCheck
                );
            }

            if (!($objectBridge instanceof Concrete) || $objectBridge->getClassName() !== $this->getBridgeAllowedClassName()) {
                $id = $objectBridge instanceof Concrete ? $objectBridge->getId() : '??';
                throw new Element\ValidationException(
                    'Invalid object relation to object [' . $id . '] in field ' . $this->getName(),
                    1574671791
                );
            }
        }
    }

    /**
     * Default functionality from ClassDefinition\Data\Object::getFromCsvImport
     * Converts object data to a simple string value or CSV Export
     *
     * @param AbstractObject $object
     * @param array $params
     * @return string|null
     * @throws \Exception
     */
    public function getForCsvExport($object, $params = [])
    {
        /** @var array $data */
        $data = $this->getDataFromObjectParam($object, $params);
        if (!is_array($data)) {
            return null;
        }

        $paths = [];
        foreach ($data as $eo) {
            if ($eo instanceof Element\ElementInterface) {
                $paths[] = $eo->getRealFullPath();
            }
        }

        return implode(',', $paths);
    }

    /**
     * Default functionality from ClassDefinition\Data\Object::getFromCsvImport
     * Will import comma separated paths
     *
     * @param mixed $importValue
     * @param null|AbstractObject $object
     * @param mixed $params
     * @return array|mixed
     */
    public function getFromCsvImport($importValue, $object = null, $params = [])
    {
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
    public function getCacheTags($data, $tags = [])
    {
        $tags = is_array($tags) ? $tags : [];

        if ($this->isNotArrayOrEmpty($data)) {
            return $tags;
        }

        foreach ($data as $object) {
            if ($object instanceof Element\ElementInterface && !array_key_exists($object->getCacheTag(), $tags)) {
                $tags = $object->getCacheTags($tags);
            }
        }

        return $tags;
    }

    /**
     * Default functionality from ClassDefinition\Data\Object::resolveDependencies
     *
     * @param array $data
     * @return array
     */
    public function resolveDependencies($data)
    {
        $dependencies = [];
        if ($this->isNotArrayOrEmpty($data)) {
            return $dependencies;
        }

        foreach ($data as $object) {
            if ($object instanceof AbstractObject) {
                $dependencies['object_' . $object->getId()] = [
                    'id'   => $object->getId(),
                    'type' => 'object',
                ];
            }
        }

        return $dependencies;
    }

    /**
     * Default functionality from ClassDefinition\Data\Object::getForWebserviceExport
     *
     * @param AbstractObject $object
     * @param array $params
     * @return array|null
     * @throws \Exception
     */
    public function getForWebserviceExport($object, $params = [])
    {
        $data = $this->getDataFromObjectParam($object, $params);
        if (!is_array($data)) {
            return null;
        }

        $items = [];
        foreach ($data as $element) {
            if ($element instanceof Element\ElementInterface) {
                $items[] = [
                    'type' => $element->getType(),
                    'id'   => $element->getId(),
                ];
            }
        }

        return $items;
    }

    /**
     * Default functionality from ClassDefinition\Data\Object::getFromWebserviceImport
     * @param array $value
     * @param null $object
     * @param mixed $params
     * @param null $idMapper
     * @return array|mixed
     * @throws \Exception
     */
    public function getFromWebserviceImport($value, $object = null, $params = [], $idMapper = null)
    {
        if ($this->isNullOrFalse($value)) {
            return null;
        }

        if (!is_array($value)) {
            throw new \InvalidArgumentException(
                'Cannot get values from web service import - invalid data',
                1574671792
            );
        }

        $relatedObjects = [];
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

            if ($relatedObject instanceof AbstractObject) {
                $relatedObjects[] = $relatedObject;
                continue;
            }

            if (!$idMapper || !$idMapper->ignoreMappingFailures()) {
                throw new \InvalidArgumentException(
                    'Cannot get values from web service import - references unknown object with id [ ' . $item['id'] . ' ]',
                    1574671793
                );
            }

            $idMapper->recordMappingFailure('object', $object->getId(), 'object', $item['id']);
        }

        return $relatedObjects;
    }

    /**
     * Dummy function used just to overwrite default metadata behavior
     * @param Element\AbstractElement $object
     * @param array $params
     * @return null|void
     */
    public function delete($object, $params = [])
    {
        return null;
    }

    /**
     * Dummy function used just to overwrite default metadata behavior
     * @param $columns
     * @return $this|null
     */
    public function setColumns($columns)
    {
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
     * @return array
     * @throws \Exception
     */
    public function rewriteIds($object, $idMapping, $params = [])
    {
        $data = $this->getDataFromObjectParam($object, $params);
        $data = $this->rewriteIdsService($data, $idMapping);

        return $data;
    }

    /**
     * @param ClassDefinition\Data|self $masterDefinition
     */
    public function synchronizeWithMasterDefinition(ClassDefinition\Data $masterDefinition)
    {
        $this->sourceAllowedClassName = $masterDefinition->sourceAllowedClassName;
        $this->sourceVisibleFields = $masterDefinition->sourceVisibleFields;
        $this->bridgeAllowedClassName = $masterDefinition->bridgeAllowedClassName;
        $this->bridgeVisibleFields = $masterDefinition->bridgeVisibleFields;
        $this->sourceHiddenFields = $masterDefinition->sourceHiddenFields;
        $this->bridgeVisibleFields = $masterDefinition->bridgeVisibleFields;
        $this->bridgeHiddenFields = $masterDefinition->bridgeHiddenFields;
        $this->bridgeField = $masterDefinition->bridgeField;
        $this->bridgeFolder = $masterDefinition->bridgeFolder;
        $this->sourcePrefix = $masterDefinition->sourcePrefix;
        $this->bridgePrefix = $masterDefinition->bridgePrefix;
        $this->allowCreate = $masterDefinition->allowCreate;
        $this->allowDelete = $masterDefinition->allowDelete;
        $this->decimalPrecision = $masterDefinition->decimalPrecision;
        $this->disableUpDown = $masterDefinition->disableUpDown;
        $this->relationType = $masterDefinition->relationType;
    }

    /**
     * Adds fields details like read only, type , title ..
     * and data for select boxes and many to one relations
     * @param AbstractObject $object
     * @param array $context additional contextual data
     * @throws \Exception
     */
    public function enrichLayoutDefinition($object, $context = [])
    {
        if (!$this->sourceAllowedClassName || !$this->bridgeAllowedClassName || !$this->sourceVisibleFields || !$this->bridgeVisibleFields) {
            return;
        }

        $this->sourceVisibleFieldDefinitions = [];
        $this->bridgeVisibleFieldDefinitions = [];

        $sourceClass = ClassDefinition::getByName($this->sourceAllowedClassName);
        $bridgeClass = ClassDefinition::getByName($this->bridgeAllowedClassName);

        foreach ($this->getSourceVisibleFieldsAsArray() as $field) {
            $fieldDefinition = $sourceClass->getFieldDefinition($field);
            if ($fieldDefinition instanceof ClassDefinition\Data) {
                $isHidden = $this->sourceFieldIsHidden($field);
                $this->setFieldDefinition('sourceVisibleFieldDefinitions', $fieldDefinition, $field, true, $isHidden);
                continue;
            }

            // Fallback to localized fields
            $fieldFound = false;
            /** @var ClassDefinition $localizedfields */
            if ($localizedfields = $sourceClass->getFieldDefinitions()['localizedfields'] ?? null) {
                /**
                 * @var ClassDefinition\Data $fieldDefinition
                 */
                if ($fieldDefinition = $localizedfields->getFieldDefinition($field)) {
                    $fieldFound = true;
                    $isHidden = $this->sourceFieldIsHidden($field);
                    $this->setFieldDefinition('sourceVisibleFieldDefinitions', $fieldDefinition, $field, true, $isHidden);
                }
            }

            // Give up, it's a system field
            if (!$fieldFound) {
                $isHidden = $this->sourceFieldIsHidden($field);
                $this->setSystemFieldDefinition('sourceVisibleFieldDefinitions', $field, $isHidden);
            }
        }

        foreach ($this->getBridgeVisibleFieldsAsArray() as $field) {
            $fieldDefinition = $bridgeClass->getFieldDefinition($field);

            if ($fieldDefinition instanceof ClassDefinition\Data) {
                $isHidden = $this->bridgeFieldIsHidden($field);
                $this->setFieldDefinition('bridgeVisibleFieldDefinitions', $fieldDefinition, $field, false, $isHidden);
                continue;
            }

            // Fallback to localized fields
            $fieldFound = false;
            if ($localizedfields = $bridgeClass->getFieldDefinitions()['localizedfields']) {
                /** @var ClassDefinition\Data $fieldDefinition */
                if ($fieldDefinition = $localizedfields->getFieldDefinition($field)) {
                    $fieldFound = true;
                    $isHidden = $this->bridgeFieldIsHidden($field);
                    $this->setFieldDefinition('bridgeVisibleFieldDefinitions', $fieldDefinition, $field, false, $isHidden);
                }
            }

            // Give up, it's a system field
            if (!$fieldFound) {
                $isHidden = $this->bridgeFieldIsHidden($field);
                $this->setSystemFieldDefinition('bridgeVisibleFieldDefinitions', $field, $isHidden);
            }
        }
    }

    /**
     * Encode value for packing it into a single column.
     *
     * @param mixed $value
     * @param Model\DataObject\AbstractObject $object
     * @param mixed $params
     * @return array|null
     */
    public function marshal($value, $object = null, $params = [])
    {
        if (!is_array($value)) {
            return null;
        }

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

    /**
     * See marshal
     *
     * @param mixed $value
     * @param Model\DataObject\AbstractObject $object
     * @param mixed $params
     * @return array|null
     */
    public function unmarshal($value, $object = null, $params = [])
    {
        if (!is_array($value)) {
            return null;
        }

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

    /**
     * @param string $fieldName sourceVisibleFieldDefinitions or bridgeVisibleFieldDefinitions
     * @param ClassDefinition\Data $fieldDefinition
     * @param string $field
     * @param bool $readOnly
     * @param bool $hidden
     */
    private function setFieldDefinition(
        string $fieldName,
        ClassDefinition\Data $fieldDefinition,
        string $field,
        bool $readOnly,
        bool $hidden
    ) {
        $this->$fieldName[$field]['name'] = $fieldDefinition->getName();
        $this->$fieldName[$field]['title'] = $this->formatTitle($fieldDefinition->getTitle());
        $this->$fieldName[$field]['fieldtype'] = $fieldDefinition->getFieldtype();
        $this->$fieldName[$field]['readOnly'] = $readOnly || $fieldDefinition->getNoteditable() ? true : false;
        $this->$fieldName[$field]['hidden'] = $hidden;
        $this->$fieldName[$field]['mandatory'] = $fieldDefinition->getMandatory();

        // Add default value if any is set
        if (method_exists($fieldDefinition, 'getDefaultValue') && strlen(strval($fieldDefinition->getDefaultValue())) > 0) {
            $this->$fieldName[$field]['default'] = $fieldDefinition->getDefaultValue();
        }

        // Dropdowns have options
        if ($fieldDefinition instanceof ClassDefinition\Data\Select) {
            $this->$fieldName[$field]['options'] = $fieldDefinition->getOptions();
        }
    }

    /**
     * @param string $fieldName sourceVisibleFieldDefinitions or bridgeVisibleFieldDefinitions
     * @param string $field
     * @param bool $hidden
     */
    private function setSystemFieldDefinition($fieldName, $field, $hidden)
    {
        /** @var  $translation */
        $translation = \Pimcore::getContainer()->get('translator');
        $this->$fieldName[$field]['name'] = $field;
        $this->$fieldName[$field]['title'] = $this->formatTitle($translation->trans($field));
        $this->$fieldName[$field]['fieldtype'] = 'input';
        $this->$fieldName[$field]['readOnly'] = true;
        $this->$fieldName[$field]['hidden'] = $hidden;
    }

    private function formatTitle($title): ?string
    {
        if ($this->newLineSplit) {
            return preg_replace('/\s+/', '<br/>', $title);
        }

        return $title;
    }


    /**
     * @return mixed
     */
    public function getSourceAllowedClassName()
    {
        return $this->sourceAllowedClassName;
    }

    /**
     * @param $sourceAllowedClassName
     * @return $this
     */
    public function setSourceAllowedClassName($sourceAllowedClassName)
    {
        $this->sourceAllowedClassName = $sourceAllowedClassName;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getSourceVisibleFields()
    {
        return $this->sourceVisibleFields;
    }

    /**
     * @param $sourceVisibleFields
     * @return $this
     */
    public function setSourceVisibleFields($sourceVisibleFields)
    {
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
    public function getBridgeVisibleFields()
    {
        return $this->bridgeVisibleFields;
    }

    /**
     * @param $bridgeVisibleFields
     * @return $this
     */
    public function setBridgeVisibleFields($bridgeVisibleFields)
    {
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
    public function getBridgeFolder()
    {
        return $this->bridgeFolder;
    }

    /**
     * @param string $bridgeFolder
     * @return $this
     */
    public function setBridgeFolder($bridgeFolder)
    {
        $this->bridgeFolder = $bridgeFolder;

        return $this;
    }

    /**
     * @return string
     */
    public function getPhpdocType()
    {
        return $this->getBridgeFullClassName() . '[]';
    }

    /**
     * @return string
     */
    public function getSourceHiddenFields()
    {
        return $this->sourceHiddenFields;
    }

    /**
     * @return array
     */
    public function getSourceHiddenFieldsAsArray()
    {
        return explode(',', $this->sourceHiddenFields);
    }

    /**
     * @param $sourceHiddenFields
     * @return $this
     */
    public function setSourceHiddenFields($sourceHiddenFields)
    {
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
    public function getBridgeHiddenFields()
    {
        return $this->bridgeHiddenFields;
    }

    /**
     * @return array
     */
    public function getBridgeHiddenFieldsAsArray()
    {
        return explode(',', $this->bridgeHiddenFields);
    }


    /**
     * @param array $bridgeHiddenFields
     * @return $this
     */
    public function setBridgeHiddenFields($bridgeHiddenFields)
    {
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
    private function bridgeFieldIsHidden($name): bool
    {
        return in_array($name, $this->getBridgeHiddenFieldsAsArray(), true);
    }

    /**
     * @param string $name
     * @return bool
     */
    private function sourceFieldIsHidden($name): bool
    {
        return in_array($name, $this->getSourceHiddenFieldsAsArray(), true);
    }

    /**
     * @return bool
     */
    public function getAutoResize()
    {
        return $this->autoResize;
    }

    /**
     * @param bool $autoResize
     */
    public function setAutoResize($autoResize)
    {
        $this->autoResize = $autoResize;
    }

    /**
     * @return int
     */
    public function getMaxWidthResize()
    {
        return $this->maxWidthResize;
    }

    /**
     * @param int $maxWidthResize
     */
    public function setMaxWidthResize($maxWidthResize)
    {
        $this->maxWidthResize = $maxWidthResize;
    }

    /**
     * @return bool
     */
    public function getNewLineSplit()
    {
        return $this->newLineSplit;
    }

    /**
     * @param bool $newLineSplit
     */
    public function setNewLineSplit($newLineSplit)
    {
        $this->newLineSplit = $newLineSplit;
    }

    /**
     * @return bool
     */
    public function getEnableFiltering()
    {
        return $this->enableFiltering;
    }

    /**
     * @param bool $enableFiltering
     */
    public function setEnableFiltering($enableFiltering)
    {
        $this->enableFiltering = $enableFiltering;
    }

    /**
     * @return bool
     */
    public function getEnableBatchEdit()
    {
        return $this->enableBatchEdit;
    }

    /**
     * @param bool $enableBatchEdit
     */
    public function setEnableBatchEdit($enableBatchEdit)
    {
        $this->enableBatchEdit = $enableBatchEdit;
    }

    /**
     * @return boolean
     */
    public function getAllowCreate()
    {
        return $this->allowCreate;
    }

    /**
     * @param bool $allowCreate
     */
    public function setAllowCreate($allowCreate)
    {
        $this->allowCreate = $allowCreate;
    }

    /**
     * @return bool
     */
    public function getAllowDelete()
    {
        return $this->allowDelete;
    }

    /**
     * @param bool $allowDelete
     */
    public function setAllowDelete($allowDelete)
    {
        $this->allowDelete = $allowDelete;
    }

    /**
     * @return string
     */
    public function getSourcePrefix()
    {
        return $this->sourcePrefix;
    }

    /**
     * @param string $sourcePrefix
     */
    public function setSourcePrefix($sourcePrefix)
    {
        $this->sourcePrefix = $sourcePrefix;
    }

    /**
     * @return string
     */
    public function getBridgePrefix()
    {
        return $this->bridgePrefix;
    }

    /**
     * @param string $bridgePrefix
     */
    public function setBridgePrefix($bridgePrefix)
    {
        $this->bridgePrefix = $bridgePrefix;
    }

    /**
     * @return string
     */
    public function getDecimalPrecision()
    {
        return $this->decimalPrecision;
    }

    /**
     * @param string $decimalPrecision
     */
    public function setDecimalPrecision($decimalPrecision)
    {
        $this->decimalPrecision = $decimalPrecision;
    }

    /**
     * @param bool $value
     */
    public function setDisableUpDown($value)
    {
        $this->disableUpDown = $value;
    }

    /**
     * @return bool
     */
    public function getdisableUpDown()
    {
        return $this->disableUpDown;
    }

    /**
     * @param AbstractObject $object
     * @return bool
     */
    protected function allowObjectRelation($object)
    {
        return true;
    }

    /**
     * @param Model\Asset $asset
     * @return bool
     */
    protected function allowAssetRelation($asset)
    {
        return false;
    }

    /**
     * @param Model\Document $document
     * @return bool
     */
    protected function allowDocumentRelation($document)
    {
        return false;
    }

    /**
     * @param $object
     * @param array $params
     * @return array|mixed|null
     * @throws \Exception
     */
    public function preGetData($object, $params = [])
    {
        $data = null;
        if ($object instanceof DataObject\Concrete) {
            $data = $object->getObjectVar($this->getName());
            if ($this->getLazyLoading()) {
                // Pimcore >= 5.6
                if (method_exists($object, 'getLazyLoadedFieldNames')) {
                    $lazyLoadedFields = $object->getLazyLoadedFieldNames();
                } else {
                    // Pimcore <= 5.5
                    $lazyLoadedFields = $object->getO__loadedLazyFields();
                }

                if (!in_array($this->getName(), $lazyLoadedFields)) {
                    $data = $this->load($object, ['force' => true]);

                    $object->setObjectVar($this->getName(), $data);
                    $this->markLazyloadedFieldAsLoaded($object);
                }
            }
        } elseif ($object instanceof DataObject\Localizedfield) {
            $data = $params['data'];
        } elseif ($object instanceof DataObject\Fieldcollection\Data\AbstractData) {
            $data = $object->getObjectVar($this->getName());
        } elseif ($object instanceof DataObject\Objectbrick\Data\AbstractData) {
            $data = $object->getObjectVar($this->getName());
        }

        return is_array($data) ? $data : [];
    }

    /**
     * @param $object
     * @param $data
     * @param array $params
     *
     * @return array|null
     */
    public function preSetData($object, $data, $params = [])
    {
        if ($data === null) {
            $data = [];
        }

        $this->markLazyloadedFieldAsLoaded($object);

        return $data;
    }

    /**
     * @param mixed $value
     * @return bool
     */
    protected function isNotArrayOrEmpty($value): bool
    {
        return !is_array($value) || empty($value);
    }

    /**
     * @param mixed $value
     * @return bool
     */
    protected function isNullOrFalse($value): bool
    {
        return $value === null || $value === false;
    }
}
