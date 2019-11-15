<?php


namespace App\Service;


use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpClient\HttpClient;

class ChatbotService_IT extends AbstractController
{
    public function typeofmessage_it($message){

        $client = HttpClient::create();
        try {
        $response =$client->request('GET','https://api.wit.ai/message',['query'=>['v'=>'20191021','q'=>$message],'headers'=>['Authorization'=>'Bearer J4AHQAMMQRMSBE6LIOYMHOLEHRFHE6DX']]);
        $content = $response->toArray();
        } catch (\Exception $e) {
            return 'serveur hors tension, reconnectez-vous en quelques minutes';
        }
        print_r($content);
        if( isset ( $content['entities']['intent'][0]['value']))
            $intent= $content['entities']['intent'][0]['value'];

        else
            return 'Mi dispiace non ho capito la tua domanda. Potresti dirmi se la tua domanda corrisponde a una delle nostre FAQ?
- Dove posso acquistare un biglietto o ricaricare la mia carta?
- Ho perso un oggetto, come trovarlo?
- Come posso presentare un reclamo ?
- A quale stazione dovrei andare?
- Qual è la stazione più vicina a me?
- Qual è il percorso migliore?
';
        switch ($intent){
            case "salutation": return $content['_text'].' ,come posso aiutarti?';
            case "aller": return 'per andare a '.$content['entities']['location'][0]['value']. " puoi prendere il tram 52-B o l'autobus 327, un altra domanda?";
            case "horaire":return 'il prossimo tram per '.$content['entities']['location'][0]['value'].' tra 15 minuti';




        }

    }





}