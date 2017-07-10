<?php

namespace AppBundle\Parser;


use AppBundle\Document\Context;
use AppBundle\Parser\Exception\InvalidNumericDimensionException;
use AppBundle\Parser\Exception\InvalidTemporalDimensionException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

interface FcaParser
{
    /**
     * @param UploadedFile $uploadedFile
     * @param array $numericalDimensions
     * @param array $temporalDimensions
     * @param string $dateFormat
     * @return Context
     * @throws InvalidNumericDimensionException
     * @throws InvalidTemporalDimensionException
     */
    function parseContext($uploadedFile, $numericalDimensions, $temporalDimensions, $dateFormat);

}
