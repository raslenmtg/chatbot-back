<?php

namespace App\Repository;

use App\Entity\TempTh;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Persistence\ManagerRegistry;


/**
 * @method TempTh|null find($id, $lockMode = null, $lockVersion = null)
 * @method TempTh|null findOneBy(array $criteria, array $orderBy = null)
 * @method TempTh[]    findAll()
 * @method TempTh[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TempThRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TempTh::class);
    }

    public function findintervalle($jour,$heure,$depart,$arrive){
        return $this->getEntityManager()->createQueryBuilder()->select('t')->from('App\Entity\TempTh','t')
            ->andWhere(':hour >= t.h_depart and :hour <=t.h_fin')
            ->andWhere('t.depart=:depart and t.arrive=:arrive and t.jour = :val')
            ->setParameters(array('hour'=>$heure,'depart'=>$depart,'arrive'=>$arrive,'val'=>$jour))
            ->getQuery()
            ->getResult()
            ;

    }

    // /**
    //  * @return TempTh[] Returns an array of TempTh objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('t.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?TempTh
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
