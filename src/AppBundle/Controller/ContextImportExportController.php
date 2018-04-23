<?php

namespace AppBundle\Controller;

use AppBundle\Document\Context;
use AppBundle\Helper\CommonUtils;
use AppBundle\Parser\Exception\InvalidNumericDimensionException;
use AppBundle\Parser\Exception\InvalidTemporalDimensionException;
use AppBundle\Parser\FcaParser;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class ContextImportExportController
 * @package AppBundle\Controller
 */
class ContextImportExportController extends BaseController
{

    /**
     * @Security("has_role('ROLE_USER')")
     * @Route("/new-context", name="create_new_context")
     *
     * @param Request $request
     * @return Response
     */
    public function createNewContext(Request $request)
    {
        $this->startStatisticsCounter();

        $errors = array();

        if ($request->isMethod("POST")) {
            $em = $this->getManager();
            $params = $this->getParameter('fca');

            $postData = $request->request;
            $contextType = $postData->get("context_type", "dyadic");

            if (!in_array($contextType, array("dyadic", "triadic"))) {
                $errors["context_type"] = "Only dyadic and triadic context can be created using this page. "
                    . "For context with more dimensions please use the import method.";
            }
            if (!$postData->has("name") || $postData->get("name") == "") {
                $errors["name"] = "The name of the context cannot be empty.";
            }
            if (!$postData->has("description") || $postData->get("description") == "") {
                $errors["description"] = "The description of the context cannot be empty.";
            }
            if (!$postData->has("objects")) {
                $errors["objects"] = "The context must have at least one object.";
            }
            if (!$postData->has("attributes")) {
                $errors["attributes"] = "The context must have at least one attribute.";
            }
            if ($contextType == "triadic" && (!$postData->has("conditions") || $postData->get("conditions") == "")) {
                $errors["conditions"] = "The context must have at least one condition.";
            }

            if (empty($errors)) {
                $context = new Context();
                $context->setName($postData->get("name"));
                $context->setDescription($postData->get("description"));
                switch ($contextType) {
                    case "triadic":
                        $dimCount = 3;
                        break;
                    case "dyadic":
                    default:
                        $dimCount = 2;
                        break;
                }
                $context->setDimCount($dimCount);
                $context->setIsPublic(false);
                $context->setUser($this->getUser());

                $dimensions = array_slice($params['dimensionsPlural'], 0, $dimCount);

                for ($index = 0; $index < $context->getDimCount(); $index++) {
                    $paramName = $dimensions[$index];
                    $elements = $postData->get($paramName, array());
                    foreach ($elements as $elem) {
                        $context->addElement($index, CommonUtils::trim($elem));
                    }
                }

                foreach ($postData->get('relation_tuples', array()) as $tuple) {
                    $parts = explode("###", $tuple);
                    $relation = array();

                    for ($index = 0; $index < $dimCount; $index++) {
                        $elemName = CommonUtils::trim($parts[$index]);
                        $elemId = array_search($elemName, $context->getDimension($index));
                        $relation[] = $elemId;
                    }

                    $context->addRelation($relation);
                }

                $context->setContextFile(null);

                $fileName = uniqid() . ".cxt";
                $context->setContextFileName($fileName);

                $generateContextFilesService = $this->get("app.generate_context_files_service");
                $generateContextFilesService->generateContextFile($context);

                $em->persist($context);
                $em->flush();

                $this->stopCounterAndLogStatistics("create context", $context);

                return $this->redirect($this->generateUrl("view_context", array(
                    "id" => $context->getId(),
                )));
            }
        }

        return $this->render('@App/ContextImportExport/createNewContext.html.twig', array(
            'activeMenu' => "my_contexts",
            'errors' => $errors,
        ));
    }

    /**
     * @Security("has_role('ROLE_USER')")
     * @Route("/import-context", name="import_context")
     *
     * @param Request $request
     * @return Response
     */
    public function importContextAction(Request $request)
    {
        $this->startStatisticsCounter();

        $errors = array();

        if ($request->isMethod("POST")) {
            /** @var UploadedFile $uploadedFile */
            $uploadedFile = $request->files->get("file");
            $extension = null;

            if ($request->request->get("name") == null) {
                $errors['name'] = "The context name cannot be empty.";
            }
            if ($request->request->get("description") == null) {
                $errors['description'] = "The context description cannot be empty.";
            }

            if ($uploadedFile == null) {
                $errors['file'] = "The file is missing or an error occurred during it's upload.";
            } else if ($uploadedFile->getClientSize() > 10000000) {
                $errors['file'] = "The file size cannot exceed 10MB.";
            } else {
                $extension = $uploadedFile->getClientOriginalExtension();
                if (!in_array($extension, array("csv", "cxt"))) {
                    $errors['file'] = "Only 'csv' and 'cxt' files are currently supported.";
                } elseif ($extension == "cxt") {
                    $file = fopen($uploadedFile->getPathname(), "r");
                    $letter = fgetc($file);
                    fclose($file);

                    if ($letter != "B") {
                        $errors['file'] = "The 'cxt' file only supports dyadic contexts. Please use one of the other formats.";
                    }
                }
            }

            $dateFormat = $request->get("dateFormat", "Y/m/d");
            if ($request->request->get("temporalDimensions", "") != "" && ($dateFormat == null || $dateFormat == "")) {
                $errors['dateFormat'] = "If a temporal dimension is specified please fill in the date format field";
            }

            if (empty($errors)) {
                switch ($extension) {
                    case 'cxt':
                        $parser = $this->get("app.parser.cxt_parser");
                        break;
                    case 'csv':
                    default:
                        $parser = $this->get("app.parser.csv_parser");
                        break;
                }

                $numericalDimensions = array();
                if ($request->request->get("numericalDimensions", "") != "") {
                    $numericalDimensions = explode(",", $request->request->get("numericalDimensions", ""));
                    foreach ($numericalDimensions as $key => $value) {
                        $numericalDimensions[$key] = (int)CommonUtils::trim($value);
                    }
                }

                $temporalDimensions = array();
                if ($request->request->get("temporalDimensions", "") != "") {
                    $temporalDimensions = explode(",", $request->request->get("temporalDimensions", ""));
                    foreach ($temporalDimensions as $key => $value) {
                        $temporalDimensions[$key] = (int)CommonUtils::trim($value);
                    }
                }

                $context = null;
                try {
                    $context = $parser->parseContext($uploadedFile, $numericalDimensions, $temporalDimensions, $dateFormat);
                } catch (InvalidNumericDimensionException $exception) {
                    $errors["numericalDimensions"] = $exception->getMessage();
                } catch (InvalidTemporalDimensionException $exception) {
                    $errors["temporalDimensions"] = $exception->getMessage();
                }

                if (empty($errors)) {
                    $context->setName($request->request->get("name"));
                    $context->setDescription($request->request->get("description"));
                    $context->setUser($this->getUser());
                    $context->setIsPublic(false);
                    $context->setContextFile(null);

                    $fileName = uniqid() . ".cxt";
                    $context->setContextFileName($fileName);

                    $generateContextFilesService = $this->get("app.generate_context_files_service");
                    $generateContextFilesService->generateContextFile($context);

                    $em = $this->getManager();
                    $em->persist($context);
                    $em->flush();

                    $this->stopCounterAndLogStatistics("import context", $context, array(
                        'e' => $extension,
                    ));

                    return $this->redirect($this->generateUrl("view_context", array(
                        "id" => $context->getId(),
                    )));
                }
            }
        }

        return $this->render('@App/ContextImportExport/importContext.html.twig', array(
            'errors' => $errors,
            'activeMenu' => "my_contexts",
        ));
    }

    /**
     * @Route("/context/export/{id}.cxt", name="export_context_cxt")
     *
     * @param string $id
     * @return Response
     */
    public function exportContextCxtAction($id)
    {
        $this->startStatisticsCounter();

        /** @var Context $context */
        $context = $this->getRepo("AppBundle:Context")->find($id);

        if (!$this->isValidContext($context, array("not null", "can view"))) {
            return $this->renderFoundError("contexts");
        }

        $baseDir = $this->get("kernel")->getRootDir();
        $fileUrl = $baseDir . "/../" . $context->getContextFilePath();

        $this->stopCounterAndLogStatistics("export context", $context, array(
            'e' => "cxt",
        ));

        return new BinaryFileResponse($fileUrl, 200, array(
            "Content-Type" => "application/x-director"
        ));
    }

    /**
     * @Route("/context/export/{id}.csv", name="export_context_csv")
     *
     * @param string $id
     * @return Response
     */
    public function exportContextCsvAction($id)
    {
        $this->startStatisticsCounter();

        /** @var Context $context */
        $context = $this->getRepo("AppBundle:Context")->find($id);

        if (!$this->isValidContext($context, array("not null", "can view"))) {
            return $this->renderFoundError("contexts");
        }

        $contextService = $this->get("app.generate_context_files_service");
        $fileName = CommonUtils::generateTempFileName("csv");
        $filePath = "bin/temp/cron_delete/" . $fileName;

        $contextService->generateContextCsvFile($context, $filePath);

        $baseDir = $this->get("kernel")->getRootDir();
        $fileUrl = $baseDir . "/../" . $filePath;

        $this->stopCounterAndLogStatistics("export context", $context, array(
            'e' => "csv",
        ));

        return new BinaryFileResponse($fileUrl, 200, array(
            "Content-Type" => "application/x-director"
        ));
    }

    /**
     * @Route("/context/export/{id}.json", name="export_context_json")
     *
     * @param string $id
     * @return Response
     */
    public function exportContextJsonAction($id)
    {
        $this->startStatisticsCounter();

        /** @var Context $context */
        $context = $this->getRepo("AppBundle:Context")->find($id);

        if (!$this->isValidContext($context, array("not null", "can view"))) {
            return $this->renderFoundError("contexts");
        }

        $jsonContext = $this->getManager()->createQueryBuilder("AppBundle:Context")
            ->field('_id')->equals($id)
            ->hydrate(false)
            ->getQuery()
            ->getSingleResult();

        unset($jsonContext['_id'], $jsonContext['user']);

        $fileName = CommonUtils::generateTempFileName("json");
        $filePath = "bin/temp/cron_delete/" . $fileName;

        $rootDir = $this->get("kernel")->getRootDir();
        $fileUrl = $rootDir . "/../" . $filePath;
        file_put_contents($fileUrl, json_encode($jsonContext));

        $this->stopCounterAndLogStatistics("export context", $context, array(
            'e' => "json",
        ));

        return new BinaryFileResponse($fileUrl, 200, array(
            "Content-Type" => "application/x-director"
        ));
    }

}
