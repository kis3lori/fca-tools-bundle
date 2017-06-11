<?php

namespace AppBundle\Service;


use AppBundle\Document\ConceptLattice;
use AppBundle\Document\Context;
use AppBundle\Helper\CommonUtils;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Kernel;

class ContextService
{
    const MAX_CONCEPTS = 500;
    const MAX_RELATIONS = 4000;

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
        $this->kernel = $container->get('kernel');
        $this->statisticsService = $container->get("app.statistics_service");
        $this->scriptDir = $this->kernel->getRootDir() . "/../bin/fca/";
    }

    /**
     * @param $context Context
     * @return array
     */
    public function generateConcepts($context)
    {
        $concepts = null;

        if ($context->getDimCount() == 2) {
            $concepts = $this->generateDyadicConcepts($context);
        } else if ($context->getDimCount() >= 3) {
            $concepts = $this->generateMultiDimensionalConcepts($context);
        }

        return $concepts;
    }

    /**
     * Generate the concepts of a dyadic context using the InClose4 algorithm.
     *
     * @param Context $context
     * @return array
     */
    public function generateDyadicConcepts($context)
    {
        $dataFileName = $this->generateTempFileName("cxt");
        $resultFileName = $this->generateTempFileName("json");

        $dataFilePath = $this->kernel->getRootDir() . "/../bin/temp/generate_concepts/input/" . $dataFileName;
        $resultFilePath = $this->kernel->getRootDir() . "/../bin/temp/generate_concepts/output/" . $resultFileName;
        file_put_contents($resultFilePath, "");

        $cxtFilePath = $this->kernel->getRootDir() . "/../" . $context->getContextFilePath();
        copy($cxtFilePath, $dataFilePath);

        // Execute the first script that generate the concepts
        $scriptPath = $this->scriptDir . "InClose4.exe " . $dataFilePath . " " . $resultFilePath;

        $this->statisticsService->startStatisticsCounter();
        exec("cd " . $this->scriptDir . " && " . $scriptPath);
        $this->statisticsService->stopCounterAndLogStatistics("generate dyadic concepts script", $context);

        $json = file_get_contents($resultFilePath);

        unlink($dataFilePath);
        unlink($resultFilePath);

        $result = json_decode($json, true);
        $dimensions = array("objects", "attributes");
        $concepts = array();

        // Parse concepts
        foreach ($result['Concepts'] as $conceptJson) {
            $concept = array();

            foreach ($dimensions as $index => $dimension) {
                $concept[$index] = array();
                foreach ($conceptJson[$dimension] as $elem) {
                    $concept[$index][] = (int)$elem;
                }

                sort($concept[$index]);
            }

            $concepts[] = $concept;
        }

        return $concepts;
    }

    /**
     * Generate the concepts of a triadic context.
     *
     * @param Context $context
     * @param string $alg The algorithm to use
     * @return array
     */
    public function generateTriadicConcepts($context, $alg = "trias")
    {
        switch ($alg) {
            case "trias":
                return $this->generateTriadicConceptsUsingTrias($context);
                break;
            case "data-peeler":
            default:
                return $this->generateTriadicConceptsUsingDataPeeler($context);
                break;
        }
    }

    /**
     * Generate the concepts of a multi-dimensional context
     *
     * @param Context $context
     * @return array
     */
    public function generateMultiDimensionalConcepts($context)
    {
        return $this->generateTriadicConceptsUsingDataPeeler($context);
    }

    /**
     * @param Context $context
     * @return array
     */
    public function generateTriadicConceptsUsingTrias($context)
    {
        $dataFileName = $this->generateTempFileName("cxt");
        $resultFileName = $this->generateTempFileName("json");

        $dataFilePath = $this->kernel->getRootDir() . "/../bin/temp/generate_tri_concepts/input/" . $dataFileName;
        $resultFilePath = $this->kernel->getRootDir() . "/../bin/temp/generate_tri_concepts/output/" . $resultFileName;
        file_put_contents($resultFilePath, "");

        // Copy the cxt file of the concept to the scripts working directory as "data.cxt"
        $cxtFilePath = $this->kernel->getRootDir() . "/../" . $context->getContextFilePath();
        copy($cxtFilePath, $dataFilePath);

        // Execute the first script that generate the concepts
        $script = "java -jar trias/trias-algorithm-0.0.1.jar " . $dataFilePath . " " . $resultFilePath;

        $this->statisticsService->startStatisticsCounter();
        exec("cd " . $this->scriptDir . " && " . $script);
        $this->statisticsService->stopCounterAndLogStatistics("generate triadic concepts script", $context);

        $json = file_get_contents($resultFilePath);

        unlink($dataFilePath);
        unlink($resultFilePath);

        // Remove the last "," from the string
        $json[strlen($json) - 4] = " ";

        $result = json_decode($json, true);
        $dimensions = array("objects", "attributes", "conditions");
        $concepts = array();

        // Parse concepts
        foreach ($result['Concepts'] as $conceptJson) {
            $concept = array();

            foreach ($dimensions as $index => $dimension) {
                $concept[$index] = array();
                foreach ($conceptJson[$dimension] as $elem) {
                    $concept[$index][] = ((int)$elem) - 1;
                }

                sort($concept[$index]);
            }

            $concepts[] = $concept;
        }

        return $concepts;
    }

    /**
     * @param Context $context
     * @return array
     */
    public function generateTriadicConceptsUsingDataPeeler($context)
    {
        $dataFileName = $this->generateTempFileName("csv");
        $resultFileName = $this->generateTempFileName("txt");

        $dataRelativeFilePath = "bin/temp/generate_tri_concepts/input/" . $dataFileName;
        $dataFilePath = $this->kernel->getRootDir() . "/../" . $dataRelativeFilePath;
        $resultFilePath = $this->kernel->getRootDir() . "/../bin/temp/generate_tri_concepts/output/" . $resultFileName;
        file_put_contents($resultFilePath, "");

        // Generate the csv file of the context
        $this->generateContextSimplifiedCsvFile($context, $dataRelativeFilePath);

        // Execute the first script that generate the concepts
        $script = "d-peeler " . $dataFilePath . " -o " . $resultFilePath . " --ids=\",\" --iis=\"#\" --ods=\"#\"";

        $this->statisticsService->startStatisticsCounter();
        exec("cd " . $this->scriptDir . " && " . $script);
        $this->statisticsService->stopCounterAndLogStatistics("generate triadic concepts script", $context);

        $data = file_get_contents($resultFilePath);

        unlink($dataFilePath);
        unlink($resultFilePath);

        $conceptsData = explode("\n", CommonUtils::trim($data));
        $concepts = array();

        // Parse concepts
        foreach ($conceptsData as $conceptData) {
            $dimensions = explode("#", CommonUtils::trim($conceptData));
            $concept = array();
            $hasEmptyDim = false;

            foreach ($dimensions as $index => $dimension) {
                $elements = explode(",", CommonUtils::trim($dimension));
                $concept[$index] = array();

                if (isset($elements[0]) && is_numeric($elements[0])) {
                    foreach ($elements as $elem) {
                        $concept[$index][] = (int)$elem;
                    }

                    sort($concept[$index]);
                }

                if (empty($concept[$index])) {
                    $hasEmptyDim = true;
                }
            }

            if ($hasEmptyDim) {
                foreach ($dimensions as $index => $dimension) {
                    if (!empty($concept[$index])) {
                        $concept[$index] = array_keys($context->getDimension($index));
                    }
                }
            }

            $concepts[] = $concept;
        }

        return $concepts;
    }

    /**
     * @param $context Context
     * @return ConceptLattice
     */
    public function generateConceptLattice($context)
    {
        $conceptLattice = null;

        if ($context->getDimCount() == 2) {
            $conceptLattice = $this->generateDyadicConceptLattice($context);
        }

        return $conceptLattice;
    }

    /**
     * @param $context Context
     * @return ConceptLattice
     */
    public function generateDyadicConceptLattice($context)
    {
        $conceptLattice = new ConceptLattice();

        $conceptsByNrAttr = array();
        $allConcepts = $context->getConcepts();

        foreach ($allConcepts as $conceptId => $concept) {
            $nrItems = count($concept[1]);

            if (!isset($conceptsByNrAttr[$nrItems])) {
                $conceptsByNrAttr[$nrItems] = array($conceptId);
            } else {
                $conceptsByNrAttr[$nrItems][] = $conceptId;
            }
        }

        ksort($conceptsByNrAttr);

        $levels = array();
        $links = array();
        $linkedConcepts = array();
        $gatheredConcepts = array();
        $level = 1;

        foreach ($conceptsByNrAttr as $nrAttributes => $concepts) {
            foreach ($concepts as $conceptId) {
                $levels[$conceptId] = $level;
                $linkedConcepts[$conceptId] = array($conceptId);
            }

            if ($level > 1) {
                foreach ($concepts as $conceptId) {
                    $conceptsToParse = $gatheredConcepts;
                    while (!empty($conceptsToParse)) {
                        $conceptAboveId = array_pop($conceptsToParse);
                        $diff1 = array_diff($allConcepts[$conceptId][0], $allConcepts[$conceptAboveId][0]);
                        $diff2 = array_diff($allConcepts[$conceptAboveId][1], $allConcepts[$conceptId][1]);
                        $areConceptsLinked = empty($diff1) && empty($diff2);

                        if ($conceptAboveId != $conceptId && $areConceptsLinked) {
                            $visitedNodes[] = $conceptAboveId;
                            $links[] = array($conceptAboveId, $conceptId);
                            $linkedConcepts[$conceptId] = array_merge($linkedConcepts[$conceptId], $linkedConcepts[$conceptAboveId]);
                            $conceptsToParse = array_diff($conceptsToParse, $linkedConcepts[$conceptAboveId]);
                        }
                    }
                }
            }

            $gatheredConcepts = array_merge($gatheredConcepts, $concepts);

            $level++;
        }

        $conceptLattice->setLinks($links);
        $conceptLattice->setLevels($levels);
        $conceptLattice->setMinLevel(1);
        $conceptLattice->setMaxLevel($level - 1);
        $conceptLattice->setMaxLevelConceptId(end($conceptsByNrAttr)[0]);

        return $conceptLattice;
    }

    /**
     * Generate a temporary file name with the given extension.
     *
     * @param String $extension
     * @return string
     */
    public function generateTempFileName($extension)
    {
        return uniqid("temp_") . "." . $extension;
    }

    /**
     * Generate and save the ".cxt" file of a context.
     *
     * @param $context Context
     */
    public function generateContextFile($context)
    {
        $data = "";

        $type = $context->getDimCount();
        if ($type == 2) {
            $type = "B";
        }

        $data .= $type . "\n\n";

        foreach ($context->getDimensions() as $dimension) {
            $data .= count($dimension) . "\n";
        }
        if ($type > 2) {
            $data .= count($context->getRelations()) . "\n";
        }
        $data .= "\n";

        foreach ($context->getDimensions() as $dimension) {
            $data .= implode("\n", $dimension) . "\n";
        }

        if ($context->getDimCount() == 2) {
            $matrix = array();
            foreach ($context->getDimension(0) as $key => $object) {
                $matrix[$key] = array();

                foreach ($context->getDimension(1) as $key2 => $attribute) {
                    $matrix[$key][$key2] = '.';
                }
            }

            foreach ($context->getRelations() as $relation) {
                $matrix[$relation[0]][$relation[1]] = 'X';
            }

            foreach ($context->getDimension(0) as $key => $object) {
                $matrix[$key] = implode("", $matrix[$key]);
            }

            $data .= implode("\n", $matrix) . "\n";
        } else {
            foreach ($context->getRelations() as $relation) {
                $elemIds = array();
                for ($index = 0; $index < $context->getDimCount(); $index++) {
                    $elemIds[] = $relation[$index] + 1;
                }

                $data .= implode(" ", $elemIds) . "\n";
            }
        }

        file_put_contents(
            $this->kernel->getRootDir() . "/../" . $context->getBaseFilePath() . $context->getContextFileName(),
            $data
        );
    }

    /**
     * Generate the ".csv" file of a context and save it in the given path.
     *
     * @param $context Context
     * @param $path
     */
    public function generateContextCsvFile($context, $path)
    {
        $data = "";

        foreach ($context->getRelations() as $relation) {
            $elementNames = array();
            foreach ($relation as $index => $elemId) {
                $elementNames[] = $context->getElement($index, $elemId);
            }

            $data .= implode(",", $elementNames) . "\n";
        }

        file_put_contents(
            $this->kernel->getRootDir() . "/../" . $path,
            $data
        );
    }

    /**
     * Generate a simplified version of the ".csv" file that is used by the data-peeler algorithm
     * and save it in the given path.
     *
     * @param $context Context
     * @param $path
     */
    public function generateContextSimplifiedCsvFile($context, $path)
    {
        $data = "";

        foreach ($context->getRelations() as $relation) {
            $data .= implode(",", $relation) . "\n";
        }

        file_put_contents(
            $this->kernel->getRootDir() . "/../" . $path,
            $data
        );
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
        $this->generateContextFile($dyadicContext);

        if (!$this->canComputeConcepts($dyadicContext)) {
            return null;
        }

        $concepts = $this->generateConcepts($dyadicContext);
        $dyadicContext->setConcepts($concepts);

        if (!$this->canComputeConceptLattice($dyadicContext)) {
            return null;
        }

        $conceptLattice = $this->generateConceptLattice($dyadicContext);
        $dyadicContext->setConceptLattice($conceptLattice);

        return $dyadicContext;
    }

    /**
     * Generate a child context by slicing the big context and only taking the relations tha contain a given dimension.
     *
     * @param Context $context
     * @param int $dimKey
     * @param string $dimItemKey
     * @return Context
     */
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
        $this->generateContextFile($childContext);

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

    /**
     * Generate the parsed concept lattice of a context.
     * This representation of the concept lattice is used by the javascript to display it.
     *
     * @param $context Context
     * @return array
     */
    public function generateParsedConceptLattice($context)
    {
        $parsedConceptLattice = array(
            'nodes' => array(),
            'links' => array(),
            'maxLevel' => $context->getConceptLattice()->getMaxLevel(),
            'lastNode' => $context->getConceptLattice()->getMaxLevelConceptId(),
        );

        foreach ($context->getConcepts() as $key => $concept) {
            $objects = array();
            $attributes = array();

            foreach ($concept[0] as $elemId) {
                $objects[] = $context->getElement(0, $elemId);
            }

            foreach ($concept[1] as $elemId) {
                $attributes[] = $context->getElement(1, $elemId);
            }

            sort($objects);
            sort($attributes);

            $nodeToAdd = array(
                'objects' => $objects,
                'attributes' => $attributes,
                'level' => $context->getConceptLattice()->getLevel($key),
            );

            if (isset($concept['o']) && $concept['o'] !== null) {
                $nodeToAdd['triadicConceptId'] = $concept['o'];
            } else if (isset($concept['c']) && $concept['c'] !== null && !empty($concept['c'])) {
                $nodeToAdd['triadicConcept'] = $concept['c'];
            }

            $parsedConceptLattice['nodes'][] = $nodeToAdd;
        }

        foreach ($context->getConceptLattice()->getLinks() as $link) {
            $parsedConceptLattice['links'][] = array(
                'source' => $link[0],
                'target' => $link[1],
            );
        }

        return $parsedConceptLattice;
    }

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

    /**
     * Find a concept using the ASP programming language.
     *
     * @param Context $context
     * @param array $constraints
     * @return array
     */
    public function findConcept($context, $constraints)
    {
        $lastIndex = $context->getDimCount() - 1;
        $aspProgram = "";
        foreach ($context->getRelations() as $relation) {
            $aspProgram .= "rel(" . implode(",", $relation) . ").\n";
        }

        $aspProgram .= "index(0 .. " . $lastIndex . ").\n";
        for ($index = 0; $index < $context->getDimCount(); $index++) {
            $aspProgram .= "set(" . $index . ", 0 .. " . (count($context->getDimension($index)) - 1) . ").\n";
        }

        $aspProgram .= "in(I,X):- set(I,X), index(I), not out(I,X).\n";
        $aspProgram .= "out(I,X):- set(I,X), index(I), not in(I,X).\n";

        $inParts = array();
        $xParts = array();
        for ($index = 0; $index < $context->getDimCount(); $index++) {
            $inParts[] = "in(" . $index . ",X" . $index . ")";
            $xParts[] = "X" . $index;
        }

        $relPart = "rel(" . implode(",", $xParts) . ")";
        $aspProgram .= ":- " . implode(", ", $inParts) . ", not " . $relPart . ".\n";

        for ($index = 0; $index < $context->getDimCount(); $index++) {
            $otherParts = array();

            for ($index2 = 0; $index2 < $context->getDimCount(); $index2++) {
                if ($index != $index2) {
                    $otherParts[] = "in(" . $index2 . ",X" . $index2 . ")";
                }
            }
            $aspProgram .= "exc(" . $index . ",X" . $index . "):- "
                . implode(", ", $otherParts)
                . ", not " . $relPart . ", set(" . $index . ",X" . $index . ").\n";
        }

        $aspProgram .= ":- out(I,X), index(I), not exc(I,X).\n";
        $aspProgram .= ":- out(I,X), index(I), in(I,X).\n";
        $aspProgram .= ":- I=0 .." . $lastIndex . ", #count {E,in : in(I,E)} <1.\n";

        $aspProgram .= "#show in/2.\n";
        $aspProgram .= "#show out/2.\n";

        foreach ($constraints as $constraint) {
            $aspProgram .= $constraint['state'] . "(" . $constraint['dimension'] . "," . $constraint['index'] . ").\n";
        }

        $dataFileName = $this->generateTempFileName("lp");
        $resultFileName = $this->generateTempFileName("txt");

        $dataFilePath = $this->kernel->getRootDir() . "/../bin/temp/find_triadic_concept/input/" . $dataFileName;
        $resultFilePath = $this->kernel->getRootDir() . "/../bin/temp/find_triadic_concept/output/" . $resultFileName;
        file_put_contents($resultFilePath, "");

        // Execute the ASP script
        file_put_contents($dataFilePath, $aspProgram);

        $command = "clingo " . $dataFilePath . " --enum-mode cautious --quiet=0,2,2 --verbose=0 > " . $resultFilePath;

        $dimensionsCount = array();
        foreach ($context->getDimensions() as $countKey => $dimension) {
            $dimensionsCount[$countKey] = count($dimension);
        }

        $this->statisticsService->startStatisticsCounter();
        exec("cd " . $this->scriptDir . " && " . $command . " 2>&1", $errorOutput);
        $this->statisticsService->stopCounterAndLogStatistics("find concept step script", $context, array(
            "cs" => $constraints,
        ));

        $result = file_get_contents($resultFilePath);

        unlink($dataFilePath);
        unlink($resultFilePath);

        $lines = explode("\n", CommonUtils::trim($result));
        $size = count($lines);
        $lastElement = null;
        $state = $lines[$size - 1];
        if (CommonUtils::trim($state) == "UNSATISFIABLE") {
            return null;
        } else {
            $lastElement = CommonUtils::trim($lines[$size - 2]);

            if ($lastElement) {
                $additionalConstraints = explode(" ", CommonUtils::trim($lastElement));

                foreach ($additionalConstraints as $constraint) {
                    $parts = explode("(", CommonUtils::trim($constraint));
                    $state = $parts[0];
                    $data = substr($parts[1], 0, strlen($parts[1]) - 1);

                    $parts = explode(",", $data);
                    $dimKey = (int)$parts[0];
                    $elemKey = (int)$parts[1];

                    $constraints[] = array(
                        'dimension' => $dimKey,
                        'index' => $elemKey,
                        'state' => $state,
                    );
                }
            }
        }

        $constraints = array_values(array_intersect_key($constraints, array_unique(array_map('serialize', $constraints))));

        return $constraints;
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
            "other" => array_diff(range(0, $dimCount - 1, 1), array($dimKey)),
        );
    }
}
