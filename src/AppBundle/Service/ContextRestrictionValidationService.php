<?php
namespace AppBundle\Service;


use AppBundle\Document\ConceptLattice;
use AppBundle\Document\Context;
use AppBundle\Helper\CommonUtils;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Kernel;

class ContextRestrictionValidationService extends ContextService {
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