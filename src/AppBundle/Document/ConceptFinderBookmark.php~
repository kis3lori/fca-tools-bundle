<?php

namespace AppBundle\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;
use Symfony\Component\HttpFoundation\File\File;
use Vich\UploaderBundle\Mapping\Annotation as Vich;

/**
 * @MongoDB\Document
 * @Vich\Uploadable
 */
class ConceptFinderBookmark
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
     * @MongoDB\Hash
     */
    protected $searchContext;

    /**
     * @MongoDB\ReferenceOne(targetDocument="Context", inversedBy="conceptFinderBookmarks")
     */
    protected $context;

    /**
     * @MongoDB\ReferenceOne(targetDocument="User", inversedBy="conceptFinderBookmarks")
     */
    protected $user;

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
     * Set context
     *
     * @param Context $context
     * @return self
     */
    public function setContext(Context $context)
    {
        $this->context = $context;
        return $this;
    }

    /**
     * Get context
     *
     * @return Context $context
     */
    public function getContext()
    {
        return $this->context;
    }

    /**
     * Set searchContext
     *
     * @param array $searchContext
     * @return self
     */
    public function setSearchContext($searchContext)
    {
        $this->searchContext = $searchContext;
        return $this;
    }

    /**
     * Get searchContext
     *
     * @return array $searchContext
     */
    public function getSearchContext()
    {
        return $this->searchContext;
    }
}
