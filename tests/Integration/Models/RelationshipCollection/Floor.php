<?php

namespace GraphAware\Neo4j\OGM\Tests\Integration\Models\RelationshipCollection;

use GraphAware\Neo4j\OGM\Annotations as OGM;

/**
 * Class Floor
 * @package GraphAware\Neo4j\OGM\Tests\Integration\Models\RelationshipCollection
 *
 * @OGM\Node(label="Floor")
 */
class Floor
{
    /**
     * @OGM\GraphId()
     *
     * @var int
     */
    protected $id;

    /**
     * @OGM\Property()
     *
     * @var int
     */
    protected $level;

    /**
     * @OGM\Relationship(type="HAS_FLOOR", direction="INCOMING", targetEntity="Building", mappedBy="floors")
     *
     * @var Building
     */
    protected $building;

    public function __construct($level)
    {
        $this->level = $level;
    }

    /**
     * @return int
     */
    public function getLevel()
    {
        return $this->level;
    }

    /**
     * @param int $level
     */
    public function setLevel($level)
    {
        $this->level = $level;
    }

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return mixed
     */
    public function getBuilding()
    {
        return $this->building;
    }


}