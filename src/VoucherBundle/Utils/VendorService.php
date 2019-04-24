<?php

namespace VoucherBundle\Utils;

use CommonBundle\Entity\Logs;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use VoucherBundle\Entity\Vendor;
use UserBundle\Entity\User;
use JMS\Serializer\Serializer;
use Psr\Container\ContainerInterface;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\MimeType\FileinfoMimeTypeGuesser;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use DateTime;
use CommonBundle\Utils\LocationService;

class VendorService
{

  /** @var EntityManagerInterface $em */
    private $em;

    /** @var ValidatorInterface $validator */
    private $validator;

    /** @var ContainerInterface $container */
    private $container;

    /** @var LocationService $locationService */
    private $locationService;

    /**
     * UserService constructor.
     * @param EntityManagerInterface $entityManager
     * @param ValidatorInterface $validator
     * @param ContainerInterface $container
     * @param LocationService $locationService
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        ValidatorInterface $validator,
        LocationService $locationService,
        ContainerInterface $container
    ) {
        $this->em = $entityManager;
        $this->validator = $validator;
        $this->container = $container;
        $this->locationService = $locationService;
    }

    /**
     * Creates a new Vendor entity
     *
     * @param array $vendorData
     * @return mixed
     * @throws \Exception
     */
    public function create($countryISO3, array $vendorData)
    {
        $username = $vendorData['username'];
        $userSaved = $this->em->getRepository(User::class)->findOneByUsername($username);
        $vendorSaved = $userSaved instanceof User ? $this->em->getRepository(Vendor::class)->getVendorByUser($userSaved) : null;

        if (!($vendorSaved instanceof Vendor)) {
            $userSaved = $this->em->getRepository(User::class)->findOneByUsername($vendorData['username']);
            $user = $this->container->get('user.user_service')->create(
        $userSaved,
        [
          'roles' => ['ROLE_VENDOR'],
          'salt' => $vendorData['salt'],
          'password' => $vendorData['password']
        ]
      );

      $location = $vendorData['location'];
      $location = $this->locationService->getOrSaveLocation($countryISO3, $location);


            $vendor = new Vendor();
            $vendor->setName($vendorData['name'])
                    ->setShop($vendorData['shop'])
                    ->setAddressStreet($vendorData['address_street'])
                    ->setAddressNumber($vendorData['address_number'])
                    ->setAddressPostcode($vendorData['address_postcode'])
                    ->setLocation($location)
                    ->setArchived(false)
                    ->setUser($user);

            $this->em->persist($vendor);
            $this->em->flush();

            $createdVendor = $this->em->getRepository(Vendor::class)->findOneByUser($user);
            return $createdVendor;
        } else {
            throw new \Exception('A vendor with this username already exists.');
        }
    }

    /**
     * Returns all the vendors
     *
     * @return array
     */
    public function findAll()
    {
        $vendors = $this->em->getRepository(Vendor::class)->findByArchived(false);
        return $vendors;
    }


    /**
     * Updates a vendor according to $vendorData
     *
     * @param Vendor $vendor
     * @param array $vendorData
     * @return Vendor
     */
    public function update($countryISO3, Vendor $vendor, array $vendorData)
    {
        try {
            $user = $vendor->getUser();
            foreach ($vendorData as $key => $value) {
                if ($key == 'name') {
                    $vendor->setName($value);
                } elseif ($key == 'shop') {
                    $vendor->setShop($value);
                } elseif ($key == 'address_street') {
                    $vendor->setAddressStreet($vendorData['address_street']);
                } elseif ($key == 'address_number') {
                    $vendor->setAddressNumber($vendorData['address_number']);
                } elseif ($key == 'address_postcode') {
                    $vendor->setAddressPostcode($vendorData['address_postcode']);
                } elseif ($key == 'username') {
                    $user->setUsername($value);
                } elseif ($key == 'password' && !empty($value)) {
                    $user->setPassword($value);
                } elseif ($key == 'location' && !empty($value)) {
                    $location = $value;
                    if (array_key_exists('id', $location)) {
                        unset($location['id']); // This is the old id
                    }
                    $location = $this->locationService->getOrSaveLocation($countryISO3, $location);
                    $vendor->setLocation($location);
                }
            }
            $this->em->merge($vendor);
            $this->em->flush();
        } catch (\Exception $e) {
            throw new \Exception('Error updating Vendor');
        }

        return $vendor;
    }


    /**
     * Archives Vendor
     *
     * @param Vendor $vendor
     * @param bool $archiveVendor
     * @return Vendor
     * @throws \Exception
     */
    public function archiveVendor(Vendor $vendor, bool $archiveVendor = true)
    {
        try {
            $vendor->setArchived($archiveVendor);
            $this->em->merge($vendor);
            $this->em->flush();
        } catch (\Exception $exception) {
            throw new \Exception('Error archiving Vendor');
        }
        return $vendor;
    }


    /**
     * Permanently deletes the record from the database
     *
     * @param Vendor $vendor
     * @param bool $removeVendor
     * @return bool
     */
    public function deleteFromDatabase(Vendor $vendor, bool $removeVendor = true)
    {
        if ($removeVendor) {
            try {
                $this->em->remove($vendor);
                $this->em->flush();
            } catch (\Exception $exception) {
                return $exception;
            }
        }
        return true;
    }

    /**
       * @param User $user
       * @throws \Exception
       */
    public function login(User $user)
    {
        $vendor = $this->em->getRepository(Vendor::class)->findOneByUser($user);
        if (!$vendor) {
            throw new \Exception('You cannot log if you are not a vendor', Response::HTTP_BAD_REQUEST);
        }

        return $vendor;
    }

    public function printInvoice(Vendor $vendor)
    {
        $pdfOptions = new Options();
        $pdfOptions->set('defaultFont', 'Arial');
        $pdfOptions->set('isRemoteEnabled', true);
        $dompdf = new Dompdf($pdfOptions);

        try {
            $now = new DateTime();
            $vouchers = $vendor->getVouchers();
            if (!count($vouchers)) {
                throw new \Exception('This vendor has no voucher. Try syncing with the server.', Response::HTTP_BAD_REQUEST);
            }
            $totalValue = 0;
            foreach ($vouchers as $voucher) {
                $voucher->setusedAt($voucher->getusedAt()->format('Y-m-d'));
                $totalValue += $voucher->getValue();
            }

            $location = $vendor->getLocation();

            if ($location && $location->getAdm4()) {
                $village = $location->getAdm4();
                $commune = $village->getAdm3();
                $district = $commune->getAdm2();
                $province = $district->getAdm1();
            } else if ($location && $location->getAdm3()) {
                $commune = $location->getAdm3();
                $district = $commune->getAdm2();
                $province = $district->getAdm1();
                $village = null;
            } else if ($location && $location->getAdm2()) {
                $district = $location->getAdm2();
                $province = $district->getAdm1();
                $village = null;
                $commune = null;
            } else if ($location && $location->getAdm1()) {
                $province = $location->getAdm1();
                $village = null;
                $commune = null;
                $district = null;
            } else {
                $village = null;
                $commune = null;
                $district = null;
                $province = null;
            }

            $html = $this->container->get('templating')->render(
            '@Voucher/Pdf/invoice.html.twig',
                array(
                    'name'  => $vendor->getName(),
                    'shop'  => $vendor->getShop(),
                    'addressStreet'  => $vendor->getAddressStreet(),
                    'addressPostcode'  => $vendor->getAddressPostcode(),
                    'addressNumber'  => $vendor->getAddressNumber(),
                    'addressVillage' => $village ? $village->getName() : null,
                    'addressCommune' => $commune ? $commune->getName() : null,
                    'addressDistrict' => $district ? $district->getName() : null,
                    'addressProvince' => $province ? $province->getName() : null,
                    'addressCountry' => $province ? $province->getCountryISO3() : null,
                    'date'  => $now->format('Y-m-d'),
                    'vouchers' => $vouchers,
                    'totalValue' => $totalValue
                )
            );

            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();
            $output = $dompdf->output();
            $pdfFilepath =  getcwd() . '/invoicepdf.pdf';
            file_put_contents($pdfFilepath, $output);

            $response = new BinaryFileResponse($pdfFilepath);
            $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, 'invoicepdf.pdf');
            $response->headers->set('Content-Type', 'application/pdf');
            $response->deleteFileAfterSend(true);

            return $response;
        } catch (\Exception $e) {
            throw new \Exception($e);
        }

        return new Response('');
    }
}
