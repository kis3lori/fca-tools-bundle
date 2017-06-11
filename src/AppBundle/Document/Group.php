<?php

namespace AppBundle\Document;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;

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
     * @MongoDB\ReferenceOne(targetDocument="User", inversedBy="groupsOwned")
     */
    protected $owner;

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
        $this->users = new ArrayCollection();
        $this->contexts = new ArrayCollection();
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
     * Set owner
     *
     * @param $owner
     * @return self
     */
    public function setOwner($owner)
    {
        $this->owner = $owner;
        return $this;
    }

    /**
     * Get owner
     *
     * @return $owner
     */
    public function getOwner()
    {
        return $this->owner;
    }

    /**
     * Add user
     *
     * @param $user
     */
    public function addUser($user)
    {
        $this->users[] = $user;
    }

    /**
     * Remove user
     *
     * @param $user
     */
    public function removeUser($user)
    {
        $this->users->removeElement($user);
    }

    /**
     * Get users
     *
     * @return Collection $users
     */
    public function getUsers()
    {
        return $this->users;
    }

    /**
     * Has user
     *
     * @param User $user
     * @return bool
     */
    public function hasUser(User $user)
    {
        return $this->users->contains($user);
    }

    /**
     * Add context
     *
     * @param $context
     */
    public function addContext($context)
    {
        $this->contexts[] = $context;
    }

    /**
     * Remove context
     *
     * @param $context
     */
    public function removeContext($context)
    {
        $this->contexts->removeElement($context);
    }

    /**
     * Get contexts
     *
     * @return Collection $contexts
     */
    public function getContexts()
    {
        return $this->contexts;
    }

    /**
     * Has contexts
     *
     * @param Context $context
     * @return bool
     */
    public function hasContext(Context $context)
    {
        return $this->contexts->contains($context);
    }
}
