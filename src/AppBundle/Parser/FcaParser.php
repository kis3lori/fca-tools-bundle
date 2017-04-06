<?php

namespace AppBundle\Parser;


interface FcaParser
{

    function parseContext($uploadedFile, $numericalDimensions, $temporalDimensions, $dateFormat);

}
