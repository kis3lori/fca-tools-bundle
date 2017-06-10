<?php

namespace AppBundle\Document;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;
use Symfony\Component\HttpFoundation\File\File;
use Vich\UploaderBundle\Mapping\Annotation as Vich;

/**
 * @MongoDB\Document
 */
class Group
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
     * @MongoDB\ReferenceOne(targetDocument="User", inversedBy="groupsCreated")
     */
    protected $creator;


    /**
     * @MongoDB\ReferenceMany(targetDocument="User", inversedBy="groups")
     */
    protected $users;

    /**
     * @MongoDB\ReferenceMany(targetDocument="Context", inversedBy="groups")
     */
    protected $contexts;

    /**
     * Group constructor.
     */
    public function __construct()
    {
        $this->users = array();
        $this->contexts = array();
    }

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param mixed $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * @return mixed
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param mixed $name
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * @return mixed
     */
    public function getUsers()
    {
        return $this->users;
    }

    /**
     * @param mixed $users
     */
    public function setUsers($users)
    {
        $this->users = $users;
    }

    public function addUser(User $user)
    {
        $this->users[] = $user;
        return $this;
    }

    public function removeContext(Context $context)
    {
        if (($key = array_search($context, $this->contexts->toArray())) !== false) {
            unset($this->contexts[$key]);
        }
    }

    public function addContext(Context $context)
    {
        $this->contexts[] = $context;
        return $this;
    }

    public function hasContext(Context $context)
    {
        return in_array($context, $this->contexts->toArray());
    }

    public function hasUser(User $user)
    {
        return in_array($user, $this->users->toArray());
    }

    public function numberOfUsers()
    {
        return sizeof($this->users->toArray());
    }

    /**
     * @return mixed
     */
    public function getCreator()
    {
        return $this->creator;
    }

    /**
     * @param $creator
     */
    public function setCreator(User $creator)
    {
        $this->creator = $creator;
        return $this;
    }

}
