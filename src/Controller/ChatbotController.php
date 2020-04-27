<?php


namespace App\Controller;


use App\Service\ChatbotService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;
use Twilio\TwiML\MessagingResponse;

class ChatbotController extends AbstractController
{

    public function __construct()
    {

    }

    /**
     * Permet de répondre à les messages recu d'aprés twilio
     * @Route("/chatbot/ma",name="chatbot", methods={"POST"})
     * @param Request $request
     * @param ChatbotService $chatbotService
     * @return Response
     */
    public function ChatbotService(Request $request, ChatbotService $chatbotService): Response
    {

        $response = new MessagingResponse();
        $data = array('message' => $request->get('Body'), 'phone_number' => substr($request->get('From'), 9));
        $answer = $chatbotService->typeofmessage($data);
        $response->message($answer);
        return Response::create($response, 200, array());


      /*  $data = array('message' => $request->get('Body'), 'phone_number' => substr($request->get('From'), 9));
        $answer = $chatbotService->typeofmessage($data);
        return Response::create($answer, 200, array());*/
    }




    /**
     * récupérer la liste des numéro des téléphones
     * @Route("/api/getphones",name="chatbot_phones", methods={"POST"})
     * @param ChatbotService $chatbotService
     * @return JsonResponse
     */
    public function Getphones(ChatbotService $chatbotService): JsonResponse
    {
        $resp = $chatbotService->Getphones();
        return new JsonResponse($resp);
    }

    /**
     * permet d'envoyer une  notification à l'utilisateurs
     * @Route("sendnotif",name="sendnotif",methods={"POST"})
     * @param Request $request
     * @param ChatbotService $chatbotService
     * @return JsonResponse
     */
    public function Sendnotif(Request $request, ChatbotService $chatbotService): ?JsonResponse
    {
        $response = $chatbotService->Sendnotif($request);

        if ($response) {
            return new JsonResponse(array('result' => 'succes'), 200);
        }
        else {
            return new JsonResponse(null, 500);
        }
    }

    /**
     * récupérer les statistiques par heure
     * @Route("/api/getdataperhour",name="getdataperhour",methods={"GET"})
     * @param Request $request
     * @param ChatbotService $chatbotService
     * @return JsonResponse
     */
    public function getdataperhour(Request $request, ChatbotService $chatbotService): ?JsonResponse
    {
        $resp = $chatbotService->getdataperhour();
        return new JsonResponse($resp);
    }


    /**
     * récupérer les statistiques par jour
     * @Route("/api/getdataperday",name="getdataperday",methods={"GET"})
     * @param Request $request
     * @param ChatbotService $chatbotService
     * @return JsonResponse
     */
    public function getdataperday(Request $request, ChatbotService $chatbotService): ?JsonResponse
    {
        //dd($request);
        $resp = $chatbotService->getdataperday();
        return new JsonResponse($resp);
    }

    /**
     * récupérer les statistiques par mois
     * @Route("/api/getdatapermonth",name="getdatapermonth",methods={"GET"})
     * @param Request $request
     * @param ChatbotService $chatbotService
     * @return JsonResponse
     */
    public function getdatapermonth(Request $request, ChatbotService $chatbotService): ?JsonResponse
    {
        $resp = $chatbotService->getdatapermonth();
        return new JsonResponse($resp);
    }


    /**
     * récupérer les statistiques par semaine
     * @Route("/api/getdataperweek",name="getdataperweek",methods={"GET"})
     * @param Request $request
     * @param ChatbotService $chatbotService
     * @return JsonResponse
     */
    public function getdataperweek(Request $request, ChatbotService $chatbotService): ?JsonResponse
    {
        $resp = $chatbotService->getdataperweek();
        return new JsonResponse($resp);
    }

    /**
     * récupérer les statistiques par date ("début"=>"fin")
     * @Route("/api/getdataperdate",name="getdataperdate",methods={"GET"})
     * @param Request $request
     * @param ChatbotService $chatbotService
     * @return JsonResponse
     */
    public function getdataperdate(Request $request, ChatbotService $chatbotService): ?JsonResponse
    {

        $resp = $chatbotService->getdataperdate($request->get('start'),$request->get('end'));
        return new JsonResponse($resp);
    }


    /**
     * permet de supprimer un utilisateur
     * @Route("/api/deleteuser",name="deleteuser",methods={"POST"})
     * @param Request $request
     * @param ChatbotService $chatbotService
     * @return JsonResponse
     */
    public function deleteuser(Request $request, ChatbotService $chatbotService): ?JsonResponse
    {

        $resp = $chatbotService->deleteuser($request->get('id'));
        return new JsonResponse($resp);
    }

    /**
     * permet d'ajouter un utilisateur
     * @Route("/api/adduser",name="adduser",methods={"POST"})
     * @param Request $request
     * @param ChatbotService $chatbotService
     * @return JsonResponse
     */
    public function adduser(Request $request, ChatbotService $chatbotService): ?JsonResponse
    {
        $resp = $chatbotService->adduser($request);
        if ($resp) {
            return new JsonResponse(array('id' => $resp), 200);
        }
        else {
            return new JsonResponse(array('result'=>'error'), 500);
        }
    }

    /**
     * permet de récupérer la liste des utilisateurs
     * @Route("/api/getusers",name="getusers",methods={"POST"})
     * @param Request $request
     * @param ChatbotService $chatbotService
     * @return JsonResponse
     */
    public function getusers(ChatbotService $chatbotService): ?JsonResponse
    {
        $resp = $chatbotService->getusers();
        return new JsonResponse($resp);
    }

    /**
     * permet d'ajouter un temps théorique
     * @Route("/api/addtemp_th",name="addtemp_th",methods={"POST"})
     * @param Request $request
     * @param ChatbotService $chatbotService
     * @return JsonResponse
     */
    public function addtemp_th(Request $request, ChatbotService $chatbotService): ?JsonResponse
    {
        $resp = $chatbotService->addtemp_th($request);
        return new JsonResponse($resp);
    }

    /**
     * Récupérer liste des  temps théoriques
     * @Route("/api/get_list_temp_th",name="get_list_temp_th",methods={"GET"})
     * @param Request $request
     * @param ChatbotService $chatbotService
     * @return JsonResponse
     */
    public function get_list_temp_th(Request $request, ChatbotService $chatbotService): ?JsonResponse
    {
        $resp = $chatbotService->get_list_temp_th($request);
        return new JsonResponse($resp);
    }


    /**
     * permet de supprimer un temps théorique
     * @Route("/api/delete_temp_th/{id}",name="delete_temp_th",methods={"POST"})
     * @param Request $request
     * @param ChatbotService $chatbotService
     * @return JsonResponse
     */
    public function delete_temp_th(Request $request, ChatbotService $chatbotService): ?JsonResponse
    {
      //  dd( $request->get('id'));
        $resp = $chatbotService->delete_temp_th($request->get('id'));
        return new JsonResponse($resp);
    }


    /**
     * permet d'ajouter le temps du premier/dernier tram
     * @Route("/api/addfirstlast",name="addfirstlast",methods={"POST"})
     * @param Request $request
     * @param ChatbotService $chatbotService
     * @return JsonResponse
     */
    public function addfirstlast(Request $request, ChatbotService $chatbotService): ?JsonResponse
    {
        $resp = $chatbotService->addfirstlast($request);
        return new JsonResponse($resp);
    }


    /**
     * permet de récupérer la liste des temps du premiers/derniers tram
     * @Route("/api/get_list_firstlast",name="get_list_firstlast",methods={"GET"})
     * @param Request $request
     * @param ChatbotService $chatbotService
     * @return JsonResponse
     */
    public function get_list_firstlast(Request $request, ChatbotService $chatbotService): ?JsonResponse
    {
        $resp = $chatbotService->get_list_firstlast($request);
        return new JsonResponse($resp);
    }


    /**
     * permet de supprimer le temps du premier/dernier tram
     * @Route("/api/delete_firstlast/{id}",name="delete_firstlast",methods={"POST"})
     * @param Request $request
     * @param ChatbotService $chatbotService
     * @return JsonResponse
     */
    public function delete_firstlast(Request $request, ChatbotService $chatbotService): ?JsonResponse
    {
        //  dd( $request->get('id'));
        $resp = $chatbotService->delete_firstlast($request->get('id'));
        return new JsonResponse($resp);
    }











}
