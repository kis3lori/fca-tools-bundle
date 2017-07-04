<?php

namespace AppBundle\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;

/**
 * @MongoDB\Document
 */
class Vote
{
    /**
     * @MongoDB\Id
     */
    protected $id;

    /**
     * @MongoDB\ReferenceOne(targetDocument="User", inversedBy="votes")
     */
    protected $user;

    /**
     * @MongoDB\ReferenceOne(targetDocument="Feature", inversedBy="votes")
     */
    protected $feature;
    
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
     * @return User $user
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * Set feature
     *
     * @param $feature
     * @return self
     */
    public function setFeature($feature)
    {
        $this->feature = $feature;
        return $this;
    }

    /**
     * Get feature
     *
     * @return $feature
     */
    public function getFeature()
    {
        return $this->feature;
    }
}
