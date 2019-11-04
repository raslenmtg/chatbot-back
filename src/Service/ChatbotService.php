<?php


namespace App\Service;


use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpFoundation\Request;

class ChatbotService
{
  public function typeofmessage($message){

      $client = HttpClient::create();
      $response =$client->request('GET','https://api.wit.ai/message',['query'=>['v'=>'20191021','q'=>$message],'headers'=>['Authorization'=>'Bearer VQQ3PGJE5HYQWS2JW3X7FKMZEJLJV7LC']]);
      $content = $response->toArray();
    //  print_r($content['entities']['location'][0]['value']);
    if( isset ( $content['entities']['intent'][0]['value']))
          $intent= $content['entities']['intent'][0]['value'];
      else
          return 'Désolé je n’ai pas saisi votre question. Pourriez vous m’indiquer si votre question correspond à l’une de nos FAQ ? 
-	Ou puis-je acheter un ticket ou recharger ma carte ? 
-	J’ai perdu un objet, comment le retrouver ? 
-	Comment puis-je déposer une réclamation/plainte ?
-	A quelle station dois-je descendre ? 
-	Quelle est la station la plus proche de moi ? 
-	Quelle est la meilleure route ? 
';
switch ($intent){
    case "salutation": return $content['_text'].' ,Comment puis-je vous aider?';
    case "aller": return 'pour aller à '.$content['entities']['location'][0]['value']. ' vous puvez prend le trameway 52-B ou Bus 327, autre question ?';
    case "horaire":return 'le prochain tram vers '.$content['entities']['location'][0]['value'].' dans 15 minutes !';




    default: return 'Désolé je n’ai pas saisi votre question. Pourriez vous m’indiquer si votre question correspond à l’une de nos FAQ ? 
-	Ou puis-je acheter un ticket ou recharger ma carte ? 
-	J’ai perdu un objet, comment le retrouver ? 
-	Comment puis-je déposer une réclamation/plainte ?
-	A quelle station dois-je descendre ? 
-	Quelle est la station la plus proche de moi ? 
-	Quelle est la meilleure route ? 
';

}

  }




}
