<?php


namespace App\Service;

use Doctrine\DBAL\DBALException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class ChatbotReporting
{

    public function __construct(EntityManagerInterface $em, SessionInterface $session)
    {
        $this->em = $em;
        $this->session = $session;
    }

    public function reporting_parheure()
    {
        try {
            $conn = $this->em->getConnection();
            $x = new \DateTime();
            $x->format('d-m-Y H:i:s');
            $conn->insert('reporting_heure', array('nb_nouv_user' => $this->session->get('nb_nouv_user'),
                'nb_msg_user' => $this->session->get('nb_msg_user'),
                'nb_user_contact' => $this->session->get('nb_user_contact'),
                'date' => $x->format('d-m-Y H:i:s')));
        } catch (DBALException $e) {
            dd($e);
        }
        $this->session->remove('nb_nouv_user');
        $this->session->remove('nb_msg_user');
        $this->session->remove('nb_user_contact');
    }

    public function reporting_parjour(){
        $nb_nouv_user=0;
        $nb_msg_user=0;
        $nb_user_contact=0;
        $conn = $this->em->getConnection();
        $reports=$conn->fetchAll("SELECT * FROM reporting_heure  ORDER BY date DESC LIMIT 24;  ");
        foreach ($reports as $reprt){
            $nb_nouv_user+=$reprt['nb_nouv_user'];
            $nb_msg_user+= $reprt['nb_msg_user'];
            $nb_user_contact+= $reprt['nb_user_contact'];
        }
        $x = new \DateTime();
        $conn->insert('reporting_jour', array('nb_nouv_user' =>$nb_nouv_user,
            'nb_msg_user' => $nb_msg_user,
            'nb_user_contact' => $nb_user_contact,
            'date' => $x->format('d-m-Y H:i:s')));
    }

    public function reporting_parsemaine(){
        $nb_nouv_user=0;
        $nb_msg_user=0;
        $nb_user_contact=0;
        $conn = $this->em->getConnection();
        $reports=$conn->fetchAll("SELECT * FROM reporting_jour  ORDER BY date DESC LIMIT 7;  ");
        foreach ($reports as $reprt){
            $nb_nouv_user+=$reprt['nb_nouv_user'];
            $nb_msg_user+= $reprt['nb_msg_user'];
            $nb_user_contact+= $reprt['nb_user_contact'];
        }
        $x = new \DateTime();
        $conn->insert('reporting_semaine', array('nb_nouv_user' =>$nb_nouv_user,
            'nb_msg_user' => $nb_msg_user,
            'nb_user_contact' => $nb_user_contact,
            'date' => $x->format('d-m-Y H:i:s')));
    }



    public function reporting_parmois(){
        $nb_nouv_user=0;
        $nb_msg_user=0;
        $nb_user_contact=0;
        $conn = $this->em->getConnection();
        $reports=$conn->fetchAll("SELECT * FROM reporting_semaine  ORDER BY date DESC LIMIT 4;  ");
        foreach ($reports as $reprt){
            $nb_nouv_user+=$reprt['nb_nouv_user'];
            $nb_msg_user+= $reprt['nb_msg_user'];
            $nb_user_contact+= $reprt['nb_user_contact'];
        }
        $x = new \DateTime();
        $conn->insert('reporting_mois', array('nb_nouv_user' =>$nb_nouv_user,
            'nb_msg_user' => $nb_msg_user,
            'nb_user_contact' => $nb_user_contact,
            'date' => $x->format('d-m-Y H:i:s')));
    }


   

}