<?php


namespace App\Service;

use App\Entity\Firstlasttram;
use App\Entity\Phone;
use App\Entity\Sendnotif;
use App\Entity\TempTh;
use App\Entity\User;
use App\Repository\TempThRepository;
use DateTime;
use Doctrine\DBAL\DBALException;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use FOS\UserBundle\Model\UserManagerInterface;

use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Process\Process;
use Twilio\Exceptions\ConfigurationException;
use Twilio\Rest\Client;


/**
 * @property EntityManagerInterface em
 */
class ChatbotService
{

    private $session;
    private $usermanager;
    private $temprepo;

    public function __construct(EntityManagerInterface $em, SessionInterface $session, UserManagerInterface $userManager, TempThRepository $temprepo)
    {
        $this->temprepo = $temprepo;
        $this->em = $em;
        $this->session = $session;
        $this->usermanager = $userManager;
    }

    public function typeofmessage($data): ?string
    {
        $cache = new FilesystemAdapter();
      //  $cache->hasItem('nb_msg_user')
        if ($cache->hasItem('nb_msg_user')) {
            $msgCount =  $cache->getItem('nb_msg_user')->set($cache->getItem('nb_msg_user')->get()+1);
        } else {
            $msgCount =  $cache->getItem('nb_msg_user')->set(1);
        }
        $cache->save($msgCount);
        $message = $data['message'];
        $phone = $data['phone_number'];
        $new_phone = $this->addphone($phone);

        //////Nombres de nouveau clients
        if ($new_phone) {
            if ($cache->hasItem('nb_nouv_user')) {
                $msgCount =  $cache->getItem('nb_nouv_user')->set($cache->getItem('nb_nouv_user')->get()+1);

            } else {
                $msgCount =  $cache->getItem('nb_nouv_user')->set(1);
            }
            $cache->save($msgCount);
            $newCount = $cache->getItem('nb_nouv_user');
            $newCount->set($this->session->get('nb_nouv_user'));
            $cache->save($newCount);
        }

        //////END*/
        $client = HttpClient::create();
        try {
            $response = $client->request('GET', 'https://api.wit.ai/message', ['query' => ['v' => date("Ymd"), 'q' => $message], 'headers' => ['Authorization' => 'Bearer ' . $_ENV['WIT_TOKEN']]]);
            $content = $response->toArray();
        } catch (Exception $e) {
            return 'serveur hors tension, reconnectez-vous en quelques minutes';
        }
        if (stripos($content['_text'],'premier') !==false ){
            if(isset($content['entities']['datetime'][1]['values'][0]['value'])){
                $pre=$this->getfirstlast(true,$content['entities']['datetime'][1]['values'][0]['value']);
            }else
                $pre=$this->getfirstlast(true,null);
            return 'la liste des premiers Tram: '.$pre;
        }
        if (stripos($content['_text'],'dernier') !==false ){
            if(isset($content['entities']['datetime'][0]['value'])){
                $pre=$this->getfirstlast(false,$content['entities']['datetime'][0]['value']);
            }else
                $pre=$this->getfirstlast(false,null);
            if($pre!=='')
                return 'la liste des derniers Tram: '.$pre;
            else
                return 'Désolée cette information n\'est pas disponible pour le moment';
        }

        if (isset ($content['entities']['station_proche'][0]['value']) & !isset($content['entities']['intent'][0]['value'])) {
            $place = substr($content['_text'], 10);
            $station = $this->getnearestplace($place, '/gpscasa.csv', 'ma');
            return 'La station la plus proche de vous est Station ' . $station[0] . '. Vous pouvez vous y rendre ainsi https://www.google.com/maps/dir/?api=1&destination=' . $station[1] . ',' . $station[2];
        }
        if (isset ($content['entities']['dest_map'][0]['value']) & !isset($content['entities']['intent'][0]['value'])) {
            $place = substr($content['_text'], 11);
            $station = $this->getnearestplace($place, '/gpscasa.csv', 'ma');
            return 'Vous devez descendre à la station ' . $station[0] . '. Voici l\'itinéraire à partir de la station. https://www.google.com/maps/dir/?api=1&origin=' . $station[1] . ',' . $station[2] . '&destination=' . urlencode($place . ',casablanca,MA');
        }
        if (isset ($content['entities']['horaire'][0]['value'])) {
            $string = $content['_text'];
            $time = strtotime(substr($content['entities']['datetime'][0]['value'], 11, 8));

            $mintime = '';
            $taille_tab = count($content['entities']['datetime'][0]['values']);
            for ($i = 1; $i < $taille_tab; $i++) {

                if ($time > strtotime(substr($content['entities']['datetime'][0]['values'][$i]['value'], 11, 8))) {
                    $time = strtotime(substr($content['entities']['datetime'][0]['values'][$i]['value'], 11, 8));
                    $mintime = substr($content['entities']['datetime'][0]['values'][$i]['value'], 11, 8);
                }
            }
            if ($mintime == '')
                $mintime = substr($content['entities']['datetime'][0]['value'], 11, 8);
            $depart = trim(str_replace('"', '', substr($string, 7, strrpos(strtolower($string), 'heure', 0) - 7)));
            $direction = trim(str_replace('"', '', substr($string, strrpos(strtolower($string), 'direction', 0) + 9)));
            $tempstheo = $this->getintervalle_ma($depart, $direction, $mintime);
            if ($tempstheo === 'error')
                return $depart . ' et ' . $direction . ' ne sont pas sur la même ligne';
            // Le prochain devrait être à HH MM.
            else
                return 'Sauf perturbation, il y a un tramway chaque ' . $tempstheo . ' min à cette heure-ci.';

        }
        if (isset($content["_text"])) {
            switch ($content["_text"]) {
                case "1" :
                    $intent = 'horaire';
                    break;
                case "2" :
                    $intent = 'aller';
                    break;
                case "3" :
                    $intent = 'station_proche';
                    break;
                case "4" :
                    $intent = 'recharger';
                    break;
                case "5" :
                    $intent = 'avantage';
                    break;
                case "6" :
                    $intent = 'service client';
                    break;
                case "7" :
                    $intent = 'service client';
                    break;
            }
        }
        if (isset ($content['entities']['intent'][0]['value'])) {
            $intent = $content['entities']['intent'][0]['value'];
        } elseif ($intent === '') {
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
                if ($cache->hasItem('nb_user_contact')) {
                    $msgCount =  $cache->getItem('nb_user_contact')->set($cache->getItem('nb_user_contact')->get()+1);
                  //  $this->session->set('nb_user_contact', $this->session->get('nb_user_contact') + 1);
                } else {
                    $msgCount =  $cache->getItem('nb_user_contact')->set(1);
                }
                $cache->save($msgCount);
                $newCount = $cache->getItem('nb_user_contact');
                $newCount->set($this->session->get('nb_user_contact'));
                $cache->save($newCount);

                //////END
                return $content['_text'] . ' , Je suis Trambot 🤖 , l\'assistant virtuelle Casatram. Comment puis-je vous aider ? 🙂';

            case 'station_proche':
                // return 'Pour connaitre la plus proche station 🚉 de vous cliquer ci-dessous !!🗺️';
                return 'Dans quel quartier 🗺️ vous trouvez vous ? Merci de répondre sous ce format : je suis à "Quartier"';

            case 'aller':
                return 'Ou exactement voulez-vous vous rendre 🗺️ ? Merci de répondre sous ce format : Destination "Lieu" ?';

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
                    if ($content['entities']['type_produit'][0]['value'] === 'carte rechargeable') {
                        return 'La carte rechargeable 🎫 vous permet de recharger autant de voyage que vous voulez et à 6dh par voyage. Elle est valable 5 ans. ';
                    }

                }
                return 'Vous pouvez acheter ou recharger votre titre de transport/carte d\'abonnement dans les guichets automatiques situés à proximité des stations, dans nos agences ou chez nos revendeurs agréés. Vous pouvez trouver l\'agence ou le revendeur le plus proche en allant sur notre siteweb 🌐 https://www.casatramway.ma/fr/points-de-vente';


            case 'prix':
                if (isset ($content['entities']['type_produit'][0]['value'])) {
                    $intent = $content['entities']['type_produit'][0]['value'];
                    switch ($intent) {
                        case 'abonnement étudiant':
                            return 'L\'abonnement étudiant coute 150 dhs par mois + 15 dh le support, à acheter une seule fois et valable 5 ans. Vous pouvez retrouvez plus de détails sur nos tarifs ici https://www.casatramway.ma/fr/titres-et-tarifs/nos-offres';
                        case 'abonnement Mensuel':
                            return 'L\'abonnement mensuel est à 230 dhs par mois + 15 dh le support, à acheter une seule fois et valable 5 ans. Vous pouvez retrouvez plus de détails sur nos tarifs ici https://www.casatramway.ma/fr/titres-et-tarifs/nos-offres';
                        case 'abonnement hebdomadaire':
                            return 'L\'abonnement hebdomadaire est à 60 dhs par semaine + 15 dh le support, à acheter une seule fois et valable 5 ans. Vous pouvez retrouvez plus de détails sur nos tarifs ici https://www.casatramway.ma/fr/titres-et-tarifs/nos-offres';
                        case 'carte rechargeable':
                            return 'Le prix de la carte rechargeable (le support) est à 15dh. Vous pouvez recharger autant de voyage que vous voulez. Chaque voyage coute 6dh. Vous pouvez retrouvez plus de détails sur nos tarifs et nos offres ici https://www.casatramway.ma/fr/titres-et-tarifs/nos-offres';
                    }
                } else {
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
                $phoneaccepted = $repository->findOneBy(array('phone' => $phone, 'asked_notif' => false));
                if ($phoneaccepted) {
                    $return_msg = 'Trambot à votre service ! Voudriez vous recevoir des informations sur le tramway via whatsapp ? Répondez "Oui" ou "Non"';
                    $this->session->set('last_resp', 'ask permission to send notification');
                    return $return_msg;
                }

                return 'Trambot 🤖 à votre service 😉 !';

            case 'accepter':
                if ($this->session->get('last_resp') === 'ask permission to send notification') {
                    $this->enable_notif_auto($phone);
                    $this->confirm_notif($phone);
                    $this->session->remove('last_resp');
                    return ' Très bien. Vous recevrez des messages ✉️ sur whatsapp pour vous informer des offres ou encore des perturbations. Trambot 🤖 à votre service ! Merci 😉';
                }
                break;
            case 'refuser':
                if ($this->session->get('last_resp') === 'ask permission to send notification') {
                    return 'Très bien 😛. N\'hesitez pas à recontacter Trambot 🤖 sur whatsapp si besoin. Trambot à votre service ! 😉';
                }
                break;
            default:


                return "Désolé je n’ai pas saisi votre question. Pourriez vous m’indiquer si votre question correspond à l’une de nos FAQ ? 
1 - Horaires tramway
2 - Itinéraire 
3 - Station la plus proche 
4 - Carte rechargeable 
5 - Abonnement
6 - Service client 
7 - Réclamation 
Si l\\'une de ces propositions correspond à votre demande, merci de m\\'en informer,
Si aucune de ces propositions ne correspond à votre demande, vous pouvez contacter notre service client par téléphone ☎️au 0522998383 ou vous pouvez contacter notre service client directement sur notre site web 🌐 ici https://www.casatramway.ma/fr/contact";

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
        if ($ph) {
            $ph->setNotifAuto(true);
            $this->em->flush();
        }
    }

    function confirm_notif($phone)
    {
        $repository = $this->em->getRepository(Phone::class);
        $ph = $repository->findOneBy(array('phone' => $phone));
        if ($ph) {
            $ph->setAskennotif(true);
            $this->em->flush();
        }


    }

    public function Sendnotif(Request $request): bool
    {
        $hour = $request->get('hour');
        $minute = $request->get('minute');
        $message = $request->get('message');
        $notif=new Sendnotif();
        $notif->setMessage($message);
        if ( $request->files->get('file') ) {
            $filename=$request->files->get('file')->getClientOriginalName();
            $request->files->get('file')->move('files',$filename);
            $notif->setUrl($_SERVER['DOCUMENT_ROOT'] .'files/'.$filename );
        }
        $this->em->persist($notif);
        $this->em->flush();
        $hour=$hour<10?'0'.$hour:$hour;
        $minute=$minute<10?'0'.$minute:$minute;
        $process = new Process('at'.$hour.':'.$minute.' '.str_replace('-','/',$request->get('date')));
        $process->run();
        $process = new Process('php bin/console sendnotif '.$notif->getId());
        $process->run();
        return true;
    }

    public function Getphones(): array
    {
        $repository = $this->em->getRepository(Phone::class);
        $phones = $repository->findBy(array('notif_auto' => true));
        foreach ($phones as $phone) {
            $phoneslist[] = $phone->getPhone();
        }
        return $phoneslist;
    }

    public function getdataperhour()
    {
        try {
            $conn = $this->em->getConnection();
            $reports = $conn->fetchAll("SELECT * FROM reporting_heure  ORDER BY date DESC ;  ");
            return $reports;
        } catch (DBALException $e) {
            var_dump($e);
        }
    }

    public function getdataperday()
    {
        try {
            $conn = $this->em->getConnection();
            $reports = $conn->fetchAll("SELECT * FROM reporting_jour  ORDER BY date LIMIT 30;  ");
            return $reports;

        } catch (DBALException $e) {
            var_dump($e);
        }


    }

    public function getdataperweek()
    {
        try {
            $conn = $this->em->getConnection();
            $reports = $conn->fetchAll("SELECT * FROM reporting_semaine  ;  ");
            return $reports;
        } catch (DBALException $e) {
            var_dump($e);
        }


    }

    public function getdatapermonth()
    {
        try {
            $conn = $this->em->getConnection();
            $reports = $conn->fetchAll("SELECT * FROM reporting_mois ;  ");
            return $reports;
        } catch (DBALException $e) {
            var_dump($e);
        }


    }

    public function getdataperdate($start, $end)
    {


        try {
            $conn = $this->em->getConnection();
            $reports = $conn->fetchAll("SELECT * FROM reporting_jour  WHERE date >= ? AND date <= ?", array($start, $end));
            return $reports;
        } catch (DBALException $e) {
            var_dump($e);
        }
    }

    public function deleteuser($id)
    {

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

    public function getusers()
    {
        $repository = $this->em->getRepository(User::class);
        $user = $repository->findAll();
        foreach ($user as $u) {
            $phoneslist[] = array($u->getId(), $u->getUsername(), $u->getEmail(), $u->getLastLogin());
        }
        return $phoneslist;


    }

    public function getnearestplace($placetogo, $filename, $regioncode)
    {

        $client = HttpClient::create();
        $response = $client->request('GET', 'https://maps.googleapis.com/maps/api/geocode/json', ['query' => ['region' => $regioncode, 'address' => $placetogo.'Casablanca,Maroc', 'key' => $_ENV['google_map_key']]]);
        $data = json_decode($response->getContent());
        $lat = $data->results[0]->geometry->location->lat;
        $lng = $data->results[0]->geometry->location->lng;

        $filename = __DIR__ . $filename;
        $the_big_array = [];
        if (($h = fopen("{$filename}", "r")) !== FALSE) {
            while (($data = fgetcsv($h, 1000, ",")) !== FALSE) {
                $the_big_array[] = $data;
            }
            fclose($h);
        }
        $proche = array($the_big_array[0][0], abs($the_big_array[0][1]), abs($the_big_array[0][2]));
        $min=999;

        foreach ($the_big_array as $location) {

            $theta = $location[2] - $lng;
            $dist = sin(deg2rad($lat)) * sin(deg2rad($location[1])) +  cos(deg2rad($lat)) * cos(deg2rad($location[1])) * cos(deg2rad($theta));
            $dist = acos($dist);
            $dist = rad2deg($dist);
            $miles = $dist * 60 * 1.1515;

            if($min>$miles){
                $proche = array($location[0], $location[1], $location[2]);
                $min=$miles;

            }

        }

        return $proche;
    }


    public function getintervalle_ma($d, $dir, $time)
    {
        $max_similarity_dep = 0;
        $max_similarity_fin = 0;
        $depart = '';
        $direction = '';

        $T1 = array("Sidi Moumen", "Ennassim", "Mohammed Zefzaf", "Centre de maintenance", "Hôpital Sidi Moumen", "Attachourk", "Okba Ibn Nafii", "Forces auxiliaires", "Hay Raja", "Ibn Tachfine", "Hay Mohammadi", "Achouhada", "Ali Yaata", "Grand ceinture", "Anciens abattoirs", "Bd Bahmad", "Casa Voyageurs", "Place Al Yassir", "La Résistance", "Mohamed Diouri", "Marché Central", "Place des Nations Unies", "Place Mohammed V", "Avenue Hassan II", "Wafasalaf", "Faculté de Médecine", "Abdelmoumen", "Abdelmoumen", "Bachkou", "Mekka", "Gare Oasis", "Panoramique", "Technopark", "Zénith", "Gare Casa Sud", "Facultés", "Al Laymoune", "TERMINUS LISSASFA");
        $T2 = array("Sidi Bernoussi Terminus", "Abi Dar El Ghafari", "Gare de Ain Sbaa", "Préfecture Ain Sbaa", "AL Amane", "Wifaq", "Dar Laman", "Carrières centrale", "Qayssariat Hay Mohammadi", "Station Mdakra", "Hay Adil", "Cimetière Achohada", "Derb Milan", "Hay El Farah", "Derb Sultan", "Place Sraghna", "El Fida", "2 Mars", "Hermitage", "Anoual", "Derb Ghalef", "Riviera", "Ghandi", "Beauséjour", "Anfa Clubs", "Anfa Park", "Casa Finance", "Abdellah Ben Cherif", "Cité de l'air", "Sidi Abderrahmane", "Hay Hassani", "Littoral", "Ain Dhiab Plage Terminus");
        foreach ($T1 as $location) {
            $similar_text_depart = similar_text(strtolower($location),strtolower( $d));
            $similar_text_direc = similar_text(strtolower($location),strtolower( $dir));
            if ($similar_text_depart > $max_similarity_dep) {
                $depart = $location;
                $max_similarity_dep = $similar_text_depart;
            }
            if ($similar_text_direc > $max_similarity_fin) {
                $direction = $location;
                $max_similarity_fin = $similar_text_direc;
            }
        }
        foreach ($T2 as $location) {
            $similar_text_depart = similar_text(strtolower($location),strtolower( $d));
            $similar_text_direc = similar_text(strtolower($location),strtolower( $dir));
            if ($similar_text_depart > $max_similarity_dep) {
                $depart = $location;
                $max_similarity_dep = $similar_text_depart;
            }
            if ($similar_text_direc > $max_similarity_fin) {
                $direction = $location;
                $max_similarity_fin = $similar_text_direc;
            }
        }

        if (array_keys($T1, $depart) && array_keys($T1, $direction)) {
            //echo array_keys($T1,$depart)[0].array_keys($T1,$direction)[0];
            if (array_keys($T1, $depart)[0] < array_keys($T1, $direction)[0]) {
                $depart = 'Sidi Moumen';
                $direction = 'Lissasfa';
            } else {
                $depart = 'Lissasfa';
                $direction = 'Sidi Moumen';
            }

        } elseif (array_keys($T2, $depart) && array_keys($T2, $direction)) {

            if (array_keys($T2, $depart)[0] < array_keys($T2, $direction)[0]) {
                $depart = 'Bernoussi';
                $direction = 'Ain Dhiab';
            } else {
                $depart = 'Ain Dhiab';
                $direction = 'Bernoussi';
            }
        } else {
            return 'error';
        }
        $ss = ChatbotService::dateToFrench("now", "l");
        // $heure_th=DateTime::createFromFormat('H:i',substr($time,10,8));
        //  dd(DateTime::getLastErrors());

        $reports = $this->temprepo->findintervalle($ss, $time, $depart, $direction);
        if (isset($reports[0])) {
            $temp_theo = $reports[0]->getIntervalle()->format('i');
            return $temp_theo;
        } else
            return 'error';


    }

    public static function dateToFrench($date, $format)
    {
        $english_days = array('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday');
        $french_days = array('Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche');
        $english_months = array('January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December');
        $french_months = array('janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre');
        return str_replace($english_months, $french_months, str_replace($english_days, $french_days, date($format, strtotime($date))));
    }

    public function addtemp_th(Request $request)
    {

        try {
            $data = json_decode($request->getContent());
            $tempth = new TempTh();
            $tempth->setArrive($data->arrive);
            $tempth->setDepart($data->depart);
            $tempth->setJour($data->jour);
            $hfmin = $data->h_fin->minute < 10 ? '0' . $data->h_fin->minute : $data->h_fin->minute;
            $date = DateTime::createFromFormat('H:i', $data->h_fin->hour . ':' . $hfmin);// var_dump($date->format('H:i:s'));
            $tempth->setHFin($date);
            $hdmin = $data->h_depart->minute < 10 ? '0' . $data->h_depart->minute : $data->h_depart->minute;
            $date = DateTime::createFromFormat('H:i', $data->h_depart->hour . ':' . $hdmin);
            $tempth->setHDepart($date);
            $intmin = $data->intervalle->minute < 10 ? '0' . $data->intervalle->minute : $data->intervalle->minute;
            $date = DateTime::createFromFormat('H:i', $data->intervalle->hour . ':' . $intmin);// var_dump(DateTime::getLastErrors());
            $tempth->setIntervalle($date);
            $this->em->persist($tempth);
            $this->em->flush();
            if ($this->em->contains($tempth)) {
                return array("result" => true);
            }
        } catch (Exception $e) {
            return array("result" => false);
        }

    }

    public function get_list_temp_th(Request $request)
    {
        $repository = $this->em->getRepository(TempTh::class);
        $times = $repository->findAll();
        foreach ($times as $t) {
            $timelist[] = array($t->getId(), $t->getJour(), $t->getDepart(), $t->getArrive(), $t->getHDepart()->format('H:i'), $t->getHFin()->format('H:i'), $t->getIntervalle()->format('H:i'));
        }
        return $timelist;
    }

    public function delete_temp_th($id)
    {
        $repository = $this->em->getRepository(TempTh::class);
        $times = $repository->find($id);
        $this->em->remove($times);
        $this->em->flush();
        return array("result" => true);

    }


    public function addfirstlast(Request $request)
    {

        try {
            $data = json_decode($request->getContent());
            $tempth = new Firstlasttram();
            $tempth->setFirst($data->type);
            $tempth->setDepart($data->depart);
            $tempth->setJour($data->jour);
            $heure = $data->heure->minute < 10 ? '0' . $data->heure->minute : $data->heure->minute;
            $date = DateTime::createFromFormat('H:i', $data->heure->hour . ':' . $heure);// var_dump($date->format('H:i:s'));
            $tempth->setHeure($date);
            $this->em->persist($tempth);
            $this->em->flush();
            if ($this->em->contains($tempth)) {
                return array("result" => true);
            }
        } catch (Exception $e) {
            return array("result" => false);
        }

    }


    public function get_list_firstlast(Request $request)
    {
        $repository = $this->em->getRepository(Firstlasttram::class);
        $times = $repository->findAll();
        $timelist=[];
        foreach ($times as $t) {
            $timelist[] = array($t->getId(), $t->getJour(), $t->getDepart(), $t->getHeure()->format('H:i'), $t->getFirst());
        }
        return $timelist;
    }

    public function delete_firstlast($id)
    {
        $repository = $this->em->getRepository(Firstlasttram::class);
        $times = $repository->find($id);
        $this->em->remove($times);
        $this->em->flush();
        return array("result" => true);
    }

    public function getfirstlast($first,$date=null){

        $ss = $date?ChatbotService::dateToFrench($date, "l"):ChatbotService::dateToFrench("now", "l");
        $repository = $this->em->getRepository(Firstlasttram::class);
        $times = $repository->findBy(array('first'=>$first,'jour'=>$ss));
        $res='';
        foreach ($times as $time){
            $res=$res.$time->getDepart().' '.$time->getHeure()->format('H:m:s').' ';
        }
        return $res;

    }


}
