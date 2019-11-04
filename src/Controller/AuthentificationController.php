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
     * @Route("/login",name="login", methods={"POST"})
     */
    public function Login(Request $i__request, UserService $i__userService, UserPasswordEncoderInterface $i__encoder)
    {
        try {
            /*
            * Get Request body content (username, password, device)
            */
                $l__username = $i__request->get("username");
            $l__password = $i__request->get("password");
            $l__isValid = $i__userService->toCheckCredentials($i__encoder, $l__username, $l__password);
            if ($l__isValid =='invalid') {
                //Bad Credentials
                $l__response = array('authenticated' => 0);
            } else {
                //Correct credentials
                $l__user = $l__isValid;
                $l__token = $i__userService->toGenerateToken($l__user);
                $l__response = array('token' => $l__token, 'username' => $l__user->getUsername(), 'authenticated' => 1);
            }
        } catch (Exception $e) {
            throw new UnexpectedValueException(AuthentificationController::C_TOKEN_FAILURE . $e->getMessage());
        }
        return new JsonResponse($l__response);
    }


    /**
     * @Route("/chatbot",name="chatbot", methods={"POST"})
     */
    public function ChatbotService(Request $request, ChatbotService $chatbotService)
    {
        //take message from whatsapp
        $message = $request->get('message');
        $answer = $chatbotService->typeofmessage($message);
        return new Response($answer, 200);

    }


}
