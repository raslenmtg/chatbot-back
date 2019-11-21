<?php

namespace App\Service;

use App\Entity\Phone;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\HttpClient\HttpClient;


class ChatbotService
{


    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public function test()
    {
        return 'test';
    }


    public function typeofmessage($data)
    {
        $message = $data['messages'][0]['text']['body'];
        $phone = $data['messages'][0]['from'] ? $data['messages'][0]['from'] : $data['messages']['context']['from'];
        $this->addphone($phone);
        $client = HttpClient::create();
        try {
            $response = $client->request('GET', 'https://api.wit.ai/message', ['query' => ['v' => '20191021', 'q' => $message], 'headers' => ['Authorization' => 'Bearer ' . $_ENV['WIT_TOKEN']]]);
            $content = $response->toArray();
        } catch (Exception $e) {
            return 'serveur hors tension, reconnectez-vous en quelques minutes';
        }

        if (isset ($content['entities']['intent'][0]['value']))
            $intent = $content['entities']['intent'][0]['value'];
        else
            return 'Désolé je n’ai pas saisi votre question. Pourriez vous m’indiquer si votre question correspond à l’une de nos FAQ ? 
                    -	Ou puis-je acheter un ticket ou recharger ma carte ? 
                    -	J’ai perdu un objet, comment le retrouver ? 
                    -	Comment puis-je déposer une réclamation/plainte ?
                    -	A quelle station dois-je descendre ? 
                    -	Quelle est la station la plus proche de moi ? 
                    -	Quelle est la meilleure route ? ';
        switch ($intent) {
            case "salutation":
                return $content['_text'] . ' ,Comment puis-je vous aider ? :)';
            case "aller":
                return 'pour aller à ' . $content['entities']['location'][0]['value'] . ' vous puvez prend le trameway 52-B ou Bus 327, autre question ?';
            case "horaire":
                return 'le prochain tram vers ' . $content['entities']['location'][0]['value'] . ' dans 15 minutes !';
            case "remerciement":
                return 'Ratp à votre service :)';
        }

    }


    public function addphone($phone)
    {
        $repository = $this->em->getRepository(phone::class);
        $phoneexist = $repository->find($phone);
        if (!$phoneexist) {
            $p = new Phone();
            $p->setPhone($phone);
            $this->em->persist($p);
            $this->em->flush();
        }
    }

    public function Sendnotif(\Symfony\Component\HttpFoundation\Request $request)
    {

        $message = $request->get('message');
        $date = $request->get('date');
        $hour = $request->get('hour');
        $minute = $request->get('minute');
        $phoneslist = $this->Getphones();
        $req = HttpClient::create();
        foreach ($phoneslist as $phone) {
            $notif = ["preview_url" => false, "recipient_type" => "individual", "to" => $phone, "type" => "text", "text" => ["body" => $message]];

//            try {
//                $req->request('POST', 'http://localhost:8000/v1/messages', ['json' =>$notif]);
//            } catch (Exception $e) {
//                return 'error sending message';
//            }
        }
return true;
    }

    public function Getphones()
    {
        $repository = $this->em->getRepository(phone::class);
        $phones = $repository->findAll();
        foreach ($phones as $phone) {
            $phoneslist[] = $phone->getPhone();
        }
        return $phoneslist;
    }

}
