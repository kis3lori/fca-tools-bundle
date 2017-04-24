<?php

namespace AppBundle\Document;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;
use Symfony\Component\HttpFoundation\File\File;
use Vich\UploaderBundle\Mapping\Annotation as Vich;

/**
 * @MongoDB\Document
 * @Vich\Uploadable
 */
class Context
{

    /**
     * @MongoDB\Id
     */
    protected $id;

    /**
     * @MongoDB\Field(type="string")
     */
    protected $name;

    /**
     * @MongoDB\Field(type="string")
     */
    protected $description;

    /**
     * @MongoDB\Field(type="int")
     */
    protected $dimCount;

    /**
     * @MongoDB\Field(type="bool")
     */
    protected $isPublic;

    /**
     * @MongoDB\Field(type="string")
     */
    protected $contextFileName;

    /**
     * @var File
     *
     * @Vich\UploadableField(mapping="context_file", fileNameProperty="contextFileName")
     */
    protected $contextFile;

    /**
     * @MongoDB\Collection
     */
    protected $dimensions;

    /**
     * @MongoDB\Collection
     */
    protected $relations;

    /**
     * @MongoDB\Collection
     */
    protected $concepts;

    /**
     * @MongoDB\Collection
     */
    protected $numericalDimensions;

    /**
     * @MongoDB\Collection
     */
    protected $temporalDimensions;

    /**
     * @MongoDB\EmbedOne(targetDocument="ConceptLattice")
     */
    protected $conceptLattice;

    /**
     * @MongoDB\ReferenceOne(targetDocument="User", inversedBy="contexts")
     */
    protected $user;

    /**
     * @MongoDB\ReferenceMany(targetDocument="ConceptFinderBookmark", mappedBy="context")
     */
    protected $conceptFinderBookmarks;

    /**
     * @var string
     */
    protected $baseFilePath = "web/uploads/context/files/";

    public function __construct($temp = false)
    {
        $this->conceptFinderBookmarks = new ArrayCollection();
        $this->numericalDimensions = array();
        $this->temporalDimensions = array();
        $this->elements = array();
        $this->relations = array();
        $this->concepts = array();

        if ($temp) {
            $this->baseFilePath = "bin/temp/context/files/";
        }
    }

    /**
     * @return string
     */
    public function getBaseFilePath()
    {
        return $this->baseFilePath;
    }

    /**
     * @param string $baseFilePath
     */
    public function setBaseFilePath($baseFilePath)
    {
        $this->baseFilePath = $baseFilePath;
    }

    /**
     * Get context file path
     *
     * @return string
     */
    public function getContextFilePath()
    {
        return $this->baseFilePath . $this->contextFileName;
    }

    /**
     * @return string
     */
    public function getContextAssetPath()
    {
        return substr($this->baseFilePath, 4) . $this->contextFileName;
    }

    /**
     * Get id
     *
     * @return string $id
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set conceptLattice
     *
     * @param ConceptLattice $conceptLattice
     * @return self
     */
    public function setConceptLattice(ConceptLattice $conceptLattice)
    {
        $this->conceptLattice = $conceptLattice;
        return $this;
    }

    /**
     * Get conceptLattice
     *
     * @return ConceptLattice $conceptLattice
     */
    public function getConceptLattice()
    {
        return $this->conceptLattice;
    }

    /**
     * Set name
     *
     * @param string $name
     * @return self
     */
    public function setName($name)
    {
        $this->name = $name;
        return $this;
    }

    /**
     * Get name
     *
     * @return string $name
     */
    public function getName()
    {
        return $this->name;
    }


    /**
     * Set contextFileName
     *
     * @param string $contextFileName
     * @return self
     */
    public function setContextFileName($contextFileName)
    {
        $this->contextFileName = $contextFileName;
        return $this;
    }

    /**
     * Get contextFileName
     *
     * @return string $contextFileName
     */
    public function getContextFileName()
    {
        return $this->contextFileName;
    }

    /**
     * @param File|\Symfony\Component\HttpFoundation\File\UploadedFile $contextFile
     *
     * @return self
     */
    public function setContextFile(File $contextFile = null)
    {
        $this->contextFile = $contextFile;

        return $this;
    }

    /**
     * @return File
     */
    public function getContextFile()
    {
        return $this->contextFile;
    }

    /**
     * Set isPublic
     *
     * @param bool $isPublic
     * @return self
     */
    public function setIsPublic($isPublic)
    {
        $this->isPublic = $isPublic;
        return $this;
    }

    /**
     * Get isPublic
     *
     * @return bool $isPublic
     */
    public function getIsPublic()
    {
        return $this->isPublic;
    }

    /**
     * Set user
     *
     * @param User $user
     * @return self
     */
    public function setUser(User $user)
    {
        $this->user = $user;
        return $this;
    }

    /**
     * Get user
     *
     * @return User $user
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * Set numberOfDimensions
     *
     * @param int $dimCount
     * @return self
     */
    public function setDimCount($dimCount)
    {
        $this->dimCount = $dimCount;
        return $this;
    }

    /**
     * Get numberOfDimensions
     *
     * @return int $numberOfDimensions
     */
    public function getDimCount()
    {
        return $this->dimCount;
    }

    /**
     * Set dimensions
     *
     * @param array $dimensions
     * @return self
     */
    public function setDimensions($dimensions)
    {
        $this->dimensions = $dimensions;
        return $this;
    }

    /**
     * Get dimensions
     *
     * @return array $dimensions
     */
    public function getDimensions()
    {
        return $this->dimensions;
    }

    /**
     * @param int $index
     * @param array $elements
     */
    public function setDimension($index, $elements)
    {
        $this->dimensions[$index] = $elements;
    }

    /**
     * @param int $index
     * @return array
     */
    public function getDimension($index)
    {
        return $this->dimensions[$index];
    }

    /**
     * @param int $dimensionIndex
     * @param string $element
     */
    public function addElement($dimensionIndex, $element)
    {
        if (!isset($this->dimensions[$dimensionIndex])) {
            $this->dimensions[$dimensionIndex] = array();
        }

        $this->dimensions[$dimensionIndex][] = $element;
    }

    /**
     * @param int $dimensionIndex
     * @param int $elementIndex
     * @return string
     */
    public function getElement($dimensionIndex, $elementIndex)
    {
        return $this->dimensions[$dimensionIndex][$elementIndex];
    }

    /**
     * @param int $dimensionIndex
     * @param int $elementIndex
     * @param string $element
     */
    public function setElement($dimensionIndex, $elementIndex, $element)
    {
        if (!isset($this->dimensions[$dimensionIndex])) {
            $this->dimensions[$dimensionIndex] = array();
        }

        $this->dimensions[$dimensionIndex][$elementIndex] = $element;
    }

    /**
     * Add relation
     *
     * @param array $relation
     * @return self
     */
    public function addRelation($relation)
    {
        $this->relations[] = $relation;
    }

    /**
     * Set relations
     *
     * @param array $relations
     * @return self
     */
    public function setRelations($relations)
    {
        $this->relations = $relations;
        return $this;
    }

    /**
     * Get relations
     *
     * @return array $relations
     */
    public function getRelations()
    {
        return $this->relations;
    }

    /**
     * Set concepts
     *
     * @param array $concepts
     * @return self
     */
    public function setConcepts($concepts)
    {
        $this->concepts = $concepts;
        return $this;
    }

    /**
     * Get concepts
     *
     * @return array $concepts
     */
    public function getConcepts()
    {
        return $this->concepts;
    }

    /**
     * Set concept
     *
     * @param $index
     * @param $concept
     */
    public function setConcept($index, $concept)
    {
        $this->concepts[$index] = $concept;
    }

    /**
     * Set numericalDimensions
     *
     * @param array $numericalDimensions
     * @return self
     */
    public function setNumericalDimensions($numericalDimensions)
    {
        $this->numericalDimensions = $numericalDimensions;
        return $this;
    }

    /**
     * Get numericalDimensions
     *
     * @return array $numericalDimensions
     */
    public function getNumericalDimensions()
    {
        return $this->numericalDimensions;
    }

    /**
     * Add conceptFinderBookmark
     *
     * @param ConceptFinderBookmark $conceptFinderBookmark
     */
    public function addConceptFinderBookmark(ConceptFinderBookmark $conceptFinderBookmark)
    {
        $this->conceptFinderBookmarks[] = $conceptFinderBookmark;
    }

    /**
     * Remove conceptFinderBookmark
     *
     * @param ConceptFinderBookmark $conceptFinderBookmark
     */
    public function removeConceptFinderBookmark(ConceptFinderBookmark $conceptFinderBookmark)
    {
        $this->conceptFinderBookmarks->removeElement($conceptFinderBookmark);
    }

    /**
     * Get conceptFinderBookmarks
     *
     * @return \Doctrine\Common\Collections\Collection $conceptFinderBookmarks
     */
    public function getConceptFinderBookmarks()
    {
        return $this->conceptFinderBookmarks;
    }

    /**
     * Set temporalDimensions
     *
     * @param array $temporalDimensions
     * @return self
     */
    public function setTemporalDimensions($temporalDimensions)
    {
        $this->temporalDimensions = $temporalDimensions;
        return $this;
    }

    /**
     * Get temporalDimensions
     *
     * @return array $temporalDimensions
     */
    public function getTemporalDimensions()
    {
        return $this->temporalDimensions;
    }

    /**
     * Set description
     *
     * @param string $description
     * @return self
     */
    public function setDescription($description)
    {
        $this->description = $description;
        return $this;
    }

    /**
     * Get description
     *
     * @return string $description
     */
    public function getDescription()
    {
        return $this->description;
    }
}
