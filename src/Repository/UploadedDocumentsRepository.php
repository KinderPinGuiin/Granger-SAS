<?php

namespace App\Repository;

use App\Entity\UploadedDocuments;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;

/**
 * @method UploadedDocuments|null find($id, $lockMode = null, $lockVersion = null)
 * @method UploadedDocuments|null findOneBy(array $criteria, array $orderBy = null)
 * @method UploadedDocuments[]    findAll()
 * @method UploadedDocuments[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UploadedDocumentsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UploadedDocuments::class);
    }

    /**
     * Récupère les documents uploadés par un utilisateur et les classe par slug
     */
    public function getUploadedDocsSlugs($user): array
    {
        $em = $this->getEntityManager();
        $results = $em->createQuery(
            "
                SELECT documents.slug, uploaded_documents
                FROM App\Entity\Documents documents, 
                     App\Entity\UploadedDocuments uploaded_documents
                WHERE documents.id = uploaded_documents.document
                      AND uploaded_documents.user = :user
            "
        )->setParameter("user", $user)->getResult();
        $array = [];
        foreach ($results as $result) {
            $array[$result["slug"]] = $result[0];
        }

        return $array;
    }

    // /**
    //  * @return UploadedDocuments[] Returns an array of UploadedDocuments objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('u.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?UploadedDocuments
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
