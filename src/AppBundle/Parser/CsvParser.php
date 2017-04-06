<?php

namespace AppBundle\Parser;


use AppBundle\Document\Context;
use AppBundle\Helper\CommonUtils;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class CsvParser implements FcaParser
{

    /**
     * @var Container
     */
    private $container;

    /**
     * @param $container ContainerInterface
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * @param UploadedFile $uploadedFile
     * @param array $numericalDimensions
     * @param array $temporalDimensions
     * @param string $dateFormat
     * @return Context
     */
    public function parseContext($uploadedFile, $numericalDimensions, $temporalDimensions, $dateFormat = "Y/m/d")
    {
        $content = file_get_contents($uploadedFile->getPathname());
        $lines = explode("\n", $content);
        $dimCount = count(explode(",", $lines[0]));

        $context = new Context();
        $context->setContextFile($uploadedFile);
        $context->setDimCount($dimCount);

        $dimensions = array();
        for ($index = 0; $index < $dimCount; $index++) {
            $dimensions[$index] = array();
        }

        foreach ($lines as $line) {
            $elements = explode(",", $line);

            if (count($elements) != $dimCount) continue;

            foreach ($elements as $index => $elem) {
                $dimensions[$index][] = CommonUtils::trim($elem);
            }
        }

        for ($index = 0; $index < $dimCount; $index++) {
            $dimensions[$index] = array_unique($dimensions[$index]);
            sort($dimensions[$index]);
            $context->setDimension($index, $dimensions[$index]);
        }

        foreach ($lines as $line) {
            $elements = explode(",", $line);

            if (count($elements) != $dimCount) continue;

            $relation = array();
            foreach ($elements as $index => $elem) {
                $elemName = CommonUtils::trim($elem);
                $elemId = array_search($elemName, $context->getDimension($index));
                $relation[] = $elemId;
            }

            $context->addRelation($relation);
        }

        $dimensions = $context->getDimensions();
        foreach ($dimensions as $index => $dimension) {
            if (in_array($index, $numericalDimensions)) {
                foreach ($dimension as $key => $elem) {
                    $dimensions[$index][$key] = (int) $elem;
                }
            }
        }

        $context->setDimensions($dimensions);
        $context->setNumericalDimensions($numericalDimensions);
        $context->setTemporalDimensions($temporalDimensions);

        return $context;
    }

}
