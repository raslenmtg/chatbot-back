<?php


namespace App\Controller;


use App\Service\ChatbotService;
use App\Service\ChatbotService_IT;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
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
        /**take message from whatsapp
         * $message = $request->get('message');
         * $answer = $chatbotService->typeofmessage($message);
         * return new Response($answer, 200);
         */

        $data = json_decode($request->getContent(), true);
        if ($data['messages'][0]['type'] == 'text') {
            $answer = $chatbotService->typeofmessage($data);
            return new Response($answer, 200);
        } else {

            return new Response('Désolé je n’ai pas saisi votre question. Pourriez vous m’indiquer si votre question correspond à l’une de nos FAQ ? 
-	Ou puis-je acheter un ticket ou recharger ma carte ? 
-	J’ai perdu un objet, comment le retrouver ? 
-	Comment puis-je déposer une réclamation/plainte ?
-	A quelle station dois-je descendre ? 
-	Quelle est la station la plus proche de moi ? 
-	Quelle est la meilleure route ? 
', 200);
        }

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


    /**
     * @Route("/api/getphones",name="chatbot_phones", methods={"POST"})
     */
    public function Getphones(ChatbotService $chatbotService)
    {
        $resp = $chatbotService->Getphones();
        return new JsonResponse($resp);
    }


    /**
     * @Route("/api/sendnotif",name="sendnotif",methods={"POST"})
     */
    public function sendnotif(Request $request, ChatbotService $chatbotService)
    {

        $response = $chatbotService->Sendnotif($request);
        if ($response)
            return new Response("success", 200);
        else
            return new Response("error", 500);

    }


}