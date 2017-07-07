<?php

namespace AppBundle\Service;


use AppBundle\Document\Context;

class ContextRestrictionValidationService
{

    const MAX_CONCEPTS = 500;
    const MAX_RELATIONS = 4000;

    /**
     * @param Context $context
     * @return bool
     */
    public function canComputeConcepts($context)
    {
        if (count($context->getRelations()) > self::MAX_RELATIONS) return false;

        return true;
    }

    /**
     * @param $context Context
     * @return bool
     */
    public function canComputeConceptLattice($context)
    {
        if (count($context->getConcepts()) > self::MAX_CONCEPTS) return false;

        if (!$this->canComputeConcepts($context)) return false;

        return true;
    }

}