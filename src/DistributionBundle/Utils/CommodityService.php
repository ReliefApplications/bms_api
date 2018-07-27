<?php


namespace DistributionBundle\Utils;


use DistributionBundle\Entity\Commodity;
use DistributionBundle\Entity\DistributionData;
use DistributionBundle\Entity\ModalityType;
use Doctrine\ORM\EntityManagerInterface;

class CommodityService
{

    /** @var EntityManagerInterface $em */
    private $em;


    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->em = $entityManager;
    }

    public function create(DistributionData $distribution, array $commodityArray, bool $flush)
    {
        $commodity = new Commodity();
        $commodity->setValue($commodityArray["value"])
            ->setDistributionData($distribution)
            ->setUnit($commodityArray["unit"])
            ->setModalityType(
                $this->em->getRepository(ModalityType::class)
                    ->find($commodityArray["modality_type"]["id"])
            );

        $this->em->persist($commodity);

        if ($flush)
            $this->em->flush();

        return $commodity;
    }
}