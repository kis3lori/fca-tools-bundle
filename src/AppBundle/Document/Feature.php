<?php

namespace AppBundle\Document;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;

/**
 * @MongoDB\Document
 */
class Feature
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
     * @MongoDB\Field(type="boolean", nullable=true)
     */
    protected $approved;

    /**
     * @MongoDB\ReferenceOne(targetDocument="User", inversedBy="proposedFeatures")
     */
    protected $user;

    /**
     * @MongoDB\ReferenceMany(targetDocument="Vote", mappedBy="feature")
     */
    protected $votes;

    public function __construct()
    {
        $this->votes = new ArrayCollection();
        $this->approved = null;
    }

    /**
     * @return bool
     */
    public function isApprovedFeature() {
        return $this->approved === true;
    }

    /**
     * @return bool
     */
    public function isRejectedFeature() {
        return $this->approved === false;
    }

    /**
     * @return bool
     */
    public function isNonValidatedFeature() {
        return $this->approved === null;
    }

    /**
     * Get id
     *
     * @return int $id
     */
    public function getId()
    {
        return $this->id;
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

    /**
     * Set approved
     *
     * @param boolean $approved
     * @return self
     */
    public function setApproved($approved)
    {
        $this->approved = $approved;
        return $this;
    }

    /**
     * Get approved
     *
     * @return boolean $approved
     */
    public function getApproved()
    {
        return $this->approved;
    }

    /**
     * Add vote
     *
     * @param $vote
     */
    public function addVote($vote)
    {
        $this->votes[] = $vote;
    }

    /**
     * Remove vote
     *
     * @param $vote
     */
    public function removeVote($vote)
    {
        $this->votes->removeElement($vote);
    }

    /**
     * Get votes
     *
     * @return \Doctrine\Common\Collections\Collection $votes
     */
    public function getVotes()
    {
        return $this->votes;
    }

    /**
     * Set user
     *
     * @param $user
     * @return self
     */
    public function setUser($user)
    {
        $this->user = $user;
        return $this;
    }

    /**
     * Get user
     *
     * @return $user
     */
    public function getUser()
    {
        return $this->user;
    }

}
