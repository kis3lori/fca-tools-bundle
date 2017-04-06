<?php

namespace AppBundle\Controller;

use AppBundle\Document\User;
use AppBundle\Form\RegistrationType;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Encoder\BCryptPasswordEncoder;

class SecurityController extends BaseController
{

    /**
     * @Route("/login", name="login")
     *
     * @param Request $request
     * @return Response
     */
    public function loginAction(Request $request)
    {
        $authenticationUtils = $this->get('security.authentication_utils');

        // get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();

        // last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render(
            '@App/Security/login.html.twig',
            array(
                // last username entered by the user
                'last_username' => $lastUsername,
                'error' => $error,
            )
        );
    }

    /**
     * @Route("/register", name="register")
     *
     * @param Request $request
     * @return Response
     */
    public function registerAction(Request $request)
    {
        $user = new User();
        $em = $this->getManager();

        $form = $this->createForm(new RegistrationType(), $user);

        if ($request->isMethod("post")) {
            $form->handleRequest($request);

            if ($form->isValid()) {
                $factory = $this->get('security.encoder_factory');
                /** @var BCryptPasswordEncoder $encoder */
                $encoder = $factory->getEncoder($user);
                $password = $encoder->encodePassword($user->getPassword(), $user->getSalt());
                $user->setPassword($password);
                $user->setIsActive(true);

                $em->persist($user);
                $em->flush();

                return $this->redirect($this->generateUrl("login"));
            }
        }

        return $this->render("@App/Security/register.html.twig", array(
            'form' => $form->createView(),
        ));
    }

}