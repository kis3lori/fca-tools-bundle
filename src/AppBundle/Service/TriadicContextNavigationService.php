<?php
 
namespace AppBundle\Service;


use AppBundle\Document\ConceptLattice;
use AppBundle\Document\Context;
use AppBundle\Helper\CommonUtils;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Kernel;
 
 class TriadicContextNavigationService extends ContextService{
 
	public function __construct(ContainerInterface $container)
    {
        $this->kernel = $container->get('kernel');
        $this->statisticsService = $container->get("app.statistics_service");
        $this->scriptDir = $this->kernel->getRootDir() . "/../bin/fca/";
        $this->generateConceptService = $container->get("app.generate_concept_service");
		$this->generateContextFilesService=$container->get("app.generate_context_files_service");
		$this->generateLatticeService = $container->get("app.generate_lattice_service");
        
    }
	
	/**
     * Generate a dyadic context from a triadic context based on a set of locked elements
     *
     * @param Context $context
     * @param string $lockType
     * @param array $lockedElements
     * @return Context
     */
	public function generateLockedContext($context, $lockType, $lockedElements)
    {	
        $dyadicContext = new Context(true);
        $dyadicContext->setDimCount(2);

        $perspective = $this->getPerspectiveByLockType($lockType);

        $dyadicContext->setDimension(0, $context->getDimension($perspective['other'][0]));
        $dyadicContext->setDimension(1, $context->getDimension($perspective['other'][1]));

        $relationCountArray = array();

        foreach ($context->getRelations() as $triadicRelation) {
            $relation = array();
            for ($index = 0; $index < $context->getDimCount(); $index++) {
                $relation[$index] = $context->getElement($index, $triadicRelation[$index]);
            }

            $element = $relation[$perspective['main']];

            if (in_array($element, $lockedElements)) {
                $key = $relation[$perspective['other'][0]] . "###" . $relation[$perspective['other'][1]];

                if (!isset($relationCountArray[$key])) {
                    $relationCountArray[$key] = 1;
                } else {
                    $relationCountArray[$key]++;
                }

            }
        }

        foreach ($relationCountArray as $key => $relationCount) {
            if ($relationCount == count($lockedElements)) {
                $parts = explode("###", $key);

                $objectName = CommonUtils::trim($parts[0]);
                $attributeName = CommonUtils::trim($parts[1]);

                $objectId = array_search($objectName, $dyadicContext->getDimension(0));
                $attributeId = array_search($attributeName, $dyadicContext->getDimension(1));

                $dyadicContext->addRelation(array($objectId, $attributeId));
            }
        }

        $fileName = $this->generateTempFileName("cxt");
        $dyadicContext->setContextFileName($fileName);
		
        $this->generateContextFilesService->generateContextFile($dyadicContext);

        if (!$this->canComputeConcepts($dyadicContext)) {
            return null;
        }
		
        $concepts = $this->generateConceptService->generateConcepts($dyadicContext);
        $dyadicContext->setConcepts($concepts);

        if (!$this->generateLatticeService->canComputeConceptLattice($dyadicContext)) {
            return null;
        }

        $conceptLattice = $this->generateLatticeService->generateConceptLattice($dyadicContext);
        $dyadicContext->setConceptLattice($conceptLattice);

        return $dyadicContext;
    }

    
    /**
     * Attach the concepts of the triadic context to the concept lattice of the dyadic context.
     * This dyadic context should have been generated from the original triadic context
     * based on a set of locked elements.
     *
     * @param array $parsedConceptLattice
     * @param Context $context
     * @return array
     */
    public function attachTriConcepts($parsedConceptLattice, $context)
    {
        foreach ($parsedConceptLattice['nodes'] as $key => $node) {
            if (isset($parsedConceptLattice['nodes'][$key]['triadicConceptId'])) {
                $conceptKey = $parsedConceptLattice['nodes'][$key]['triadicConceptId'];
                $concept = $context->getConcepts()[$conceptKey];
            } else if (isset($parsedConceptLattice['nodes'][$key]['triadicConcept'])) {
                $concept = $parsedConceptLattice['nodes'][$key]['triadicConcept'];
            } else {
                continue;
            }

            $triadicConcept = array();
            for ($index = 0; $index < 3; $index++) {
                $triadicConcept[$index] = array();

                foreach ($concept[$index] as $elemId) {
                    $triadicConcept[$index][] = $context->getElement($index, $elemId);
                }

                sort($triadicConcept[$index]);
            }

            $parsedConceptLattice['nodes'][$key]['triadicConcept'] = $triadicConcept;
        }

        return $parsedConceptLattice;
    }

    /**
     * Get the associated triadic context of a two dimensional context.
     * This is possible if both contexts have their concepts generated.
     *
     * @param Context $dyadicContext
     * @param Context $triadicContext
     * @param string $lockType
     * @return Context
     */
    public function computeAssociatedConcepts($dyadicContext, $triadicContext, $lockType)
    {
        $perspective = $this->getPerspectiveByLockType($lockType);

        foreach ($dyadicContext->getConcepts() as $index => $dyadicConcept) {
            foreach ($triadicContext->getConcepts() as $key => $triadicConcept) {
                $dimension1 = $triadicConcept[$perspective['other'][0]];
                $dimension2 = $triadicConcept[$perspective['other'][1]];

                if (count($dimension1) == count($dyadicConcept[0]) &&
                    count($dimension2) == count($dyadicConcept[1])
                ) {
                    $diff1 = array_diff($dimension1, $dyadicConcept[0]);
                    $diff2 = array_diff($dimension2, $dyadicConcept[1]);

                    if (empty($diff1) && empty($diff2)) {
                        $dyadicConcept['o'] = $key;
                        $dyadicContext->setConcept($index, $dyadicConcept);
                    }
                }
            }
        }

        return $dyadicContext;
    }

    /**
     * Generate the associated triadic context of a two dimensional context
     * This is the case if the parent triadic context does not have the list of concepts generated.
     *
     * @param Context $dyadicContext
     * @param Context $triadicContext
     * @param string $lockType
     * @return Context
     */
    public function generateAssociatedConcepts($dyadicContext, $triadicContext, $lockType)
    {
        $perspective = $this->getPerspectiveByLockType($lockType);

        foreach ($dyadicContext->getConcepts() as $conceptIndex => $concept) {
            $twoDimLockSize = count($concept[0]) * count($concept[1]);
            if ($twoDimLockSize == 0) {
                continue;
            }

            $elements = array();

            foreach ($triadicContext->getRelations() as $triadicRelation) {
                if (in_array($triadicRelation[$perspective["other"][0]], $concept[0]) &&
                    in_array($triadicRelation[$perspective["other"][1]], $concept[1])
                ) {
                    $key = $triadicRelation[$perspective["main"]];
                    if (key_exists($key, $elements)) {
                        $elements[$key]++;
                    } else {
                        $elements[$key] = 1;
                    }
                }
            }

            $validElements = array();
            foreach ($elements as $elementIndex => $nrApparitions) {
                if ($nrApparitions == $twoDimLockSize) {
                    $validElements[] = $triadicContext->getElement($perspective["main"], $elementIndex);
                }
            }

            $dimensions = array();
            for ($index = 0; $index < 2; $index++) {
                $dimensions[$index] = array();
                foreach ($concept[$index] as $elemId) {
                    $dimensions[$index][] = $dyadicContext->getElement($index, $elemId);
                }
            }

            $triadicConcept = array(
                $perspective['main'] => $validElements,
                $perspective['other'][0] => $dimensions[0],
                $perspective['other'][1] => $dimensions[1],
            );

            $concept["c"] = $triadicConcept;
            $dyadicContext->setConcept($conceptIndex, $concept);
        }

        return $dyadicContext;
    }

    /**
     * Compute the set of elements (objects, attributes, etc.) that are part of a concept.
     * These sets represent the lockable sets of a triadic context.
     *
     * @param Context $context
     * @return array
     */
    public function computeLockableElements($context)
    {
        $lockableElements = array();

        foreach ($context->getConcepts() as $concept) {
            for ($index = 0; $index < $context->getDimCount(); $index++) {
                $lock = array();

                foreach ($concept[$index] as $elemId) {
                    $lock[] = $context->getElement($index, $elemId);
                }

                sort($lock);
                $lockableElements[] = $lock;
            }
        }

        $lockableElements = array_map("unserialize", array_unique(array_map("serialize", $lockableElements)));

        return $lockableElements;
    }
	
	/**
     * Useful method for perspective locking which defines which is the main dimension on which
     * the locking is done and which are the secondary dimensions (the rest) based on the lock type.
     *
     * @param $lockType
     * @return array
     */
	private function getPerspectiveByLockType($lockType)
    {
        switch ($lockType) {
            case "object":
                return $this->getPerspective(0, 3);
            case "attribute":
                return $this->getPerspective(1, 3);
            case "condition":
            default:
                return $this->getPerspective(2, 3);
        }
    }

    private function getPerspective($dimKey, $dimCount)
    {
        return array(
            "main" => $dimKey,
            "other" => array_values(array_diff(range(0, $dimCount - 1, 1), array($dimKey))),
        );
    }
	
 }