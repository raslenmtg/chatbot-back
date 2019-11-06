<?php


namespace App\Controller;

use App\Service\ChatbotService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Serializer\Exception\UnexpectedValueException;
use App\Service\UserService;
use App\Entity\User;

class AuthentificationController extends AbstractController
{
    const C_TOKEN_FAILURE = 'Failed to get JWT Token :';

    /**
     * @Route("/api/login_check",name="login", methods={"POST"})
     */
    public function login_check()
    {}


    /**
     * @Route("/api/chatbot",name="chatbot", methods={"POST"})
     */
    public function ChatbotService(Request $request, ChatbotService $chatbotService)
    {
        //take message from whatsapp
        $message = $request->get('message');
        $answer = $chatbotService->typeofmessage($message);
        return new Response($answer, 200);

    }


}
