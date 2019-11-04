<?php


namespace App\Service;


use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoder;

class UserService
{

    const C_KEY_ARRAY_USERNAME = 'username';
    const C_FOS_USER_MANAGER = 'fos_user.user_manager';


    public function __construct(


        EntityManagerInterface $i__entityManager,
        ContainerInterface $i__container)
    {
        $this->em = $i__entityManager;
        $this->container = $i__container;
    }

    /**
     * Checks user credentials
     */
    public function toCheckCredentials(UserPasswordEncoder $i__encoder, $i__username, $i__password){
        $l__user = $this->em->getRepository(User::class)->findOneBy(array('username'=>$i__username));
        if (!$l__user || !$i__encoder->isPasswordValid($l__user, $i__password)) {
            return 'invalid';
        } else {
            return $l__user;
        }
    }
    /**
     * Generates a JsonWebToken from user.
     */
    public function toGenerateToken($i__user)
    {// Call the jwt_manager service & create the token
        return $this->container->get('lexik_jwt_authentication.jwt_manager')->create($i__user);
    }






}
