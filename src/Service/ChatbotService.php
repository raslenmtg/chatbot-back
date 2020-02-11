<?php


namespace App\Service;

use App\Entity\Phone;
use App\Entity\User;
use Doctrine\DBAL\DBALException;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use FOS\UserBundle\Model\UserManagerInterface;
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
    private $usermanager;
    public function __construct(EntityManagerInterface $em, SessionInterface $session,UserManagerInterface $userManager)
    {
        $this->em = $em;
        $this->session = $session;
        $this->usermanager=$userManager;
    }

    public function typeofmessage($data): ?string
    {
        $intent='';


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

        //////END*/
        $client = HttpClient::create();
        //  if ($this->session->get('last_resp') === 'get location') {


        /*    $res=$client->request('GET','https://maps.googleapis.com/maps/api/geocode/json',['query'=>['region'=>'ma','address'=>$message.',casablanca','key'=>$_ENV['google_map_key']]]);
            $t = $res->toArray();
            $long=$t['results'][0]['geometry']['location']['lng'];
            $lat=$t['results'][0]['geometry']['location']['lat'];

        */
        //  }


        try {
            $response = $client->request('GET', 'https://api.wit.ai/message', ['query' => ['v' => '20191021', 'q' => $message], 'headers' => ['Authorization' => 'Bearer ' . $_ENV['WIT_TOKEN']]]);
            $content = $response->toArray();
        } catch (Exception $e) {
            return 'serveur hors tension, reconnectez-vous en quelques minutes';
        }

        if(isset ($content['entities']['station_proche'][0]['value'])){
            $place= substr($content['_text'],10);
            $station= $this->getnearestplace($place);
            return 'La station la plus proche de vous est Station '.$station[0].'. Vous pouvez vous y rendre ainsi https://www.google.com/maps/dir/?api=1&destination='.urlencode($station[0].',casablanca,MA');
        }

        if(isset ($content['entities']['dest_map'][0]['value'])){
            $place= substr($content['_text'],11);
            $station= $this->getnearestplace($place);
            return 'Vous devez descendre à la station '.$station[0].'. Voici l\'itinéraire à partir de la station. https://www.google.com/maps/dir/?api=1&origin='.urlencode($station[0].',casablanca,MA').'&destination='.urlencode($place.',casablanca,MA') ;
        }
        if(isset ($content['entities']['horaire'][0]['value'])){
            return 'Sauf perturbation, il y a un tramway chaque XX min à cette heure-ci. Le prochain devrait être à HH MM. ';
        }

        if (isset($content["_text"])){
            switch ($content["_text"]){
                case "1" : $intent='horaire';
                    break;
                case "2" : $intent='aller';
                    break;
                case "3" : $intent='station_proche';
                    break;
                case "4" : $intent='recharger';
                    break;
                case "5" : $intent='avantage';
                    break;
                case "6" : $intent='service client';
                    break;
                case "7" : $intent='service client';
                    break;
            }
        }

        if (isset ($content['entities']['intent'][0]['value'])) {
            $intent = $content['entities']['intent'][0]['value'];

        } elseif ($intent==='') {
            /*
            $report = new  \App\Service\ChatbotReporting($this->em, $this->session);
            $report->reporting_parjour();*/
            return 'Désolé je n’ai pas saisi votre question. Pourriez vous m’indiquer si votre question correspond à l’une de nos FAQ ? 
1 - Horaires tramway
2 - Itinéraire 
3 - Station la plus proche 
4 - Carte rechargeable 
5 - Abonnement
6 - Service client 
7 - Réclamation 
Si l\'une de ces propositions correspond à votre demande, merci de m\'en informer,
Si aucune de ces propositions ne correspond à votre demande, vous pouvez contacter notre service client par téléphone ☎️au 0522998383 ou vous pouvez contacter notre service client directement sur notre site web 🌐 ici https://www.casatramway.ma/fr/contact';
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
                return $content['_text'] . ' , Je suis Trambot 🤖 , l\'assistant virtuelle Casatram. Comment puis-je vous aider ? 🙂';

            case 'station_proche':
                // return 'Pour connaitre la plus proche station 🚉 de vous cliquer ci-dessous !!🗺️';
                return'Dans quel quartier 🗺️ vous trouvez vous ? Merci de répondre sous ce format : je suis à "Quartier"';

            case 'aller':
                if(  isset($content['entities']['location'][0]['value'])){
                    $this->session->set('location', $content['entities']['location'][0]['value']);}

                return'Ou exactement voulez-vous vous rendre 🗺️ ? Merci de répondre sous ce format : Destination "Lieu" ?';

            case 'avantage':
                return 'La carte d\'abonnement vous permet de vous déplacer librement sur l\'ensemble du réseau et d’effectuer des voyages illimités durant toute la période de l\'abonnement. Il y a une différence sur la période de validité de la carte (1 semaine ou 1 mois). L\'abonnement étudiant vous donne les memes avantages mais à un prix préférenciel. ';

            case 'réclamation':
                return 'Vous pouvez joindre notre service client par téléphone ☎️au 0522998383 ou vous pouvez contacter notre service client directement sur notre site web 🌐 ici https://www.casatramway.ma/fr/contact';

            case 'horaire':
                return 'Merci de me préciser quelle est votre station 🚉 de départ, l\'heure ⏲️et votre direction 🗺️. Vous pouvez l\'ecrire comme ceci : Départ "Station", Heure "HH MM", Direction "Terminus"';

            case 'service client':
                return 'Vous pouvez joindre notre service client par téléphone ☎️au 0522998383 ou vous pouvez contacter notre service client directement sur notre site web 🌐 ici https://www.casatramway.ma/fr/contact';

            case 'pièces':
                return 'Vous devez uniquement fournir 2 documents : Une photo et une copie de la CIN. Vous l\'abonnement étudiant 🎓, il faut aussi fournir un certificat de scolarité.';

            case 'abonn_etudiant':
                return 'L’abonnement étudiant 🧑‍🎓 vous permet de vous déplacer librement sur l\'ensemble du réseau, tout en bénéficiant d’un  tarif préférentiel 🔥💰 .';

            case "recharger":
                if (isset ($content['entities']['type_produit'][0]['value'])) {
                    if ($content['entities']['type_produit'][0]['value']==='carte rechargeable' ){
                        return 'La carte rechargeable 🎫 vous permet de recharger autant de voyage que vous voulez et à 6dh par voyage. Elle est valable 5 ans. ';
                    }

                }
                return 'Vous pouvez acheter ou recharger votre titre de transport/carte d\'abonnement dans les guichets automatiques situés à proximité des stations, dans nos agences ou chez nos revendeurs agréés. Vous pouvez trouver l\'agence ou le revendeur le plus proche en allant sur notre siteweb 🌐 https://www.casatramway.ma/fr/points-de-vente';



            case 'prix':
                if (isset ($content['entities']['type_produit'][0]['value'])) {
                    $intent = $content['entities']['type_produit'][0]['value'];
                    switch ($intent){
                        case 'abonnement étudiant':
                            return 'L\'abonnement étudiant coute 150 dhs par mois + 15 dh le support, à acheter une seule fois et valable 5 ans. Vous pouvez retrouvez plus de détails sur nos tarifs ici https://www.casatramway.ma/fr/titres-et-tarifs/nos-offres';
                        case 'abonnement Mensuel':
                            return 'L\'abonnement mensuel est à 230 dhs par mois + 15 dh le support, à acheter une seule fois et valable 5 ans. Vous pouvez retrouvez plus de détails sur nos tarifs ici https://www.casatramway.ma/fr/titres-et-tarifs/nos-offres';
                        case 'abonnement hebdomadaire':
                            return 'L\'abonnement hebdomadaire est à 60 dhs par semaine + 15 dh le support, à acheter une seule fois et valable 5 ans. Vous pouvez retrouvez plus de détails sur nos tarifs ici https://www.casatramway.ma/fr/titres-et-tarifs/nos-offres';
                        case 'carte rechargeable':
                            return 'Le prix de la carte rechargeable (le support) est à 15dh. Vous pouvez recharger autant de voyage que vous voulez. Chaque voyage coute 6dh. Vous pouvez retrouvez plus de détails sur nos tarifs et nos offres ici https://www.casatramway.ma/fr/titres-et-tarifs/nos-offres';
                    }
                }
                else {
                    return 'Un titre de transport coute 8dh. Après votre premier voyage, vous pouvez le recharger une fois pour 6dh et le réutiliser. Vous pouvez retrouver toutes nos offres ici https://www.casatramway.ma/fr/titres-et-tarifs/nos-offres';
                }

            case 'souscri_abonn':
                return 'Pour souscrire à un abonnement rendez-vous dans l’une de nos agences commerciales qui se trouvent à 🗺️ Abdelmoumen, Casa Voyageurs, Hay Mohammadi et Nations-Unies.';

            case 'avoir_ab_etud':
                return 'Vous pouvez avoir accès à l\'abonnement pour étudiant si vous êtes un étudiant de moins de 25ans provenant des établissements publics et privés ainsi que des formations professionnelles homologuées par le ministère de l\'Éducation nationale, de la Formation Professionnelle, de l\'Enseignement Supérieur et de la Recherche Scientifique.';

            case 'horaire_ouv';
                return 'Pour connaître les horaires d’ouverture ⌚ de nos agences commerciales cliquez sur le lien ci-dessous ⬇️ ⬇️';

            case 'remerciement':
                $repository = $this->em->getRepository(Phone::class);
                $phoneaccepted = $repository->findOneBy(array('phone'=>$phone,'asked_notif'=>false));
                if ( $phoneaccepted ) {
                    $return_msg = 'Trambot à votre service ! Voudriez vous recevoir des informations sur le tramway via whatsapp ? Répondez "Oui" ou "Non"';
                    $this->session->set('last_resp', 'ask permission to send notification');
                    return $return_msg;
                }

                return 'RatpDev 🚆 à votre service 😉 !';

            case 'accepter':
                if ($this->session->get('last_resp') === 'ask permission to send notification') {
                    $this->enable_notif_auto($phone);
                    $this->confirm_notif($phone);
                    $this->session->remove('last_resp');
                    return ' Très bien. Vous recevrez des messages sur whatsapp pour vous informer des offres ou encore des perturbations. Trambot 🤖 à votre service ! Merci 😉';
                }
                break;
            case 'refuser':
                if ($this->session->get('last_resp') === 'ask permission to send notification') {
                    return 'Très bien 😛. N\'hesitez pas à recontacter Trambot 🤖 sur whatsapp si besoin. Trambot à votre service ! 😉';
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
            $p->setAskennotif(false);
            $this->em->persist($p);
            $this->em->flush();
            return true;
        }
        return false;
    }

    //activate notification
    public function enable_notif_auto($phone): void
    {
        $repository = $this->em->getRepository(Phone::class);
        $ph = $repository->find($phone);
        if($ph){
            $ph->setNotifAuto(true);
            $this->em->flush();}
    }
    function confirm_notif($phone){
        $repository = $this->em->getRepository(Phone::class);
        $ph = $repository->findOneBy(array('phone'=>$phone));
        if($ph){
            $ph->setAskennotif(true);
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
    public function getdataperhour(){
        try {
            $conn = $this->em->getConnection();
            $reports=$conn->fetchAll("SELECT * FROM reporting_heure  ORDER BY date DESC ;  ");
            return $reports;
        } catch (DBALException $e) {
            var_dump($e);
        }
    }

    public function getdataperday(){
        try {
            $conn = $this->em->getConnection();
            $reports=$conn->fetchAll("SELECT * FROM reporting_jour  ORDER BY date DESC ;  ");
            return $reports;

        } catch (DBALException $e) {
            var_dump($e);
        }


    }
    public function getdataperweek(){
        try {
            $conn = $this->em->getConnection();
            $reports=$conn->fetchAll("SELECT * FROM reporting_semaine  ;  ");
            return $reports;
        } catch (DBALException $e) {
            var_dump($e);
        }


    }

    public function getdatapermonth(){
        try {
            $conn = $this->em->getConnection();
            $reports=$conn->fetchAll("SELECT * FROM reporting_mois ;  ");
            return $reports;
        } catch (DBALException $e) {
            var_dump($e);
        }


    }

    public function getdataperdate($start, $end)
    {



        try {
            $conn = $this->em->getConnection();
            $reports=$conn->fetchAll("SELECT * FROM reporting_jour  WHERE date >= ? AND date <= ?",array($start,$end));
            return $reports;
        } catch (DBALException $e) {
            var_dump($e);
        }
    }

    public function deleteuser($id){

        try {
            $user = $this->usermanager->findUserBy(['id' => $id]);
            $this->usermanager->deleteUser($user);
            return true;
        } catch (Exception $e) {
            var_dump($e);
            return false;
        }
    }

    public function adduser(Request $req)
    {

        try {
            $user = $this->usermanager->createUser();
            $user->setUsername($req->get('username'));
            $user->setEmail($req->get('email'));
            $user->setPlainPassword($req->get('password'));
            $this->usermanager->updateUser($user);
            return $user->getId();
        } catch (Exception $e) {
            var_dump($e);
            return false;
        }
    }

    public function getusers(){
        $repository = $this->em->getRepository(User::class);
        $user = $repository->findAll();
        foreach ($user as $u) {
            $phoneslist[] = array($u-> getId(),$u-> getUsername(),$u->getEmail(),$u->getLastLogin());
        }
        return $phoneslist;



    }



    public function getnearestplace($placetogo){

        $client = HttpClient::create();
        $response = $client->request('GET', 'https://maps.googleapis.com/maps/api/geocode/json', ['query' => ['region' => 'ma', 'address' => $placetogo,'key'=>$_ENV['google_map_key']]]);
        $data=json_decode($response->getContent());
        $lat=$data->results[0]->geometry->location->lat;
        $lng=$data->results[0]->geometry->location->lng;

        $filename = __DIR__.'/gpscasa.csv';

        $the_big_array = [];
        if (($h = fopen("{$filename}", "r")) !== FALSE)
        {
            while (($data = fgetcsv($h, 1000, ",")) !== FALSE)
            {
                $the_big_array[] = $data;
            }
            fclose($h);
        }
        $proche=array($the_big_array[0][0],abs($the_big_array[0][1]),abs($the_big_array[0][2]));
        $min=array(50,50);
        foreach ( $the_big_array as $location){
            if(abs($location[1]-$lat)<$min[0] && abs($location[2]-$lng)<$min[1] ){
                $min=array(abs($location[1]-$lat),abs($location[2]-$lng) );
                $proche=array($location[0],$location[1],$location[2]);
            }
        }
        return $proche;
    }




}