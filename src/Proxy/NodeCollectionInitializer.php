<?php

declare(strict_types=1);

/*
 * This file is part of the GraphAware Neo4j PHP OGM package.
 *
 * (c) GraphAware Ltd <info@graphaware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace GraphAware\Neo4j\OGM\Proxy;

use GraphAware\Neo4j\OGM\Metadata\RelationshipMetadata;

class NodeCollectionInitializer extends SingleNodeInitializer
{
    public function initialize($baseInstance)
    {
        $persister = $this->em->getEntityPersister($this->metadata->getClassName());
        $persister->getSimpleRelationshipCollection($this->relationshipMetadata->getPropertyName(), $baseInstance);
    }

    public function getCount($baseInstance, RelationshipMetadata $relationshipMetadata)
    {
        $persister = $this->em->getEntityPersister($this->metadata->getClassName());

        return $persister->getCountForRelationship($relationshipMetadata->getPropertyName(), $baseInstance);
    }
}
