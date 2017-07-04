<?php

namespace AppBundle\Document;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;
use Symfony\Component\Security\Core\User\UserInterface;

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
    protected $groupsOwned;

    /**
     * @MongoDB\ReferenceMany(targetDocument="Feature", mappedBy="user")
     */
    protected $proposedFeatures;

    /**
     * @MongoDB\ReferenceMany(targetDocument="Vote", mappedBy="user")
     */
    protected $votes;

    public function __construct()
    {
        $this->contexts = new ArrayCollection();
        $this->conceptFinderBookmarks = new ArrayCollection();
        $this->groupsOwned = new ArrayCollection();
        $this->proposedFeatures = new ArrayCollection();
        $this->groups = new ArrayCollection();
        $this->votes = new ArrayCollection();
    }

    /**
     * @return bool
     */
    public function isAdmin() {
        return in_array("ROLE_ADMIN", $this->getRoles());
    }

    public function getSalt()
    {
        return null;
    }

    /**
     * @return array
     */
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
     * Get full name
     *
     * @return string
     */
    public function getFullName()
    {
        return $this->firstName . " " . $this->lastName;
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
     * @return Collection $contexts
     */
    public function getContexts()
    {
        return $this->contexts;
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
     * @return Collection $conceptFinderBookmarks
     */
    public function getConceptFinderBookmarks()
    {
        return $this->conceptFinderBookmarks;
    }

    /**
     * Add group
     *
     * @param Group $group
     */
    public function addGroup(Group $group)
    {
        $this->groups[] = $group;
    }

    /**
     * Remove group
     *
     * @param Group $group
     */
    public function removeGroup(Group $group)
    {
        $this->groups->removeElement($group);
    }

    /**
     * Get groups
     *
     * @return Collection $groups
     */
    public function getGroups()
    {
        return $this->groups;
    }

    /**
     * Add groupsOwned
     *
     * @param Group $groupsOwned
     */
    public function addGroupsOwned(Group $groupsOwned)
    {
        $this->groupsOwned[] = $groupsOwned;
    }

    /**
     * Remove groupsOwned
     *
     * @param Group $groupsOwned
     */
    public function removeGroupsOwned(Group $groupsOwned)
    {
        $this->groupsOwned->removeElement($groupsOwned);
    }

    /**
     * Get groupsOwned
     *
     * @return Collection $groupsOwned
     */
    public function getGroupsOwned()
    {
        return $this->groupsOwned;
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
     * @return Collection $votes
     */
    public function getVotes()
    {
        return $this->votes;
    }

    /**
     * Add proposedFeature
     *
     * @param $proposedFeature
     */
    public function addProposedFeature($proposedFeature)
    {
        $this->proposedFeatures[] = $proposedFeature;
    }

    /**
     * Remove proposedFeature
     *
     * @param $proposedFeature
     */
    public function removeProposedFeature($proposedFeature)
    {
        $this->proposedFeatures->removeElement($proposedFeature);
    }

    /**
     * Get proposedFeatures
     *
     * @return Collection $proposedFeatures
     */
    public function getProposedFeatures()
    {
        return $this->proposedFeatures;
    }
}
