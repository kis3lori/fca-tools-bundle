<?php

namespace AppBundle\Service;


use AppBundle\Document\Context;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Kernel;

class ContextService
{

    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * @var Kernel
     */
    private $kernel;

    /**
     * @var StatisticsService
     */
    private $statisticsService;

    /**
     * @var string
     */
    private $scriptDir;

    /**
     * @param $container ContainerInterface
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->kernel = $container->get('kernel');
        $this->statisticsService = $container->get("app.statistics_service");
        $this->scriptDir = $this->kernel->getRootDir() . "/../bin/fca/";
    }

    /**
     * Generate the concepts and the concept lattice of a context.
     * It also create the cxt file for it in the process.
     *
     * @param $context Context
     * @param $fileName string
     * @param $errors array
     * @return array
     */
    public function computeConceptsAndConceptLattice($context, $fileName, $errors) {
        $context->setContextFile(null);
        $context->setContextFileName($fileName);

        $contextFileService = $this->container->get("app.generate_context_files_service");
        $contextFileService->generateContextFile($context);

        $contextRestrictionValidationService = $this->container->get("app.context_restriction_validation_service");
        if (!$contextRestrictionValidationService->canComputeConcepts($context)) {
            $errors["context"] = "The context is too big to compute its concepts.";
        } else {
            $generateConceptsService = $this->container->get("app.generate_concept_service");
            $concepts = $generateConceptsService->generateConcepts($context);
            $context->setConcepts($concepts);

            if (!$contextRestrictionValidationService->canComputeConceptLattice($context)) {
                $errors["context"] = "The context is too big to compute its concept lattice.";
            } else {
                $generateLatticeService = $this->container->get("app.generate_lattice_service");
                $conceptLattice = $generateLatticeService->generateConceptLattice($context);
                $context->setConceptLattice($conceptLattice);
            }
        }

        return $errors;
    }

    /*
    /**
     * Generate a child context by slicing the big context and only taking the relations that contain a given dimension.
     *
     * @param Context $context
     * @param int $dimKey
     * @param string $dimItemKey
     * @return Context
     * /
    public function generateChildContext($context, $dimKey, $dimItemKey)
    {
        $childContext = new Context(true);
        $childContext->setDimCount($context->getDimCount() - 1);

        $perspective = $this->getPerspective($dimKey, $context->getDimCount());

        foreach ($perspective['other'] as $otherDimKey) {
            $childContext->setDimension($otherDimKey, $context->getDimension($perspective['other'][$otherDimKey]));
        }

        foreach ($context->getRelations() as $relation) {
            if ($relation[$dimKey] == $dimItemKey) {
                unset($relation[$dimKey]);
                sort($relation);
                $childContext->addRelation($relation);
            }
        }

        $childContext->setRelations(array_map("unserialize", array_unique(array_map("serialize", $childContext->getRelations()))));

        $fileName = $this->generateTempFileName("cxt");
        $childContext->setContextFileName($fileName);
        $this->generateContextFilesService->generateContextFile($childContext);

        if (!$this->canComputeConcepts($childContext)) {
            return null;
        }

        $concepts = $this->generateConcepts($childContext);
        $childContext->setConcepts($concepts);

        if (!$this->canComputeConceptLattice($childContext)) {
            return null;
        }

        $conceptLattice = $this->generateConceptLattice($childContext);
        $childContext->setConceptLattice($conceptLattice);

        return $childContext;
    }
    */

    /**
     * @param $context Context
     * @return array
     */
    public function stringifyContext($context)
    {
        $contextData = array(
            'nodes' => array(),
            'links' => $context->getConceptLattice()->getLinks(),
            'maxLevel' => $context->getConceptLattice()->getMaxLevel(),
            'lastNode' => $context->getConceptLattice()->getMaxLevelConceptId(),
        );

        foreach ($context->getConcepts() as $key => $concept) {
            $contextData['nodes'][] = array(
                'value' => $concept,
                'level' => $context->getConceptLattice()->getLevel($key),
            );
        }

        return $contextData;
    }

    /**
     * @param $context Context
     * @param $attributes array
     * @return array
     */
    public function computeExtent($context, $attributes)
    {
        $objectsCount = array();

        foreach ($context->getRelations() as $relation) {
            if (in_array($relation[1], $attributes)) {
                if (isset($objectsCount[$relation[0]])) {
                    $objectsCount[$relation[0]]++;
                } else {
                    $objectsCount[$relation[0]] = 1;
                }
            }
        }

        $objects = array();
        $attributesCount = count($attributes);
        foreach ($objectsCount as $object => $count) {
            if ($count == $attributesCount) {
                $objects[] = $object;
            }
        }

        $objects = array_unique($objects);
        sort($objects);

        return $objects;
    }

    /**
     * @param $context Context
     * @param $objects array
     * @return array
     */
    public function computeIntent($context, $objects)
    {
        $attributesCount = array();

        foreach ($context->getRelations() as $relation) {
            if (in_array($relation[0], $objects)) {
                if (isset($attributesCount[$relation[1]])) {
                    $attributesCount[$relation[1]]++;
                } else {
                    $attributesCount[$relation[1]] = 1;
                }
            }
        }

        $attributes = array();
        $objectsCount = count($objects);
        foreach ($attributesCount as $attribute => $count) {
            if ($count == $objectsCount) {
                $attributes[] = $attribute;
            }
        }

        $attributes = array_unique($attributes);
        sort($attributes);

        return $attributes;
    }

}

