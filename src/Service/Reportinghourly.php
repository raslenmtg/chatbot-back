<?php


namespace App\Service;


use App\Entity\Phone;
use App\Entity\Sendnotif;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Twilio\Exceptions\ConfigurationException;
use Twilio\Exceptions\TwilioException;
use Twilio\Rest\Client;

class Reportinghourly extends Command
{

    private $em;

    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct();
        $this->em = $em;
    }

    public function configure()
    {
        $this->addArgument('time');
        $this->setDescription('Reporting Hourly Created');
        $this->setName('cron:job');
        $this->addArgument('id', InputArgument::OPTIONAL);
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        date_default_timezone_set('Africa/Casablanca');
        //  var_dump($input);
        //  var_dump($input->getArgument('time'));
        if ($input->getArgument('time') === 'hourly') {
            $cache = new FilesystemAdapter();
            // $productsCount = $cache->getItem('nb_msg_user');
            // $productsCount->set(4711);
            //$cache->save($productsCount);
            $nb_msg_user = $cache->getItem('nb_msg_user')->get();
            $nb_nouv_user = $cache->getItem('nb_nouv_user')->get();
            $nb_user_contact = $cache->getItem('nb_user_contact')->get();
            //  var_dump($cache->getItem('nb_msg_user')->get());
            $x = new \DateTime();
            $this->em->getConnection()->insert('reporting_heure', array('nb_nouv_user' => $nb_nouv_user,
                'nb_msg_user' => $nb_msg_user,
                'nb_user_contact' => $nb_user_contact,
                'date' => $x->format('Y-m-d H:i:s')));
            $cache->clear();
            $output->writeln('Created Succefully');
            return 0;
        }

        if ($input->getArgument('time') === 'daily') {
            $nb_nouv_user = 0;
            $nb_msg_user = 0;
            $nb_user_contact = 0;
            $conn = $this->em->getConnection();
            $reports = $conn->fetchAll("SELECT * FROM reporting_heure  ORDER BY date DESC LIMIT 24;  ");
            foreach ($reports as $reprt) {
                $nb_nouv_user += $reprt['nb_nouv_user'];
                $nb_msg_user += $reprt['nb_msg_user'];
                $nb_user_contact += $reprt['nb_user_contact'];
            }
            $x = new \DateTime();
            $conn->insert('reporting_jour', array('nb_nouv_user' => $nb_nouv_user,
                'nb_msg_user' => $nb_msg_user,
                'nb_user_contact' => $nb_user_contact,
                'date' => $x->format('Y-m-d H:i:s')));
            $output->writeln('Created Succefully');
            return 0;
        }
        if ($input->getArgument('time') === 'weekly') {
            $nb_nouv_user = 0;
            $nb_msg_user = 0;
            $nb_user_contact = 0;
            $conn = $this->em->getConnection();
            $reports = $conn->fetchAll("SELECT * FROM reporting_jour  ORDER BY date DESC LIMIT 7;  ");
            foreach ($reports as $reprt) {
                $nb_nouv_user += $reprt['nb_nouv_user'];
                $nb_msg_user += $reprt['nb_msg_user'];
                $nb_user_contact += $reprt['nb_user_contact'];
            }
            $x = new \DateTime();
            $conn->insert('reporting_semaine', array('nb_nouv_user' => $nb_nouv_user,
                'nb_msg_user' => $nb_msg_user,
                'nb_user_contact' => $nb_user_contact,
                'date' => $x->format('d-m-Y H:i:s')));
            $output->writeln('Created Succefully');
            return 0;
        }

        if ($input->getArgument('time') === 'monthly') {

            $nb_nouv_user = 0;
            $nb_msg_user = 0;
            $nb_user_contact = 0;
            $conn = $this->em->getConnection();
            $reports = $conn->fetchAll("SELECT * FROM reporting_semaine  ORDER BY date DESC LIMIT 4;  ");
            foreach ($reports as $reprt) {
                $nb_nouv_user += $reprt['nb_nouv_user'];
                $nb_msg_user += $reprt['nb_msg_user'];
                $nb_user_contact += $reprt['nb_user_contact'];
            }
            $x = new \DateTime();
            $conn->insert('reporting_mois', array('nb_nouv_user' => $nb_nouv_user,
                'nb_msg_user' => $nb_msg_user,
                'nb_user_contact' => $nb_user_contact,
                'date' => $x->format('d-m-Y H:i:s')));
            $output->writeln('Created Succefully');
            return 0;
        }
        if ($input->getArgument('time') === 'sendnotif') {

            $repository = $this->em->getRepository(Sendnotif::class);
            $notif = $repository->find($input->getArgument('id'));
            $from = $_ENV['TWILIO_PHONE_NUMBER'];
            $files_exist = $notif->getUrl() === '';
            $repository = $this->em->getRepository(Phone::class);
            $phoneslist = $repository->findBy(['notif_auto' => true]);
            try {
                $twilio = new Client($_ENV['TWILIO_SID'], $_ENV['TWILIO_AUTH']);
            } catch (ConfigurationException $e) {
            }
            foreach ($phoneslist as $phone) {
                if ($files_exist) {
                    try {
                        $twilio->messages
                            ->create($phone->getPhone(), // to
                                array(
                                    'body' => $notif->getMessage(),
                                    'from' => $from
                                )
                            );
                    } catch (Exception $e) {
                    }
                } else {
                    try {
                        $twilio->messages
                            ->create($phone->getPhone(), // to
                                array(
                                    'body' => $notif->getMessage(),
                                    'from' => $from,
                                    'mediaUrl' => $notif->getUrl()
                                )
                            );
                    } catch (Exception $e) {
                 
                    }
                }
            }
            if (!$files_exist) {
                unlink($notif->getUrl());
            }
        }
        $output->writeln('Wrong parameter');
        return 1;

    }

}
