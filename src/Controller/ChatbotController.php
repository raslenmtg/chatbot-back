<?php


namespace App\Controller;


use App\Service\ChatbotService;
use App\Service\ChatbotService_IT;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
class ChatbotController extends AbstractController
{


    /**
     * @Route("/chatbot/fr",name="chatbot", methods={"POST"})
     */
    public function ChatbotService(Request $request, ChatbotService $chatbotService)
    {
        //take message from whatsapp
        $message = $request->get('message');
        $answer = $chatbotService->typeofmessage($message);
        return new Response($answer, 200);

    }

    /**
     * @Route("/chatbot/it",name="chatbot_it", methods={"POST"})
     */
    public function ChatbotService_it(Request $request, ChatbotService_IT $chatbotService)
    {
        //take message from whatsapp
        $message = $request->get('message');
        $answer = $chatbotService->typeofmessage_it($message);
        return new Response($answer, 200);

    }



}