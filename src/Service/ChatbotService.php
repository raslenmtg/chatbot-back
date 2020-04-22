<?php


namespace App\Service;

use App\Entity\Firstlasttram;
use App\Entity\Phone;
use App\Entity\Sendnotif;
use App\Entity\TempTh;
use App\Entity\User;
use App\Repository\TempThRepository;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use FOS\UserBundle\Model\UserManagerInterface;

use Symfony\Component\Cache\Adapter\FilesystemAdapter;
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

    public function typeofmessage_it($data)
    {


        $cache = new FilesystemAdapter();
        //  $cache->hasItem('nb_msg_user')
        if ($cache->hasItem('nb_msg_user')) {
            $msgCount = $cache->getItem('nb_msg_user')->set($cache->getItem('nb_msg_user')->get() + 1);
        } else {
            $msgCount = $cache->getItem('nb_msg_user')->set(1);
        }
        $cache->save($msgCount);
        $message = $data['message'];
        $phone = $data['phone_number'];
        $new_phone = $this->addphone($phone);

        //////Nombres de nouveau clients
        if ($new_phone) {
            if ($cache->hasItem('nb_nouv_user')) {
                $msgCount = $cache->getItem('nb_nouv_user')->set($cache->getItem('nb_nouv_user')->get() + 1);

            } else {
                $msgCount = $cache->getItem('nb_nouv_user')->set(1);
            }
            $cache->save($msgCount);
            $newCount = $cache->getItem('nb_nouv_user');
            $newCount->set($this->session->get('nb_nouv_user'));
            $cache->save($newCount);
        }


        $client = HttpClient::create();
        try {
            $response = $client->request('GET', 'https://api.wit.ai/message', ['query' => ['v' => date("Ymd"), 'q' => $message], 'headers' => ['Authorization' => 'Bearer ' . $_ENV['WIT_TOKEN']]]);
            $content = $response->toArray();
        } catch (Exception $e) {
            dd($e);
        }

        if (stripos($content['_text'],'premier') !==false ){
            if(isset($content['entities']['datetime'][1]['values'][0]['value'])){
                $pre=$this->getfirstlast(true,$content['entities']['datetime'][1]['values'][0]['value']);
            }else
                $pre=$this->getfirstlast(true,null);
            if($pre!=='')
                return 'l\'elenco degli ultimi metro: '.$pre;
            else
                return 'Spiacenti, al momento questa informazione non è disponibile';
        }
        if (stripos($content['_text'],'dernier') !==false ){
            if(isset($content['entities']['datetime'][0]['value'])){
                $pre=$this->getfirstlast(false,$content['entities']['datetime'][0]['value']);
            }else
                $pre=$this->getfirstlast(false,null);
            if($pre!=='')
                return 'l\'elenco degli ultimi metro: '.$pre;
            else
                return 'Spiacenti, al momento questa informazione non è disponibile';
        }



        if (isset ($content['entities']['station_proche'][0]['value']) & !isset($content['entities']['intent'][0]['value'])) {
            $place = substr($content['_text'], 13);

            $station = $this->getnearestplace($place, '/gpsitalie.csv', 'it');
            return 'Deve scendere alla fermata ' . $station[0] . '. Ecco l\'itinerario a partire dalla fermata. https://www.google.com/maps/dir/?api=1&origin=' . $station[1] . ',' . $station[2] . '&destination=' . urlencode($place . ',Italie');
        }
        if (isset ($content['entities']['dest_map'][0]['value']) & !isset($content['entities']['intent'][0]['value'])) {
            $place = substr($content['_text'], 11);
            $station = $this->getnearestplace($place, '/gpsitalie.csv', 'it');
            return 'La fermata più vicina è la fermata' . $station[0] . '.  Può arrivarci così https://www.google.com/maps/dir/?api=1&destination=' . $station[1] . ',' . $station[2];
        }
        if (isset ($content['entities']['horaire'][0]['value'])) {
            $string = $content['_text'];
            var_dump(strripos($string, 'ora'));
            var_dump(strripos($string, 'direzione'));
            die;
            if(strripos($string, 'ora') > strripos($string, 'direzione')){
                return 'Non ho capito tutte le informazioni. Riprendi il formato Partenza "Stazione", Ora "HH:MM", Direzione "Terminus"';
            }
            $time = strtotime(substr($content['entities']['datetime'][0]['value'], 11, 8));
            $mintime = '';
            try {
                $taille_tab = count($content['entities']['datetime'][0]['values']);
            } catch (Exception $e) {
                return 'Non ho capito tutte le informazioni. Riprendi il formato Partenza "Stazione", Ora "HH:MM", Direzione "Terminus"';
            }
            for ($i = 1; $i < $taille_tab; $i++) {

                if ($time > strtotime(substr($content['entities']['datetime'][0]['values'][$i]['value'], 11, 8))) {
                    $time = strtotime(substr($content['entities']['datetime'][0]['values'][$i]['value'], 11, 8));
                    $mintime = substr($content['entities']['datetime'][0]['values'][$i]['value'], 11, 8);
                }
            }
            if ($mintime === '')
                $mintime = substr($content['entities']['datetime'][0]['value'], 11, 8);
            $depart = trim(str_replace('"', '', substr($string, 8, strrpos(strtolower($string), 'ora', 0) - 10)));
            $direction = trim(str_replace('"', '', substr($string, strrpos(strtolower($string), 'direzione', 0) + 9)));


            $tempstheo = $this->getintervalle_ma($depart, $direction, $mintime);
            if ($tempstheo === 'error')
                return 'Spiacenti, al momento questa informazione non è disponibile';
            // Le prochain devrait être à HH MM.
            else
                return 'Salvo ritardi, in questa fascia oraria c\'è un tram ogni ' . $tempstheo[0] . ' minuti.Il prossimo dovrebbe essere alle '. $tempstheo[1];

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
                    $intent = 'prix';
                    break;
                case "5" :
                    $intent = 'service client';
                    break;
                case "6" :
                    $intent = 'service client';
                    break;

            }
        }


        if (isset ($content['entities']['intent'][0]['value'])){
            $intent = $content['entities']['intent'][0]['value'];
        }
        elseif ($intent === '')
            return 'Non ho compreso la sua domanda
Posso fornirle le seguenti informazioni
1- Orari del tram
2- Itinerario
3- La fermata più vicina
4- Tariffe e abbonamenti
5- Servizio clienti (Rimborsi; oggetti smarriti)
6- Reclami
Se una di queste proposte corrisponde alla sua richiesta, proceda con la scelta
Se nessuna di queste proposte corrisponde alla sua richiesta, può contattare il servizio clienti al numero gratuito 800.964424 (solo da fisso) o al numero a pagamento 199.229300 (solo da cellulare), oppure visitare il nostro sito www.gestramvia.com';

        switch ($intent) {
            case "salutation":
                if ($cache->hasItem('nb_user_contact')) {
                    $msgCount = $cache->getItem('nb_user_contact')->set($cache->getItem('nb_user_contact')->get() + 1);
                    //  $this->session->set('nb_user_contact', $this->session->get('nb_user_contact') + 1);
                } else {
                    $msgCount = $cache->getItem('nb_user_contact')->set(1);
                }
                $cache->save($msgCount);
                $newCount = $cache->getItem('nb_user_contact');
                $newCount->set($this->session->get('nb_user_contact'));
                $cache->save($newCount);
                return 'Buongiorno, sono Sirio, l\'assistente virtuale GEST 🤖 . Come posso aiutarla ? 🙂';


            case "aller":
                return 'Dove vuole andare? Grazie di rispondere in questo formato: destinazione "Luogo"';

            case 'station_proche':
                return 'In quale zona vi trovate? Grazie di inserire la risposta in questo formato: sono in zona "Nome della zona"';

            case "horaire":
                return 'Grazie per specificare la sua fermata di partenza, l\'ora e la direzione. Può scrivere così: Partenza "Nome fermata", Ora "HH:MM", Direzione "Capolinea"';

            case "book":
                return "Potete comprare in anticipo i biglietti tramite la app Nugo, oppure usufruire del servizio prenotazione del parcheggio di Villa Costanza https://www.parcheggiovillacostanza.it/it/parcheggio-villa-costanza/prenotazioni-gruppi/";

            case "disabled":
                return "Troverà tutte le informazioni alla pagina della società ATAF http://www.ataf.net/it/biglietti-e-abbonamenti.aspx?idC=20&LN=it-IT";

            case "group":
                return "Non ci risulta che esistano biglietti scontati per gruppi. Può comunque avere tutte le informazioni sui titoli di viaggio alla pagina della società ATAF http://www.ataf.net/it/biglietti-e-abbonamenti.aspx?idC=20&LN=it-IT";

            case 'service client':
                return "E' possibile chiamare, dal numero fisso e in modo gratuito, il numero 800.964424 (Attivo solo da numero fisso). Con il cellulare è possibile chiamare, a pagamento, il numero 199.229300. Attivo tutti i giorni dalle 7 alle 20. Oppure può contattarci tramite il modulo online su https://www.gestramvia.com/modulo-contatto";

            case 'pièces':
                return "Troverà uttti i dettagli nella pagina della società ATAF http://www.ataf.net/it/biglietti-e-abbonamenti/abbonamenti/mensile-ordinario.aspx?idC=83&IdCat=5815&idO=5853&LN=it-IT";

            case 'prix':
                if (isset ($content['entities']['type_produit'][0]['value'])) {
                    $intent = $content['entities']['type_produit'][0]['value'];
                    switch ($intent) {
                        case 'abbonamento studenti':
                            return "Troverà tutte le informazioni alla pagina della società ATAF http://www.ataf.net/it/biglietti-e-abbonamenti.aspx?idC=20&LN=it-IT";
                        case 'aeroporto':
                            return "Il costo del biglietto per l'aeroporto è quello di un normale biglietto di corsa singola, € 1,50. Tutti i dettagli su dove acquistare i biglietti su  https://www.gestramvia.com/biglietti";
                        case 'Carta Unica':
                            return "Troverà uttti i dettagli nella pagina dedicata a Carta Unica https://ataf.fsbusitaliashop.it/carta-unica";
                        case 'biglietto':
                            return "Il costo del biglietto di corsa singola, € 1,50. Tutti i dettagli su dove acquistare i biglietti su  https://www.gestramvia.com/biglietti";
                    }
                } else {
                    return "Può acquistare il biglietto presso le emettitrici presenti in fermata, tramite la app Nugo, tramiteSMS, oppure presso le rivendite autorizzate (elenco presente in banchina). Trova tutti i dettagli su https://www.gestramvia.com/biglietti";


                }

            case 'remerciement':
                $repository = $this->em->getRepository(Phone::class);
                $phoneaccepted = $repository->findOneBy(array('phone' => $phone, 'asked_notif' => false));
                if ($phoneaccepted) {
                    $return_msg = 'Sirio al suo servizio! Vuole ricevere delle informazioni sul servizio via Whatsapp? Risponda "Si" o "No"';
                    $this->session->set('last_resp', 'ask permission to send notification');
                    return $return_msg;
                }

                return 'Sirio 🤖 al suo servizio! 😉';

            case 'accepter':
                if ($this->session->get('last_resp') === 'ask permission to send notification') {
                    $this->enable_notif_auto($phone);
                    $this->confirm_notif($phone);
                    $this->session->remove('last_resp');
                    return ' Grazie per la sua risposta. Riceverà dei messaggi ✉ su whatsapp per informazioni in merito a perturbazioni del servizio o altro. Sirio 🤖 al suo servizio! 😉';
                }
                break;
            case 'refuser':
                if ($this->session->get('last_resp') === 'ask permission to send notification') {
                    return '\'Grazie per la sua risposta. Non esiti a contattare nuovamente Sirio se ha bisogno. Sirio 🤖 al suo servizio! 😉';
                }
                break;


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

    public function confirm_notif($phone)
    {
        $repository = $this->em->getRepository(Phone::class);
        $ph = $repository->findOneBy(array('phone' => $phone));
        if ($ph) {
            $ph->setAskennotif(true);
            $this->em->flush();
        }


    }

    public function Sendnotif(Request $request)
    {
        try {
            $hour = $request->get('hour');
            $minute = $request->get('minute');
            $message = $request->get('message');
            $notif = new Sendnotif();
            $notif->setMessage($message);
            if ( $request->files->get('file') ) {
                $filename=$request->files->get('file')->getClientOriginalName();
               $request->files->get('file')->move('files',$filename);
                $notif->setUrl($_SERVER['DOCUMENT_ROOT'] .'files/'.$filename );
                }
            $this->em->persist($notif);
            $this->em->flush();
            $hour = $hour < 10 ? '0' . $hour : $hour;
            $minute = $minute < 10 ? '0' . $minute : $minute;
            $process = new Process('at' . $hour . ':' . $minute . ' ' . str_replace('-', '/', $request->get('date')));
            $process->run();
            $process = new Process('php bin/console sendnotif ' . $notif->getId());
            $process->run();
        } catch (Exception $e) {
      return $e->getMessage();
        }
        return 'success';
    }

    public function Getphones(): array
    {
        $repository = $this->em->getRepository(Phone::class);
        $phones = $repository->findBy(array('notif_auto' => true));
        $phoneslist=array();
        foreach ($phones as $phone) {
            $phoneslist[] = $phone->getPhone();
        }
        return $phoneslist;
    }

    public function getdataperhour()
    {
        $conn = $this->em->getConnection();
        $reports = $conn->fetchAll("SELECT * FROM reporting_heure  ORDER BY date DESC ;  ");
        return $reports;
    }

    public function getdataperday()
    {
        $conn = $this->em->getConnection();
        $reports = $conn->fetchAll("SELECT * FROM reporting_jour  ORDER BY date LIMIT 30;  ");
        return $reports;


    }

    public function getdataperweek()
    {
        $conn = $this->em->getConnection();
        $reports = $conn->fetchAll("SELECT * FROM reporting_semaine  ;  ");
        return $reports;


    }

    public function getdatapermonth()
    {
        $conn = $this->em->getConnection();
        $reports = $conn->fetchAll("SELECT * FROM reporting_mois ;  ");
        return $reports;


    }

    public function getdataperdate($start, $end)
    {


        $conn = $this->em->getConnection();
        $reports = $conn->fetchAll("SELECT * FROM reporting_jour  WHERE date >= ? AND date <= ?", array($start, $end));
        return $reports;
    }

    public function deleteuser($id)
    {

        try {
            $user = $this->usermanager->findUserBy(['id' => $id]);
            $this->usermanager->deleteUser($user);
            return true;
        } catch (Exception $e) {
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
            return false;
        }
    }

    public function getusers()
    {
        $repository = $this->em->getRepository(User::class);
        $user = $repository->findAll();
        $phoneslist=array();
        foreach ($user as $u) {
            $phoneslist[] = array($u->getId(), $u->getUsername(), $u->getEmail(), $u->getLastLogin());
        }
        return $phoneslist;
    }

    public function getnearestplace($placetogo, $filename, $regioncode)
    {
        $client = HttpClient::create();
        $response = $client->request('GET', 'https://maps.googleapis.com/maps/api/geocode/json', ['query' => ['region' => $regioncode, 'address' => $placetogo, 'key' => $_ENV['google_map_key']]]);
        $data = json_decode($response->getContent(), true);
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


    public function getintervalle_ma($d, $dir, $time)
    {
        $max_similarity_dep = 0;
        $max_similarity_fin = 0;
        $depart = '';
        $direction = '';

        $T1 = array("Unita", "Rosselli", "Belfiore", "Redi", "Ponte asse", "Buonsignor", "Sandonato", "Regione", "Torre degli agli", "Palazzi rossi", "Guidoni", "Aeroporto");
        $T2 = array("Villa Costanza", "de andre", "resistanza", "aldo moro", "neni torregalli", "arcipressi", "deferiga", "talenti", "batoni", 'sansovino', 'paolo uccello', 'cascine', 'porta al prato', 'alamanni stazione', 'valfonda stazione', 'fortezza-fiera', 'strozzi-fallaci', 'statuto', 'muratori-stazione', 'leopoldo', 'pisacane', 'dalmazia', 'morgagni', 'careggi-ospedale');
        foreach ($T1 as $location) {
            $similar_text_depart = similar_text(strtolower($location), strtolower($d));
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
            $similar_text_depart = similar_text(strtolower($location), strtolower($d));
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
                $depart = 'Unita';
                $direction = 'Aeroporto';
            } else {
                $depart = 'Aeroporto';
                $direction = 'Unita';
            }

        } elseif (array_keys($T2, $depart) && array_keys($T2, $direction)) {

            if (array_keys($T2, $depart)[0] < array_keys($T2, $direction)[0]) {
                $depart = 'Villa Costanza';
                $direction = 'careggi-ospedale';
            } else {
                $depart = 'careggi-ospedale';
                $direction = 'Villa Costanza';
            }
        } else {
            return 'error';
        }
        $ss = ChatbotService::dateToFrench("now", "l");
        $oldLocale = setlocale(LC_TIME, 'it_IT');
        $jours = ['lunedì', 'martedì', 'mercoledì', 'giovedì', 'venerdì', 'sabato', 'domenica'];
        $reports = $this->temprepo->findintervalle($jours[date('N')-1], $time, $depart, $direction);
        if (isset($reports[0])) {
            $result= new \DateTime($time);
            $temp_theo = $reports[0]->getIntervalle()->format('i');
            $d=new \DateInterval('PT'.$temp_theo.'M');
            $result->add($d) ;
            return array($temp_theo,$result->format('H:i'));
        } else
            return array('error');
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
            $data = json_decode($request->getContent(), true);
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
        $timelist = [];
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
            $data = json_decode($request->getContent(), true);
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
        $timelist = [];
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
