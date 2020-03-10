<?php


namespace App\Service;

use Doctrine\DBAL\DBALException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class Reportinghourly extends Command
{

    private $session ;
    private $em ;

    public function __construct(EntityManagerInterface $em, SessionInterface $session)
    {
        parent::__construct();
        $this->em = $em;
        $this->session = $session;
    }

    public function configure()
     {
         $this->setName('cron:job');
     }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $handle = fopen(__DIR__ . "/reporting.csv", "r");
        $contents = fread($handle, filesize(__DIR__ . "/reporting.csv"));
        fclose($handle);
        $x = new \DateTime();
        $output->writeln('hello cron !'.var_dump($contents));
        $this->em->getConnection()->insert('reporting_heure', array('nb_nouv_user' => $contents,
            'nb_msg_user' => $contents,
            'nb_user_contact' => $contents,
            'date' => $x->format('Y-m-d H:i:s')));

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
        $this->session->clear();
    }




}