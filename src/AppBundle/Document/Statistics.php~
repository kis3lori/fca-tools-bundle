<?php

namespace AppBundle\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;
use Symfony\Component\HttpFoundation\File\File;
use Vich\UploaderBundle\Mapping\Annotation as Vich;

/**
 * @MongoDB\Document
 */
class Statistics
{

    /**
     * @MongoDB\Id
     */
    protected $id;

    /**
     * @MongoDB\Field(type="int", name="o")
     */
    protected $operation;

    /**
     * @MongoDB\Field(type="float", name="t")
     */
    protected $duration;

    /**
     * @MongoDB\Hash(name="d")
     */
    protected $data;

    /**
     * @MongoDB\ReferenceOne(targetDocument="User", inversedBy="contexts", name="u")
     */
    protected $user;

    public function __construct()
    {
        $this->data = array();
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
     * Set operation
     *
     * @param int $operation
     * @return self
     */
    public function setOperation($operation)
    {
        $this->operation = $operation;
        return $this;
    }

    /**
     * Get operation
     *
     * @return int $operation
     */
    public function getOperation()
    {
        return $this->operation;
    }

    /**
     * Set duration
     *
     * @param float $duration
     * @return self
     */
    public function setDuration($duration)
    {
        $this->duration = $duration;
        return $this;
    }

    /**
     * Get duration
     *
     * @return float $duration
     */
    public function getDuration()
    {
        return $this->duration;
    }

    /**
     * Set data
     *
     * @param array $data
     * @return self
     */
    public function setData($data)
    {
        $this->data = $data;
        return $this;
    }

    /**
     * Get data
     *
     * @return array $data
     */
    public function getData()
    {
        return $this->data;
    }

}
