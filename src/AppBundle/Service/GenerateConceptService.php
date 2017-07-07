<?php

namespace AppBundle\Service;


use AppBundle\Document\Context;
use AppBundle\Helper\CommonUtils;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Kernel;

class GenerateConceptService
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
     * @var GenerateContextFilesService
     */
    public $generateContextFilesService;

    /**
     * @param $container ContainerInterface
     */
    public function __construct(ContainerInterface $container)
    {
        $this->kernel = $container->get('kernel');
        $this->statisticsService = $container->get("app.statistics_service");
        $this->scriptDir = $this->kernel->getRootDir() . "/../bin/fca/";
        $this->generateContextFilesService = $container->get("app.generate_context_files_service");
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
        $dataFileName = $this->generateContextFilesService->generateTempFileName("cxt");
        $resultFileName = $this->generateContextFilesService->generateTempFileName("json");

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
        $dataFileName = $this->generateContextFilesService->generateTempFileName("cxt");
        $resultFileName = $this->generateContextFilesService->generateTempFileName("json");

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
        $dataFileName = $this->generateContextFilesService->generateTempFileName("csv");
        $resultFileName = $this->generateContextFilesService->generateTempFileName("txt");

        $dataRelativeFilePath = "bin/temp/generate_tri_concepts/input/" . $dataFileName;
        $dataFilePath = $this->kernel->getRootDir() . "/../" . $dataRelativeFilePath;
        $resultFilePath = $this->kernel->getRootDir() . "/../bin/temp/generate_tri_concepts/output/" . $resultFileName;
        file_put_contents($resultFilePath, "");

        // Generate the csv file of the context
        $this->generateContextFilesService->generateContextSimplifiedCsvFile($context, $dataRelativeFilePath);

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

}