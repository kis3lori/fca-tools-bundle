<?php

namespace AppBundle\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;

/**
 * @MongoDB\EmbeddedDocument
 */
class ConceptLattice
{

    /**
     * @MongoDB\Collection
     */
    protected $links;

    /**
     * @MongoDB\Field(type="int")
     */
    protected $minLevel;

    /**
     * @MongoDB\Field(type="int")
     */
    protected $maxLevel;

    /**
     * @MongoDB\Field(type="int")
     */
    protected $maxLevelConceptId;

    /**
     * @MongoDB\Hash
     */
    protected $levels;

    public function __construct()
    {
        $this->links = array();
        $this->levels = array();
    }

    /**
     * Set minLevel
     *
     * @param int $minLevel
     * @return self
     */
    public function setMinLevel($minLevel)
    {
        $this->minLevel = $minLevel;
        return $this;
    }

    /**
     * Get minLevel
     *
     * @return int $minLevel
     */
    public function getMinLevel()
    {
        return $this->minLevel;
    }

    /**
     * Set maxLevel
     *
     * @param int $maxLevel
     * @return self
     */
    public function setMaxLevel($maxLevel)
    {
        $this->maxLevel = $maxLevel;
        return $this;
    }

    /**
     * Get maxLevel
     *
     * @return int $maxLevel
     */
    public function getMaxLevel()
    {
        return $this->maxLevel;
    }

    /**
     * Set maxLevelConceptId
     *
     * @param int $maxLevelConceptId
     * @return self
     */
    public function setMaxLevelConceptId($maxLevelConceptId)
    {
        $this->maxLevelConceptId = $maxLevelConceptId;
        return $this;
    }

    /**
     * Get maxLevelConceptId
     *
     * @return int $maxLevelConceptId
     */
    public function getMaxLevelConceptId()
    {
        return $this->maxLevelConceptId;
    }

    /**
     * Set levels
     *
     * @param int $index
     * @param int $level
     * @return static
     */
    public function setLevel($index, $level)
    {
        $this->levels[$index] = $level;
    }

    /**
     * Set levels
     *
     * @param array $levels
     * @return self
     */
    public function setLevels($levels)
    {
        $this->levels = $levels;
        return $this;
    }

    /**
     * Get levels
     *
     * @return array $levels
     */
    public function getLevels()
    {
        return $this->levels;
    }

    /**
     * Get level
     *
     * @param int $index
     * @return array $level
     */
    public function getLevel($index)
    {
        return $this->levels[$index];
    }

    /**
     * Add link
     *
     * @param array $link
     */
    public function addLink($link)
    {
        $this->links[] = $link;
    }

    /**
     * Set links
     *
     * @param array $links
     * @return self
     */
    public function setLinks($links)
    {
        $this->links = $links;
        return $this;
    }

    /**
     * Get links
     *
     * @return array $links
     */
    public function getLinks()
    {
        return $this->links;
    }
}
