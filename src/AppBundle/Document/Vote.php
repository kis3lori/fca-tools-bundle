<?php

namespace AppBundle\Document;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
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

//    /**
//     * @MongoDB\ReferenceMany(targetDocument="User")
//     */
    /**
     * @MongoDB\Field(type="string")
     */
    protected $user;
//
//    /**
//     * @MongoDB\ReferenceMany(targetDocument="Feature")
//     */
    /**
     * @MongoDB\Field(type="string")
     */
    protected $feature;

    public function __construct()
    {
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
    public function getUser()
    {
        return $this->user;
    }

    /**
     * @param mixed $user
     */
    public function setUser($user)
    {
        $this->user = $user;
    }

    /**
     * @return mixed
     */
    public function getFeature()
    {
        return $this->feature;
    }

    /**
     * @param mixed $feature
     */
    public function setFeature($feature)
    {
        $this->feature = $feature;
    }
}