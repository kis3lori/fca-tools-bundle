<?php

namespace AppBundle\Controller;

use AppBundle\Document\Context;
use AppBundle\Document\DatabaseConnection;
use AppBundle\Document\Group;
use AppBundle\Document\User;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @Security("has_role('ROLE_USER')")
 *
 * Class DatabaseConnectionController
 * @package AppBundle\Controller
 */
class DatabaseConnectionController extends BaseController
{

    /**
     * @Route("/new-database-connection", name="create_new_database_connection")
     * @param Request $request
     * @return Response
     */
    public function createNewDatabaseConnectionAction(Request $request)
    {
        $user = $this->getUser();
        $errors = array();

        if ($request->isMethod("POST")) {
            $postData = $request->request;
            $name = $postData->get("name");
            if (!$name) {
                $errors["name"] = "The name of the database cannot be empty.";
            }

            $host = $postData->get("host");
            if (!$host) {
                $errors["host"] = "The database host cannot be empty.";
            }

            $port = $postData->get("port");
            if (!$port) {
                $errors["port"] = "The database port cannot be empty.";
            }

            $type = $postData->get("type");
            if (!$type) {
                $errors["type"] = "The database type cannot be empty.";
            } else if (!in_array($type, array("mysql", "mongodb"))) {
                $errors["type"] = "The given database type is not supported. The supported database types are mysql and mongodb.";
            }

            if (empty($errors)) {
                $databaseConnection = new DatabaseConnection();
                $databaseConnection->setName($name);
                $databaseConnection->setUsername($postData->get("user", ""));
                $databaseConnection->setPassword($postData->get("password", ""));
                $databaseConnection->setHost($host);
                $databaseConnection->setPort($port);
                $databaseConnection->setType($type);
                $databaseConnection->setUser($user);
                $user->addDatabaseConnection($databaseConnection);

                $em = $this->getManager();
                $em->persist($databaseConnection);
                $em->persist($user);
                $em->flush();

                return new JsonResponse(array(
                    "success" => true,
                    "data" => array(
                        "databaseConnection" => array(
                            "id" => $databaseConnection->getId(),
                            "name" => $databaseConnection->getName(),
                        )
                    )
                ));
            }
        }

        return $this->renderErrorAsJson($errors[0]);
    }

}
