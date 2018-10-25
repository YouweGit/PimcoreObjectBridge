<?php

namespace ObjectBridgeBundle\DataDictionary;

use \DataDictionaryBundle\Graph\Entity\Vertex;

class ObjectBridgeVisitor extends \DataDictionaryBundle\Graph\Visitor\AbstractVisitor
{
    public function visit()
    {
        $node = $this->getNode();
        $relation = $this->fieldDefinition;
        $node->addVertex(
            new Vertex(
                $relation->getName(),
                $relation->getTitle(),
                $relation->getBridgeAllowedClassName()
            )
        );
    }
    private function getNode() : \DataDictionaryBundle\Graph\Interfaces\Node
    {
        return $this->getGraph()->getNode(
            $this->classDefinition->getName()
        );
    }
}