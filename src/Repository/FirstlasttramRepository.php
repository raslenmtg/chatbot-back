<?php

namespace App\Repository;

use App\Entity\Firstlasttram;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Persistence\ManagerRegistry;

/**
 * @method Firstlasttram|null find($id, $lockMode = null, $lockVersion = null)
 * @method Firstlasttram|null findOneBy(array $criteria, array $orderBy = null)
 * @method Firstlasttram[]    findAll()
 * @method Firstlasttram[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class FirstlasttramRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Firstlasttram::class);
    }

    // /**
    //  * @return Firstlasttram[] Returns an array of Firstlasttram objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('f.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?Firstlasttram
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
