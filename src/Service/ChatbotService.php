<?php


namespace App\Service;

use App\Entity\Phone;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Twilio\Exceptions\ConfigurationException;
use Twilio\Rest\Client;
define('location',['Sidi Moumen'=>array('lat'=>33.587280458339585,'lng'=>-7.500931050231884),
                    'Ennassim'=>array('lat'=>33.58508178237927,'lng'=>-7.50427844708247),
                    'Mohammed Zefzaf'=>array('lat'=>33.58226632282282,'lng'=>-7.508645083358715),
                    'Centre de maintenance'=>array('lat'=>33.579288580212925,'lng'=>-7.513350385736089),
                    'Centre de maintenance'=>array('lat'=>33.579288580212925,'lng'=>-7.513350385736089),

]);

/**
 * @property EntityManagerInterface em
 */
class ChatbotService
{
    private $session;

    public function __construct(EntityManagerInterface $em, SessionInterface $session)
    {
        $this->em = $em;
        $this->session = $session;
    }

    public function typeofmessage($data): ?string
    {
        $filename = 'C:\Users\Med Raslen\Desktop\GPS station Casa.csv';

// The nested array to hold all the arrays
        $the_big_array = [];

// Open the file for reading
        if (($h = fopen("{$filename}", "r")) !== FALSE)
        {
            // Each line in the file is converted into an individual array that we call $data
            // The items of the array are comma separated
            while (($data = fgetcsv($h, 1000, ",")) !== FALSE)
            {
                // Each individual array is being pushed into the nested array
                $the_big_array[] = $data;
            }

            // Close the file
            fclose($h);
        }

// Display the code in a readable format
        echo "<pre>";
       print_r($the_big_array);
        echo "</pre>";

        die();
        //////nombre de messages envoyés par utilisateur
        if ($this->session->has('nb_msg_user')) {
            $this->session->set('nb_msg_user', $this->session->get('nb_msg_user') + 1);
        } else {
            $this->session->set('nb_msg_user', 1);
        }
        //////END

        $message = $data['message'];
        $phone = $data['phone_number'];
        $new_phone = $this->addphone($phone);
        //////Nombres de nouveau clients
        if ($new_phone) {
            if ($this->session->has('nb_nouv_user')) {
                $this->session->set('nb_nouv_user', $this->session->get('nb_nouv_user') + 1);
            } else {
                $this->session->set('nb_nouv_user', 1);
            }
        }
        //////END
        $client = HttpClient::create();
      //  if ($this->session->get('last_resp') === 'get location') {


            $res=$client->request('GET','https://maps.googleapis.com/maps/api/geocode/json',['query'=>['region'=>'ma','address'=>$message.',casablanca','key'=>$_ENV['google_map_key']]]);
            $t = $res->toArray();
            $long=$t['results'][0]['geometry']['location']['lng'];
            $lat=$t['results'][0]['geometry']['location']['lat'];


      //  }


        try {
            $response = $client->request('GET', 'https://api.wit.ai/message', ['query' => ['v' => '20191021', 'q' => $message], 'headers' => ['Authorization' => 'Bearer ' . $_ENV['WIT_TOKEN']]]);
            $content = $response->toArray();

        } catch (Exception $e) {
            return 'serveur hors tension, reconnectez-vous en quelques minutes';
        }

        if (isset ($content['entities']['intent'][0]['value'])) {
            $intent = $content['entities']['intent'][0]['value'];
            $this->freq_question($intent);
        } else {
            /*
            $report = new  \App\Service\ChatbotReporting($this->em, $this->session);
            $report->reporting_parjour();*/

            return 'Désolé je n’ai pas saisi votre question. Pourriez vous m’indiquer si votre question correspond à l’une de nos FAQ ? 
-	Ou puis-je acheter un ticket ou recharger ma carte ? 
-	J’ai perdu un objet, comment le retrouver ? 
-	Comment puis-je déposer une réclamation ?
-	A quelle station dois-je descendre ? 
-	Quelle est la station la plus proche de moi ? 
-	Comment puis-je souscrire à un abonnement ?';
        }
        switch ($intent) {
            case 'salutation':
                //////Nombre de personnes qui ont contacter le chatbot
                if ($this->session->has('nb_user_contact')) {
                    $this->session->set('nb_user_contact', $this->session->get('nb_user_contact') + 1);
                } else {
                    $this->session->set('nb_user_contact', 1);
                }
                //////END
                return $content['_text'] . ' , Je suis Trambot 🤖 Comment puis-je vous aider ? 🙂';

            case 'station_proche':
               // return 'Pour connaitre la plus proche station 🚉 de vous cliquer ci-dessous !!🗺️';
                $this->session->set('last_resp', 'get location');
                return'Quel est votre position🗺️ ?';

            case 'aller':
                $this->session->set('last_resp', 'get location');
                return'Quel est votre position🗺️ ?';

                return 'pour aller à ' . $content['entities']['location'][0]['value'] . ' (Lien vers le site web)';

            case 'bénéfic_ab_etud':
                return "Oui, si vous êtes un étudiant 🧑‍🎓 de moins de 25ans provenant des établissements publics et privés ainsi que des formations professionnelles homologuées par le ministère de l'Éducation nationale, de la Formation Professionnelle, de l'Enseignement Supérieur et de la Recherche Scientifique.";
            case 'avantage':
                if (isset ($content['entities']['type_produit'][0]['value'])) {
                    $intent = $content['entities']['type_produit'][0]['value'];
                    switch ($intent) {
                        case 'abonnement Mensuel':
                            return "La carte d'abonnement 📅 vous permet de vous déplacer librement sur l'ensemble du réseau et d’effectuer des voyages illimités durant toute la période de l'abonnement.";
                        case 'abonnement étudiant':
                            return "L’abonnement étudiant 👨‍🎓 vous permet de vous déplacer librement sur l'ensemble du réseau, tout en bénéficiant d’un tarif préférentiel.";
                        case 'carte rechargeable' :
                            return 'La carte rechargeable 💳 a l’avantage d’être un support durable, et peut être rechargée de façon illimitée pendant 5ans, contrairement au ticket jetable qui lui ne peut être utilisé que 2 fois.
                                        Elle est valable sur l’ensemble du réseau de tramway 🚉.';
                        case 'abonnement hebdomadaire':
                            return "La carte d'abonnement 📆 vous permet de vous déplacer librement sur l'ensemble du réseau et d’effectuer des voyages illimités durant toute la période de l'abonnement.";
                    }
                } else {
                    return "répète ta question SVP, en précise type d'avatange: Carte Rechargeable 💳, Abonnement Mensuel 📅, Abonnement étudiant 👨‍🎓, Abonnement Hebdomadaire📆";
                }
                break;

            case 'réclamation':
                return 'Vous pouvez déposer votre réclamation sur notre site web en cliquant sur le lien ci-dessous ⬇️';

            case 'pièces':
                return 'Une copie de la CIN, Une photo ';

            case 'abonn_etudiant':
                return 'L’abonnement étudiant 🧑‍🎓 vous permet de vous déplacer librement sur l\'ensemble du réseau, tout en bénéficiant d’un  tarif préférentiel 🔥💰 .';

            case 'recharger':
                return 'Dans un guichet automatique en station 🚉, dans une agence ou auprès de l’un de nos revendeurs agrées.';

            case 'achat_ticket':
                return '🎫 Au niveau d’un guichet automatique en station, dans une agence ou auprès de l’un de nos revendeurs agrées.';

            case 'horaire_tram':
                return 'Pour connaître les horaires ⌚ et fréquences ⏲️des tramways cliquez sur le lien ci-dessous ⬇️ ⬇️';

            case 'horaire':
                return 'le prochain tram 🚉 vers ' . $content['entities']['location'][0]['value'] . ' dans 15 minutes !';

            case 'souscri_abonn':
                return 'Pour souscrire à un abonnement rendez-vous dans l’une de nos agences commerciales qui se trouvent à 🗺️ Abdelmoumen, Casa Voyageurs, Hay Mohammadi et Nations-Unies.';

            case 'horaire_ouv';
                return 'Pour connaître les horaires d’ouverture ⌚ de nos agences commerciales cliquez sur le lien ci-dessous ⬇️ ⬇️';

            case 'objet_perdu':
                return 'Vous pouvez contacter l\'agence la plus proche de chez vous.
Abdelmoumen, Casa Voyageurs, Hay Mohammadi et Nations-Unies. 📍
Ou par téléphone, au 05 22 99 83 83 📱';
            case 'remerciement':
                if ($new_phone) {
                    $return_msg = 'Souhaiteriez-vous que je vous tienne au courant des actualités.
    Je peux vous avertir en cas de promotions, d’offres spéciales, de problèmes de trafic et bien d’autres infos utiles ?';
                    $this->session->set('last_resp', 'ask permission to send notification');
                    return $return_msg;
                }

                return 'RatpDev 🚆 à votre service 😉 !';

            case 'accepter':
                if ($this->session->get('last_resp') === 'ask permission to send notification') {
                    $this->notif_auto($phone);
                    $this->session->remove('last_resp');
                    return 'Merci, RatpDev 🚆 à votre service 😉';
                }
                break;
            case 'refuser':
                if ($this->session->get('last_resp') === 'ask permission to send notification') {
                    return 'Aucun problème 😛. Sachez simplement que nous sommes ici si vous avez besoin de nous. Merci pour votre temps! 😉';
                }
                break;
            default:


                return 'Désolé je n’ai pas saisi votre question. Pourriez vous m’indiquer si votre question correspond à l’une de nos FAQ ? 
-	Ou puis-je acheter un ticket ou recharger ma carte ? 
-	J’ai perdu un objet, comment le retrouver ? 
-	Comment puis-je déposer une réclamation ?
-	A quelle station dois-je descendre ? 
-	Quelle est la station la plus proche de moi ? 
-	Comment puis-je souscrire à un abonnement ? ';

        }

    }


    public function addphone($phone): bool
    {
        $repository = $this->em->getRepository(Phone::class);
        $phoneexist = $repository->find($phone);
        if (!$phoneexist) {
            $p = new Phone();
            $p->setPhone($phone);
            $p->setNotifAuto(false);
            $this->em->persist($p);
            $this->em->flush();
            return true;
        }
        return false;
    }

    //activate notification
    public function notif_auto($phone): void
    {
        $repository = $this->em->getRepository(Phone::class);
        $ph = $repository->find($phone);
        if($ph){
        $ph->setNotifAuto(true);
        $this->em->flush();}
    }

    public function Sendnotif(Request $request): bool
    {
        $from = $_ENV['TWILIO_PHONE_NUMBER'];
        $files_exist = false;
        $hour = $request->get('hour');
        $minute = $request->get('minute');
        $date = explode('-', $request->get('date'));
        $message = $request->get('message');
        if (isset($_FILES['file'])) {
            if (move_uploaded_file($_FILES['file']['tmp_name'], $_SERVER['DOCUMENT_ROOT'] . $_FILES['file']['name'])) {
                $files_exist = true;
            }
            //
        }

        $repository = $this->em->getRepository(Phone::class);
        $phoneslist = $repository->findBy(['notif_auto' => true]);
        try {
            $twilio = new Client($_ENV['TWILIO_SID'], $_ENV['TWILIO_AUTH']);
        } catch (ConfigurationException $e) {
            var_dump($e);
        }
        foreach ($phoneslist as $phone) {
            //$notif = ["preview_url" => false, "recipient_type" => "individual", "to" => $phone, "type" => "text", "text" => ["body" => $message]];
            // echo $phone->getPhone();
            if ($files_exist) {
                try {
                    //   $rsp = $req->request('POST', $_ENV['URL_WA_SERVER'] . '/v1/messages', ['body' => json_encode($notif), 'headers' => ['Authorization' => 'Bearer ' . $this->session->get('token'), 'Content-Type' => 'application/json']]);
                    if ($files_exist) {
                        $twilio->messages
                            ->create($phone->getPhone(), // to
                                array(
                                    'body' => $message,
                                    'from' => $from
                                )
                            );
                    }
                } catch (Exception $e) {
                    dd($e);
                }
            } else {
                try {
                    //   $rsp = $req->request('POST', $_ENV['URL_WA_SERVER'] . '/v1/messages', ['body' => json_encode($notif), 'headers' => ['Authorization' => 'Bearer ' . $this->session->get('token'), 'Content-Type' => 'application/json']]);
                    if ($files_exist) {
                        $twilio->messages
                            ->create($phone->getPhone(), // to
                                array(
                                    'body' => $message,
                                    'from' => $from,
                                    'mediaUrl' => array($_SERVER['DOCUMENT_ROOT'] . $_FILES['file']['name'])
                                )
                            );
                    }
                } catch (Exception $e) {
                    var_dump($e);
                }
            }

        }
        if ($files_exist) {
            unlink($_SERVER['DOCUMENT_ROOT'] . $_FILES['file']['name']);
        }

        return true;
    }

    public function Getphones(): array
    {
        $repository = $this->em->getRepository(Phone::class);
        $phones = $repository->findBy(array('notif_auto'=>true));
        foreach ($phones as $phone) {
            $phoneslist[] = $phone->getPhone();
        }
        return $phoneslist;
    }



    public function freq_question($intent): void
    {

#TODO implement freq_question()

    }

}