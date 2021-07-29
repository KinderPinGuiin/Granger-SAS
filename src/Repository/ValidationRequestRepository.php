<?php

namespace App\Repository;

use App\Entity\ValidationRequest;
use App\Utils\Constants;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method ValidationRequest|null find($id, $lockMode = null, $lockVersion = null)
 * @method ValidationRequest|null findOneBy(array $criteria, array $orderBy = null)
 * @method ValidationRequest[]    findAll()
 * @method ValidationRequest[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ValidationRequestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ValidationRequest::class);
    }

    public function getNotHandledRequest()
    {
        return $this->getEntityManager()->createQuery(
            "
                SELECT request
                FROM App\Entity\ValidationRequest request, 
                     App\Entity\User user
                WHERE user.id = request.user
                      AND user.status = :status
            "
        )->setParameter("status", Constants::VERIFICATION_STATUS)->getResult();
    }

    // /**
    //  * @return ValidationRequest[] Returns an array of ValidationRequest objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('v')
            ->andWhere('v.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('v.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?ValidationRequest
    {
        return $this->createQueryBuilder('v')
            ->andWhere('v.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
