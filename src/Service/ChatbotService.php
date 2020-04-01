<?php


namespace App\Service;

use App\Entity\Firstlasttram;
use App\Entity\Phone;
use App\Entity\Sendnotif;
use App\Entity\TempTh;
use App\Entity\User;
use App\Repository\TempThRepository;
use DateInterval;
use DateTime;
use Doctrine\DBAL\DBALException;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use FOS\UserBundle\Model\UserManagerInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Process\Process;


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

    public function confirm_notif($phone): void
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

    public function getdataperhour(): ?array
    {

            $conn = $this->em->getConnection();
            $reports = $conn->fetchAll("SELECT * FROM reporting_heure  ORDER BY date DESC ;  ");
            return $reports;

    }

    public function getdataperday(): ?array
    {
            $conn = $this->em->getConnection();
            $reports = $conn->fetchAll("SELECT * FROM reporting_jour  ORDER BY date LIMIT 30;  ");
            return $reports;
    }

    public function getdataperweek(): ?array
    {
            $conn = $this->em->getConnection();
        return $conn->fetchAll("SELECT * FROM reporting_semaine  ;  ");
    }

    public function getdatapermonth(): ?array
    {
            $conn = $this->em->getConnection();
        return $conn->fetchAll("SELECT * FROM reporting_mois ;  ");
    }

    public function getdataperdate($start, $end): ?array
    {
            $conn = $this->em->getConnection();
            $reports = $conn->fetchAll("SELECT * FROM reporting_jour  WHERE date >= ? AND date <= ?", array($start, $end));
            return $reports;
    }

    public function deleteuser($id): ?bool
    {

        try {
            $user = $this->usermanager->findUserBy(['id' => $id]);
            $this->usermanager->deleteUser($user);
            return true;
        } catch (Exception $e) {
            //var_dump($e);
            return false;
        }
    }

    /**
     * @param Request $req
     * @return bool|mixed
     */
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
           // var_dump($e);
            return false;
        }
    }

    public function getusers(): array
    {
        $repository = $this->em->getRepository(User::class);
        $user = $repository->findAll();
        foreach ($user as $u) {
            $phoneslist[] = array($u->getId(), $u->getUsername(), $u->getEmail(), $u->getLastLogin());
        }
        return $phoneslist;


    }

    /**
     * @param $placetogo
     * @param $filename
     * @param $regioncode
     * @return array
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
    public function getnearestplace($placetogo, $filename, $regioncode): array
    {

        $client = HttpClient::create();
        $response = $client->request('GET', 'https://maps.googleapis.com/maps/api/geocode/json', ['query' => ['region' => $regioncode, 'address' => $placetogo.'Alger, Algérie', 'key' => $_ENV['google_map_key']]]);
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
        $min = array(50, 50);
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

    /**
     * @param $data
     * @return string
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
    public function typeofmessage_alger($data): ?string
    {

        if ($this->session->has('nb_msg_user')) {
            $this->session->set('nb_msg_user', $this->session->get('nb_msg_user') + 1);
        } else {
            $this->session->set('nb_msg_user', 1);
        }
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
        try {
            $response = $client->request('GET', 'https://api.wit.ai/message', ['query' => ['v' => date("Ymd"), 'q' => $message], 'headers' => ['Authorization' => 'Bearer ' . $_ENV['WIT_TOKEN_AL']]]);
            $content = $response->toArray();
        } catch (Exception $e) {
            return 'serveur hors tension, reconnectez-vous en quelques minutes';
        }
        if (stripos($content['_text'],'premier') !==false ){
            if(isset($content['entities']['datetime'][1]['values'][0]['value'])){
                $pre=$this->getfirstlast(true,$content['entities']['datetime'][1]['values'][0]['value']);
            }else {
                $pre = $this->getfirstlast(true);
            }
            if($pre!=='') {
                return 'la liste des Premiers Métros: ' . $pre;
            }
            else
                return 'Désolée cette information n\'est pas disponible pour le moment';
        }
        if (stripos($content['_text'],'dernier') !==false ){
            if(isset($content['entities']['datetime'][0]['value'])){
                $pre=$this->getfirstlast(false,$content['entities']['datetime'][0]['value']);
            }else
                $pre=$this->getfirstlast(false);
            if($pre!=='') {
                return 'la liste des derniers Métros: ' . $pre;
            }

            return 'Désolée cette information n\'est pas disponible pour le moment';
        }


        if (isset ($content['entities']['station_proche'][0]['value']) & !isset($content['entities']['intent'][0]['value'])) {
            $place = substr($content['_text'], 10);
            $station = $this->getnearestplace($place, '/gpsalger.csv', 'dz');
            return 'La station la plus proche de vous est Station ' . $station[0] . '. Vous pouvez vous y rendre ainsi https://www.google.com/maps/dir/?api=1&travelmode=walking&destination=' . $station[1] . ',' . $station[2];
        }
        if (isset ($content['entities']['horaire'][0]['value'])) {
            $string = $content['_text'];
            if(strrpos(strtolower($string), 'heure')>strrpos(strtolower($string), 'direction')){
            return 'Je n\'ai pas compris toutes les informations. Reprenez le format Départ "Station", Heure "HH:MM", Direction "Terminus" ';
            }
            $time = strtotime(substr($content['entities']['datetime'][0]['value'], 11, 8));
            $mintime = '';
            $taille_tab = count($content['entities']['datetime'][0]['values']);
            for ($i = 1; $i < $taille_tab; $i++) {

                if ($time > strtotime(substr($content['entities']['datetime'][0]['values'][$i]['value'], 11, 8))) {
                    $time = strtotime(substr($content['entities']['datetime'][0]['values'][$i]['value'], 11, 8));
                    $mintime = substr($content['entities']['datetime'][0]['values'][$i]['value'], 11, 8);
                }
            }
            if ($mintime === '')
                $mintime = substr($content['entities']['datetime'][0]['value'], 11, 8);
            $depart = trim(str_replace('"', '', substr($string, 7, strrpos(strtolower($string), 'heure') - 7)));
            $direction = trim(str_replace('"', '', substr($string, strrpos(strtolower($string), 'direction') + 9)));
            $tempstheo = $this->getintervalle_al($depart, $direction, $mintime);
            if ($tempstheo === 'error')
                return 'Désolée cette information n\'est pas disponible pour le moment';
            else
            return 'Sauf perturbation, il y a un tramway chaque ' . $tempstheo[0] . ' min à cette heure-ci. Le prochain devrait être à '. $tempstheo[1];
        }
        if (isset ($content['entities']['dest_map'][0]['value']) & !isset($content['entities']['intent'][0]['value'])) {
            $place = substr($content['_text'], 11);
            $station = $this->getnearestplace($place, '/gpsalger.csv', 'dz');
            return 'Vous devez descendre à la station ' . $station[0] . '. Voici l\'itinéraire à partir de la station. https://www.google.com/maps/dir/?api=1&origin=' . $station[1] . ',' . $station[2] . '&travelmode=walking&destination=' . urlencode($place . ',Algeria,DZ');
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
                case "abonnement":
                case "Abonnement":
                case "abonement":
                case "4" :
                   return 'La carte d\'abonnement vous permet de vous déplacer librement sur l\'ensemble du réseau et d’effectuer des voyages illimités durant toute la période de l\'abonnement à un prix préférenciel.';
                    break;
                case "6" :
                case "5" :
                    $intent = 'service client';
                    break;
                }
        }
        if (isset ($content['entities']['intent'][0]['value'])) {
            $intent = $content['entities']['intent'][0]['value'];
        } elseif (!isset($intent)) {
            return 'Désolé je n’ai pas saisi votre question. Pourriez vous m’indiquer si votre question correspond à l’une de nos FAQ ? 
1 - Horaires tramway
2 - Itinéraire 
3 - Station la plus proche 
4 - Abonnement
5 - Service client 
6 - Réclamation 
Si l\'une de ces propositions correspond à votre demande, merci de m\'en informer,
Si aucune de ces propositions ne correspond à votre demande, vous pouvez contacter notre service client par téléphone ☎️au 021778779 ou vous pouvez contacter notre service client directement par mail 📧: sav.alger@ratp-eldjazair.com';
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
                return $content['_text'] . ' , Je suis MOMO 🤖 , l\'assistant virtuelle du Métro d\'Alger. Comment puis-je vous aider ? 🙂';

            case 'station_proche':
                // return 'Pour connaitre la plus proche station 🚉 de vous cliquer ci-dessous !!🗺️';
                return 'Dans quel quartier 🗺️ vous trouvez vous ? Merci de répondre sous ce format : je suis à "Quartier"';

            case 'recharger':
                return 'Vous pouvez recharger votre carte d\'abonnement au niveau de nos agences commerciales, des guichets de vente ou des distributeurs automatiques de billets ';

            case 'aller':
                return 'Ou exactement voulez-vous vous rendre 🗺️ ? Merci de répondre sous ce format : Destination "Lieu" ?';

            case 'avantage':
                return 'La carte d\'abonnement vous permet de vous déplacer librement sur l\'ensemble du réseau et d’effectuer des voyages illimités durant toute la période de l\'abonnement à un prix préférenciel.';

            case 'réclamation':
                return 'Vous pouvez joindre notre service client par téléphone ☎️au 021778779 ou par mail 📧: sav.alger@ratp-eldjazair.com.';

            case 'ouverture':
                return 'les horaires d\'exploitation du Métro d\'Alger sont de 05h00 à 23h00, 7j/7';

            case 'horaire':
                return 'Merci de me préciser quelle est votre station 🚉 de départ, l\'heure ⏲️et votre direction 🗺️. Vous pouvez l\'ecrire comme ceci : Départ "Station", Heure "HH:MM", Direction "Terminus"';

            case 'service client':
                return 'Vous pouvez joindre notre service client par téléphone ☎️au 021778779 ou vous pouvez contacter notre service client directement par mail 📧: sav.alger@ratp-eldjazair.com';

            case 'pièces':
                return 'Vous devez uniquement fournir 2 documents : Une photo et une copie de la CIN. plus des documents spécifiques aux différents types d\'abonnements qui vous corresponds.';

            case 'prix':
                if (isset ($content['entities']['type_produit'][0]['value'])) {
                    $intent = $content['entities']['type_produit'][0]['value'];
                    switch ($intent) {
                        case 'abonnement étudiant':
                            return 'L\'abonnement étudiant coute 700 dinar par mois et 7.000 dinar par an.';
                        case 'abonnement Mensuel':
                            return 'L\'abonnement mensuel est à 1820 dinar par mois.';
                        case 'abonnement hebdomadaire':
                            return 'L\'abonnement hebdomadaire est à 540 dinar par semaine.';
                        case 'abonnement jeune':
                            return 'l\'abonnement jeune est à 1200 dinar par mois.';
                        case 'abonnement scolaire':
                            return 'l\'abonnement scolaire est à 400 dinar par mois, et 4,000 dinar par an';
                        case 'abonnement sénior':
                            return 'l\'abonnement sénior est à 1,000 dinar';
                        case 'abonnement unique':
                            return 'l\'abonnement unique est à 2,500 dinar, valable pour les quatres types de transport: Métro - Tramway - Transport par cables - bus ETUSA';
                        case 'carte_unit':
                            return 'Le prix de la carte à unités de transport (le support) est à 300 dinar. 10 voyages: 400 dinar / 20 voyages: 700 dinar / 30 voyages: 1020 dinar / 40 voyages: 1320 dinar / 50 voyages: 1600 dinar.';
                    }
                } else {
                    return 'Un titre de transport coute 50 dinar, et le pass 24h coute 150 dinar';
                }

            case 'souscri_abonn':
                return 'Pour souscrire à un abonnement rendez-vous dans l’une de nos agences commerciales ';

            case 'avoir_ab':
                if (isset ($content['entities']['type_produit'][0]['value'])) {
                    $intent = $content['entities']['type_produit'][0]['value'];
                    switch ($intent) {
                        case 'abonnement scolaire':
                            return 'Vous pouvez souscrire à un abonnement scolaire si vous êtes au primaire, collége ou lycée';
                        case 'abonnement sénior':
                            return 'pour souscrire à un abonnent sénior vous devez avoir plus de 60 ans.';
                        case 'abonnement jeune':
                            return 'vous pouvez souscrire à un abonnement jeune si vous avez moins de 25 ans.';
                        case 'abonnement unique':
                            return 'Vous devez uniquement fournir 2 documents : Une photo et une copie de la CIN';

                    }
                }
                return 'Vous pouvez souscrire à un abonnement étudiant si vous êtes un étudiant de moins de 29ans provenant des établissements publics et privés ainsi que des formations professionnelles homologuées par le ministère de l\'Éducation nationale, de la Formation Professionnelle, de l\'Enseignement Supérieur et de la Recherche Scientifique.';

            case 'horaire_ouv';
                return 'Pour connaître les horaires d’ouverture ⌚ de nos agences commerciales cliquez sur le lien ci-dessous ⬇️ ⬇️';

            case 'remerciement':
                $repository = $this->em->getRepository(Phone::class);
                $phoneaccepted = $repository->findOneBy(array('phone' => $phone, 'asked_notif' => false));
                if ($phoneaccepted) {
                    $return_msg = 'MOMO 🤖 à votre service ! Voudriez vous recevoir des informations sur le Métro via whatsapp ? Répondez "Oui" ou "Non"';
                    $this->session->set('last_resp', 'ask permission to send notification');
                    return $return_msg;
                }

                return 'MOMO 🤖 à votre service 😉 !';

            case 'accepter':
                if ($this->session->get('last_resp') === 'ask permission to send notification') {
                    $this->enable_notif_auto($phone);
                    $this->confirm_notif($phone);
                    $this->session->remove('last_resp');
                    return ' Très bien. Vous recevrez des messages ✉️sur whatsapp pour vous informer des offres ou encore des perturbations. MOMO 🤖 à votre service ! Merci 😉';
                }
                break;
            case 'refuser':
                if ($this->session->get('last_resp') === 'ask permission to send notification') {
                    $this->session->remove('last_resp');
                    return 'Très bien 😛. N\'hesitez pas à recontacter MOMO 🤖 sur whatsapp si besoin. MOMO à votre service ! 😉';
                }
                break;
            default:


                return 'Désolé je n’ai pas saisi votre question. Pourriez vous m’indiquer si votre question correspond à l’une de nos FAQ ? 
1 - Horaires tramway
2 - Itinéraire 
3 - Station la plus proche 
4 - Abonnement
5 - Service client 
6 - Réclamation 
Si l\'une de ces propositions correspond à votre demande, merci de m\'en informer,
Si aucune de ces propositions ne correspond à votre demande, vous pouvez contacter notre service client par téléphone ☎️au 021778779 ou vous pouvez contacter notre service client directement par mail : sav.alger@ratp-eldjazair.com';

        }
    }

    public static function dateToFrench($date, $format)
    {
        $english_days = array('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday');
        $french_days = array('Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche');
        $english_months = array('January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December');
        $french_months = array('janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre');
        return str_replace($english_months, $french_months, str_replace($english_days, $french_days, date($format, strtotime($date))));
    }

    public function addtemp_th(Request $request): ?array
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

    public function get_list_temp_th(Request $request): array
    {   $timelist=[];
        $repository = $this->em->getRepository(TempTh::class);
        $times = $repository->findAll();
        foreach ($times as $t) {
            $timelist[] = array($t->getId(), $t->getJour(), $t->getDepart(), $t->getArrive(), $t->getHDepart()->format('H:i'), $t->getHFin()->format('H:i'), $t->getIntervalle()->format('H:i'));
        }
        return $timelist;
    }



    public function delete_temp_th($id): array
    {
        $repository = $this->em->getRepository(TempTh::class);
        $times = $repository->find($id);
        $this->em->remove($times);
        $this->em->flush();
        return array("result" => true);
    }



    public function addfirstlast(Request $request): ?array
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


    public function get_list_firstlast(Request $request): array
    {
        $repository = $this->em->getRepository(Firstlasttram::class);
        $times = $repository->findAll();
        $timelist=[];
        foreach ($times as $t) {
            $timelist[] = array($t->getId(), $t->getJour(), $t->getDepart(), $t->getHeure()->format('H:i'), $t->getFirst());
        }
        return $timelist;
    }

    public function delete_firstlast($id): array
    {
        $repository = $this->em->getRepository(Firstlasttram::class);
        $times = $repository->find($id);
        $this->em->remove($times);
        $this->em->flush();
        return array("result" => true);
    }


    public function getintervalle_al($d, $dir, $time):array
    {
        $max_similarity_dep = 0;
        $max_similarity_fin = 0;
        $depart = '';
        $direction = '';
        $T1=array("Place des Martyrs","Ali BOUMENDJEL","Tafourah","Khelifa BOUKHALFA","1er Mai","Aissat IDIR","Hamma","Jardin d’Essais","Les Fusillés","AMIROUCHE","Mer & Soleil","Hay El Badr","Bachdjarah Tennis","Bachdjarah","El Harrach Gare","El Harrach Centre");
        $T2=array("Hay El Badr","Les Ateliers","Gué de Constantine","Ain Naadja");
       foreach ($T1 as $location) {
            $similar_text_depart = similar_text(strtolower( $location),strtolower( $d));
            $similar_text_direc = similar_text(strtolower($location), strtolower($dir));
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
            $similar_text_direc = similar_text(strtolower($location), strtolower($dir));
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
                $depart = 'Place des Martyrs';
                $direction = 'El Harrach Centre';
            } else {
                $depart = 'El Harrach Centre';
                $direction = 'Place des Martyrs';
            }

        } elseif (array_keys($T2, $depart) && array_keys($T2, $direction)) {

            if (array_keys($T2, $depart)[0] < array_keys($T2, $direction)[0]) {
                $depart = 'Hay El Badr';
                $direction = 'Ain Naadja';
            } else {
                $depart = 'Ain Naadja';
                $direction = 'Hay El Badr';
            }
        } else {
            return array('error');
        }
        $ss = self::dateToFrench("now", "l");
        // $heure_th=DateTime::createFromFormat('H:i',substr($time,10,8));
        //  dd(DateTime::getLastErrors());

        $reports = $this->temprepo->findintervalle($ss, $time, $depart, $direction);
        if (isset($reports[0])) {
            $result= new DateTime($time);
            $temp_theo = $reports[0]->getIntervalle()->format('i');
            $d=new DateInterval('PT'.$temp_theo.'M');
            $result->add($d) ;
            return array($temp_theo,$result->format('H:i'));
        } else
            return array('error');


    }

    /**
     * @param $first
     * @param null $date
     * @return string
     */
    public function getfirstlast($first, $date=null): string
    {

        $ss = $date? self::dateToFrench($date, "l"): self::dateToFrench("now", "l");
        $repository = $this->em->getRepository(Firstlasttram::class);
        $times = $repository->findBy(array('first'=>$first,'jour'=>$ss));
        $res='';
        foreach ($times as $time){
        $res .= $time->getDepart() . ' ' . $time->getHeure()->format('H:m:s') . '
         
         ';
        }
        return $res;

    }


}
