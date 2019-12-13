<?php


namespace App\Controller;


use App\Service\ChatbotService;
use App\Service\ChatbotService_IT;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;


class ChatbotController extends AbstractController
{
    private $session;

    public function __construct(SessionInterface $session)
    {
        $this->session = $session;
    }

    /**
     * @Route("/chatbot/ma",name="chatbot", methods={"POST"})
     */
    public function ChatbotService(Request $request, ChatbotService $chatbotService)
    {

        //text message you received from a customer(from whatsapp serve) via webhook call
        $data = json_decode($request->getContent(), true);
        if ($data['messages'][0]['type'] == 'text') {
            $answer = $chatbotService->typeofmessage($data);
        } else
            $answer = 'Désolé je n’ai pas saisi votre question. Pourriez vous m’indiquer si votre question correspond à l’une de nos FAQ ? 
-	Ou puis-je acheter un ticket ou recharger ma carte ? 
-	J’ai perdu un objet, comment le retrouver ? 
-	Comment puis-je déposer une réclamation ?
-	A quelle station dois-je descendre ? 
-	Quelle est la station la plus proche de moi ? 
-	Comment puis-je souscrire à un abonnement ? ';

        $response = array("preview_url" => true, "recipient_type" => "individual", "to" => $data['messages'][0]['from'], "type" => "text", "text" => array("body" => $answer));
        /*
         *
         *
         */
        // uncomment for test the response that will send to customer
         return $this->json($response,200,array(),array());
        /*
         *
         *
         */
        $client = HttpClient::create();
        try {
            /// check token expiration
            $datetime1 = new \DateTime();
            $datetime2 = new \DateTime($this->session->get('date'));
            $interval = $datetime1->diff($datetime2);
            if (!($this->session->has('token') && $interval->d < 7)) {
                $r = $client->request('POST', $_ENV['URL_WA_SERVER'] . '/v1/users/login', ['body' => '{}', 'headers' => ['Authorization' => 'basic base64(' . $_ENV['WA_LOGIN'] . ')', 'Content-Type' => 'application/json']]);
                $t = $r->toArray();
                $this->session->set('token', $t['users'][0]['token']);
                $this->session->set('date', $datetime1->format('Y-m-d'));
            }
            //send message to customers
            $rsp = $client->request('POST', $_ENV['URL_WA_SERVER'] . '/v1/messages', ['body' => json_encode($response), 'headers' => ['Authorization' => 'Bearer ' . $this->session->get('token'), 'Content-Type' => 'application/json']]);
            //$rsp == message id
            return Response::create('', $rsp->getStatusCode(), array());
            //return to webhook call
        } catch (\Exception $e) {
            return Response::create('', $rsp->getStatusCode(), array());
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
    public function Sendnotif(Request $request, ChatbotService $chatbotService)
    {

        $response = $chatbotService->Sendnotif($request);
        if ($response)
            return new Response("success", 200);
        else
            return new Response("error", 500);

    }

    /**
     * @Route("/api/getPlaces",name="getPlaces",methods={"POST"})
     */
    public function GetPlaces(Request $request, ChatbotService $chatbotService)
    {

        $resp = $chatbotService->GetPlaces();
        return new JsonResponse($resp);

    }


}