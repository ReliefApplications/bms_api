<?php

declare(strict_types=1);

namespace BeneficiaryBundle\Utils\DataVerifier;

use BeneficiaryBundle\Entity\Household;
use BeneficiaryBundle\Entity\HouseholdLocation;
use Doctrine\ORM\EntityManagerInterface;
use JMS\Serializer\SerializationContext;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ExistingHouseholdVerifier extends AbstractVerifier
{
    private $token;

    private $container;

    public function __construct(EntityManagerInterface $entityManager, ContainerInterface $container, string $token)
    {
        parent::__construct($entityManager);
        $this->token = $token;
        $this->container = $container;
    }

    /**
     * @throws \Exception
     */
    public function verify(string $countryISO3, array &$householdArray, int $cacheId, string $email)
    {
        if (empty($householdArray['beneficiaries']) && empty($householdArray['household_locations'])) {
            throw new \Exception('Beneficiaries or location missing in household');
        }

        $locations       = $householdArray['household_locations'];
        $currentLocation = null;
        $beneficiaries   = $householdArray['beneficiaries'];
        $headOfHousehold = null;

        foreach ($locations as $location) {
            if ($location['location_group'] === HouseholdLocation::LOCATION_GROUP_CURRENT) {
                $currentLocation = $location;
            }
        }

        foreach ($beneficiaries as $beneficiary) {
            if ($beneficiary['status'] === 1) {
                $headOfHousehold = $beneficiary;
            }
        }

        if (! $headOfHousehold || ! $currentLocation) {
            throw new \Exception('Household has no head or no current location');
        }

        $existingHousehold = $this->em->getRepository(Household::class)->getByHeadAndLocation(
            $headOfHousehold['local_given_name'],
            $headOfHousehold['local_family_name'],
            $currentLocation['type'],
            $currentLocation['address']['street'] ?? null,
            $currentLocation['address']['number'] ?? null,
            $currentLocation['camp_address']['tent_number'] ?? null
        );

        if (empty($existingHousehold)) {
            $this->saveInCache('to_create', $cacheId, $householdArray, $email, null);
        }
        else {
            $this->saveInCache('to_update', $cacheId, $householdArray, $email, $existingHousehold);
        }
    }

    /**
     * @throws \Exception
     */
    private function saveInCache(string $step, int $cacheId, array $dataToSave, string $email, Household $household = null)
    {
        if (! empty($household)) {
            $arrayOldHousehold = json_decode(
                $this->container->get('jms_serializer')->serialize(
                    $household,
                    'json',
                    SerializationContext::create()->setSerializeNull(true)->setGroups(['FullHousehold'])
                ),
                true
            );
        } else {
            $arrayOldHousehold = [];
        }

        $sizeToken = 50;
        if (null === $this->token) {
            $this->token = bin2hex(random_bytes($sizeToken));
        }

        $dirRoot = $this->container->get('kernel')->getRootDir();

        $dirVar = $dirRoot . '/../var/data';
        if (!is_dir($dirVar)) {
            mkdir($dirVar);
        }

        $dirVarToken = $dirVar . '/' . $this->token;
        if (!is_dir($dirVarToken)) {
            mkdir($dirVarToken);
        }

        $dirVar = $dirVarToken . '/' . $email . '-' . $step;
        if (is_file($dirVar)) {
            $listHH = json_decode(file_get_contents($dirVar), true);
        } else {
            $listHH = [];
        }

        $listHH[$cacheId] = ['new' => $dataToSave, 'old' => $arrayOldHousehold, 'id_tmp_cache' => $cacheId];
        file_put_contents($dirVar, json_encode($listHH));
    }
}
