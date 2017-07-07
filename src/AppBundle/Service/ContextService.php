<?php

namespace AppBundle\Service;


use AppBundle\Document\Context;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Kernel;

class ContextService
{

    /**
     * @var Kernel
     */
    protected $kernel;

    /**
     * @var StatisticsService
     */
    protected $statisticsService;

    /**
     * @var string
     */
    protected $scriptDir;

    /**
     * @param $container ContainerInterface
     */
    public function __construct(ContainerInterface $container)
    {
        $this->kernel = $container->get('kernel');
        $this->statisticsService = $container->get("app.statistics_service");
        $this->scriptDir = $this->kernel->getRootDir() . "/../bin/fca/";
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

