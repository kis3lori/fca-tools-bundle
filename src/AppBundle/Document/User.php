<?php

namespace AppBundle\Document;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;
use Symfony\Component\Security\Core\User\UserInterface;
use Vich\UploaderBundle\Mapping\Annotation as Vich;

/**
 * @MongoDB\Document
 */
class User implements UserInterface, \Serializable
{

    /**
     * @MongoDB\Id
     */
    protected $id;

    /**
     * @MongoDB\Field(type="string")
     */
    protected $username;

    /**
     * @MongoDB\Field(type="string")
     */
    protected $password;

    /**
     * @MongoDB\Field(type="string")
     */
    protected $email;

    /**
     * @MongoDB\Field(type="string")
     */
    protected $firstName;

    /**
     * @MongoDB\Field(type="string")
     */
    protected $lastName;

    /**
     * @MongoDB\Field(type="bool")
     */
    protected $isActive;

    /**
     * @MongoDB\ReferenceMany(targetDocument="Context", mappedBy="user")
     */
    protected $contexts;

    /**
     * @MongoDB\ReferenceMany(targetDocument="ConceptFinderBookmark", mappedBy="user")
     */
    protected $conceptFinderBookmarks;

    /**
     * @MongoDB\ReferenceMany(targetDocument="Group", mappedBy="users")
     */
    protected $groups;

    /**
     * @MongoDB\ReferenceMany(targetDocument="Group", mappedBy="creator")
     */
    protected $groupsCreated;


    public function __construct()
    {
        $this->contexts = new ArrayCollection();
        $this->conceptFinderBookmarks = new ArrayCollection();
        $this->groupsCreated = new ArrayCollection();
    }

    public function getSalt()
    {
        return null;
    }

    public function getRoles()
    {
        return array('ROLE_USER');
    }

    public function eraseCredentials()
    {
    }

    public function serialize()
    {
        return serialize(array(
            $this->id,
            $this->username,
            $this->password,
        ));
    }

    public function unserialize($serialized)
    {
        list (
            $this->id,
            $this->username,
            $this->password,
            ) = unserialize($serialized);
    }

    /**
     * Set username
     *
     * @param string $username
     * @return self
     */
    public function setUsername($username)
    {
        $this->username = $username;
        return $this;
    }

    /**
     * Get username
     *
     * @return string $username
     */
    public function getUsername()
    {
        return $this->username;
    }

    /**
     * Set password
     *
     * @param string $password
     * @return self
     */
    public function setPassword($password)
    {
        $this->password = $password;
        return $this;
    }

    /**
     * Get password
     *
     * @return string $password
     */
    public function getPassword()
    {
        return $this->password;
    }

    /**
     * Set email
     *
     * @param string $email
     * @return self
     */
    public function setEmail($email)
    {
        $this->email = $email;
        return $this;
    }

    /**
     * Get email
     *
     * @return string $email
     */
    public function getEmail()
    {
        return $this->email;
    }

    /**
     * Set firstName
     *
     * @param string $firstName
     * @return self
     */
    public function setFirstName($firstName)
    {
        $this->firstName = $firstName;
        return $this;
    }

    /**
     * Get firstName
     *
     * @return string $firstName
     */
    public function getFirstName()
    {
        return $this->firstName;
    }

    /**
     * Set lastName
     *
     * @param string $lastName
     * @return self
     */
    public function setLastName($lastName)
    {
        $this->lastName = $lastName;
        return $this;
    }

    /**
     * Get lastName
     *
     * @return string $lastName
     */
    public function getLastName()
    {
        return $this->lastName;
    }

    /**
     * Set isActive
     *
     * @param bool $isActive
     * @return self
     */
    public function setIsActive($isActive)
    {
        $this->isActive = $isActive;
        return $this;
    }

    /**
     * Get isActive
     *
     * @return bool $isActive
     */
    public function getIsActive()
    {
        return $this->isActive;
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
     * Add context
     *
     * @param Context $context
     */
    public function addContext(Context $context)
    {
        $this->contexts[] = $context;
    }

    /**
     * Remove context
     *
     * @param Context $context
     */
    public function removeContext(Context $context)
    {
        $this->contexts->removeElement($context);
    }

    /**
     * Get contexts
     *
     * @return \Doctrine\Common\Collections\Collection $contexts
     */
    public function getContexts()
    {
        return $this->contexts;
    }


    /**
     * Get groups
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getGroups()
    {
        return $this->groups;
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


}
