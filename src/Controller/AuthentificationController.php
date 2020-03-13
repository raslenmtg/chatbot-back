<?php


namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;

class AuthentificationController extends AbstractController
{
    const C_TOKEN_FAILURE = 'Failed to get JWT Token :';

    /**
     * @Route("/api/login_check",name="login", methods={"POST"})
     */
    public function login_check()
    {}




}
