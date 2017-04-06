<?php

namespace AppBundle\Parser;


use AppBundle\Document\Context;
use AppBundle\Helper\CommonUtils;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class CxtParser implements FcaParser
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
     * @return Context
     */
    public function parseContext($uploadedFile, $numericalDimensions, $temporalDimensions, $dateFormat = "Y/m/d")
    {
        $content = file_get_contents($uploadedFile->getPathname());
        $lines = explode("\n", $content);

        $nrObjects = (int)$lines[2];
        $nrAttributes = (int)$lines[3];

        $context = new Context();
        $context->setContextFile($uploadedFile);
        $context->setDimCount(2);

        $index = 4;
        if (empty(CommonUtils::trim($lines[$index]))) $index++;
        $end = $index + $nrObjects;

        $objects = array();

        for (; $index < $end; $index++) {
            $objects[] = CommonUtils::trim($lines[$index]);
        }

        $objectIndexMap = array_keys($objects);
        array_multisort($objects, $objectIndexMap);
        $objectIndexMap = array_flip($objectIndexMap);

        $context->setDimension(0, $objects);

        $end = $index + $nrAttributes;

        $attributes = array();

        for (; $index < $end; $index++) {
            $attributes[] = CommonUtils::trim($lines[$index]);
        }

        $attributeIndexMap = array_keys($attributes);
        array_multisort($attributes, $attributeIndexMap);
        $attributeIndexMap = array_flip($attributeIndexMap);

        $context->setDimension(1, $attributes);

        $start = $index;
        $end = $index + $nrObjects;

        for (; $index < $end; $index++) {
            for ($index2 = 0; $index2 < $nrAttributes; $index2++) {
                if ($lines[$index][$index2] == 'X') {
                    $pos = $index - $start;
                    $objectId = $objectIndexMap[$pos];
                    $attributeId = $attributeIndexMap[$index2];

                    $context->addRelation(array($objectId, $attributeId));
                }
            }
        }

        $dimensions = $context->getDimensions();
        foreach ($dimensions as $index => $dimension) {
            if (in_array($index, $numericalDimensions)) {
                foreach ($dimension as $key => $elem) {
                    $dimensions[$index][$key] = (int) $elem;
                }
            } else if (in_array($index, $temporalDimensions)) {
                foreach ($dimension as $key => $elem) {
                    $dimensions[$index][$key] = (new \DateTime($elem))->format($dateFormat);
                }
            }
        }

        $context->setDimensions($dimensions);
        $context->setNumericalDimensions($numericalDimensions);

        return $context;
    }

}
