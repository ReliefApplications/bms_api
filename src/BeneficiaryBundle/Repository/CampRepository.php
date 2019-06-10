<?php

namespace BeneficiaryBundle\Repository;
use CommonBundle\Entity\Location;

/**
 * CampRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class CampRepository extends \Doctrine\ORM\EntityRepository
{

    public function findByAdm1($adm1Id) {
        $qb = $this->createQueryBuilder('c')
            ->leftJoin('c.location', 'l');

        $locationRepository = $this->getEntityManager()->getRepository(Location::class);
        $locationRepository->getAdm1($qb);

        $qb->orWhere('adm1.id = :adm1Id')
            ->setParameter('adm1Id', $adm1Id);
        return $qb->getQuery()->getResult();
    }

    public function findByAdm2($adm2Id) {
        $qb = $this->createQueryBuilder('c')
            ->leftJoin('c.location', 'l');

        $locationRepository = $this->getEntityManager()->getRepository(Location::class);
        $locationRepository->getAdm2($qb);

        $qb->orWhere('adm2.id = :adm2Id')
            ->setParameter('adm2Id', $adm2Id);
        return $qb->getQuery()->getResult();
    }

    public function findByAdm3($adm3Id) {
        $qb = $this->createQueryBuilder('c')
            ->leftJoin('c.location', 'l');

        $locationRepository = $this->getEntityManager()->getRepository(Location::class);
        $locationRepository->getAdm3($qb);

        $qb->orWhere('adm3.id = :adm3Id')
            ->setParameter('adm3Id', $adm3Id);
        return $qb->getQuery()->getResult();
    }

    public function findByAdm4($adm4Id) {
        $qb = $this->createQueryBuilder('c')
            ->leftJoin('c.location', 'l')
            ->leftJoin('l.adm4', 'adm4')
            ->orWhere('adm4.id = :adm4Id')
            ->setParameter('adm4Id', $adm4Id);
        return $qb->getQuery()->getResult();
    }
}
