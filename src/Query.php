<?php

/*
 * This file is part of the GraphAware Neo4j PHP OGM package.
 *
 * (c) GraphAware Ltd <info@graphaware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace GraphAware\Neo4j\OGM;

use GraphAware\Neo4j\OGM\Exception\Result\NonUniqueResultException;
use GraphAware\Neo4j\OGM\Exception\Result\NoResultException;
use Laudis\Neo4j\Types\CypherList;

class Query
{
    public const HYDRATE_COLLECTION = "HYDRATE_COLLECTION";

    public const HYDRATE_SINGLE = "HYDRATE_SINGLE";

    public const HYDRATE_RAW = "HYDRATE_RAW";

    public const HYDRATE_MAP = "HYDRATE_MAP";

    public const HYDRATE_MAP_COLLECTION = "HYDRATE_MAP_COLLECTION";

    protected string $cql;

    protected array $parameters = [];

    protected array $mappings = [];

    public function __construct(protected EntityManager $entityManager)
    {
    }

    public function setCQL($cql): static
    {
        $this->cql = $cql;

        return $this;
    }

    public function setParameter(string $key, float|int|bool|array|string|null $value, int $type = null): static
    {
        $this->parameters[$key] = [$value, $type];

        return $this;
    }

    public function addEntityMapping(
        string $alias,
        ?string $className,
        string $hydrationType = self::HYDRATE_SINGLE
    ): static {
        $this->mappings[$alias] = [$className, $hydrationType];

        return $this;
    }

    public function getResult(): array
    {
        return $this->execute();
    }

    public function execute(): array
    {
        $stmt = $this->cql;
        $parameters = $this->formatParameters();

        /** @var CypherList $result */
        $result = $this->entityManager->getDatabaseDriver()->run($stmt, $parameters);
        if ($result->count() === 0) {
            return [];
        }

        return $this->handleResult($result);
    }

    private function formatParameters(): array
    {
        $params = [];
        foreach ($this->parameters as $alias => $parameter) {
            $params[$alias] = $parameter[0];
        }

        return $params;
    }

    private function handleResult(CypherList $result): array
    {
        $queryResult = [];

        foreach ($result->toArray() as $record) {
            $row = [];
            $keys = $record->keys();

            foreach ($keys as $key) {

                $mode = array_key_exists($key, $this->mappings) ? $this->mappings[$key][1] : self::HYDRATE_RAW;

                if ($mode === self::HYDRATE_SINGLE) {
                    if (count($keys) === 1) {
                        $row = $this->entityManager->getEntityHydrator($this->mappings[$key][0])->hydrateNode($record->get($key));
                    } else {
                        $row[$key] = $this->entityManager->getEntityHydrator($this->mappings[$key][0])->hydrateNode($record->get($key));
                    }
                } elseif ($mode === self::HYDRATE_COLLECTION) {
                    $coll = [];
                    foreach ($record->get($key) as $i) {
                        $v = $this->entityManager->getEntityHydrator($this->mappings[$key][0])->hydrateNode($i);
                        $coll[] = $v;
                    }
                    $row[$key] = $coll;
                } elseif ($mode === self::HYDRATE_MAP_COLLECTION) {
                    $row[$key] = $this->hydrateMapCollection($record->get($key));
                } elseif ($mode === self::HYDRATE_MAP) {
                    $row[$key] = $this->hydrateMap($record->get($key)->toArray());
                } elseif ($mode === self::HYDRATE_RAW) {
                    $row[$key] = $record->get($key);
                }
            }

            $queryResult[] = $row;
        }

        return $queryResult;
    }

    /**
     * Maps collection of maps.
     * For cases where map is collection of another maps,
     * like in "WITH node, { otherNode.name, otherNode.fieldName} as cols RETURN {val1:value, data: collect(cols) }"
     * in that case "cols" is collection of maps and should be mapped as
     * addEntityMapping('cols', null, Query::HYDRATE_MAP_COLLECTION);
     *
     * @param $map
     * @return array
     */
    private function hydrateMapCollection($map): array
    {
        $row = [];
        foreach ($map as $value) {
            $row[] = $this->hydrateMap($value->toArray());
        }
        return $row;
    }

    /**
     * Hydrates array (map) to entities (if correct mappong is present).
     *
     * Entities will be mapped to values through map keys (instead of neo4j Result`s column key as in handleResult()).
     * Useful for maps like "RETURN {val1:node, data: collect(otherNode) as } as col".
     * In this example two mapping should be present:
     * addEntityMapping('col', null, Query::HYDRATE_MAP);
     * addEntityMapping('data', OtherNode::class, Query::HYDRATE_COLLECTION);
     *
     * @param $map array
     * @return array
     */
    private function hydrateMap(array $map): array
    {
        $row = [];
        foreach ($map as $key => $value) {
            $row[$key] = $this->hydrateMapValue($key, $value);
        }
        return $row;
    }

    /**
     * Recursively maps array`s $key=>$value pair to Node,
     * Node collection, map, map collection or to RAW value.
     * Mapping relies on $key
     *
     * @param string $key
     * @param mixed $value
     * @return array|null|int|object
     */
    private function hydrateMapValue(string $key, mixed $value): array|null|int|object
    {
        $row = [];
        $mode = array_key_exists($key, $this->mappings) ? $this->mappings[$key][1] : self::HYDRATE_RAW;

        if ($mode === self::HYDRATE_SINGLE) {
            $row = $this->entityManager->getEntityHydrator($this->mappings[$key][0])->hydrateNode($value);
        } elseif ($mode === self::HYDRATE_COLLECTION) {
            $coll = [];
            foreach ($value as $i) {
                $v = $this->entityManager->getEntityHydrator($this->mappings[$key][0])->hydrateNode($i);
                $coll[] = $v;
            }
            $row = $coll;
        } elseif ($mode === self::HYDRATE_MAP_COLLECTION) {
            $row = $this->hydrateMapCollection($value);
        } elseif ($mode === self::HYDRATE_MAP) {
            $row = $this->hydrateMap($value->toArray());
        } elseif ($mode === self::HYDRATE_RAW) {
            $row = $value instanceof CypherList ? $value->toArray() : $value;
        }
        return $row;
    }

    public function getOneOrNullResult(): ?array
    {
        $result = $this->execute();

        if (empty($result)) {
            return null;
        }

        if (count($result) > 1) {
            throw new NonUniqueResultException(sprintf('Expected 1 or null result, got %d', count($result)));
        }


        return $result;
    }

    public function getOneResult()
    {
        $result = $this->execute();

        if (empty($result)) {
            throw new NoResultException('Entities have not been found');
        }

        if (count($result) > 1) {
            throw new NonUniqueResultException(sprintf('Expected 1 or null result, got %d', count($result)));
        }

        return $result[0];
    }
}
