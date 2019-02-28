<?php

namespace VoucherBundle\Utils;

use BeneficiaryBundle\Entity\Beneficiary;
use CommonBundle\Entity\Logs;
use DistributionBundle\Entity\DistributionBeneficiary;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use VoucherBundle\Entity\Booklet;
use VoucherBundle\Entity\Voucher;
use Psr\Container\ContainerInterface;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\MimeType\FileinfoMimeTypeGuesser;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

class BookletService
{

  /** @var EntityManagerInterface $em */
  private $em;

  /** @var ValidatorInterface $validator */
  private $validator;

  /** @var ContainerInterface $container */
  private $container;

  /**
   * UserService constructor.
   * @param EntityManagerInterface $entityManager
   * @param ValidatorInterface $validator
   * @param ContainerInterface $container
   */
  public function __construct(EntityManagerInterface $entityManager, ValidatorInterface $validator, ContainerInterface $container)
  {
    $this->em = $entityManager;
    $this->validator = $validator;
    $this->container = $container;
  }

  /**
   * Returns the index of the next booklet to be inserted in the database
   *
   * @return int
   */
  public function getBookletBatch()
  {
    $allBooklets = $this->em->getRepository(Booklet::class)->findAll();
    end($allBooklets);

    if ($allBooklets) {
      $bookletBatch = $allBooklets[key($allBooklets)]->getId() + 1;
      return $bookletBatch;
    } else {
      return 0;
    }
  }


  /**
   * Creates a new Booklet entity
   *
   * @param array $bookletData
   * @return mixed
   * @throws \Exception
   */
  public function create(array $bookletData)
  {
    $bookletBatch = $this->getBookletBatch();
    $currentBatch = $bookletBatch;

    for ($x = 0; $x < $bookletData['number_booklets']; $x++) {

      // === creates booklet ===
      try {
        $booklet = new Booklet();
        $code = $this->generateCode($bookletData, $currentBatch, $bookletBatch);

        $booklet->setCode($code)
          ->setNumberVouchers($bookletData['number_vouchers'])
          ->setCurrency($bookletData['currency'])
          ->setStatus(Booklet::UNASSIGNED);

        if (array_key_exists('password', $bookletData) && !empty($bookletData['password'])) {
          $booklet->setPassword($bookletData['password']);
        }

        $this->em->merge($booklet);
        $this->em->flush();

        $currentBatch++;
        $createdBooklet = $this->em->getRepository(Booklet::class)->findOneByCode($booklet->getCode());
      } catch (\Exception $e) {
        throw new \Exception('Error creating Booklet ' . $e->getMessage() . ' ' . $e->getLine());
      }

      //=== creates vouchers ===
      try {
        $voucherData = [
          'number_vouchers' => $bookletData['number_vouchers'],
          'bookletCode' => $code,
          'currency' => $bookletData['currency'],
          'bookletID' => $createdBooklet->getId(),
          'values' => $bookletData['individual_values'],
        ];
  
        $this->container->get('voucher.voucher_service')->create($voucherData);
      } catch (\Exception $e) {
        throw new \Exception('Error creating vouchers');
      }
    }

    return $createdBooklet;
  }


  /**
   * Generates a random code for a booklet
   *
   * @param array $bookletData
   * @param int $currentBatch
   * @param int $bookletBatch
   * @return string
   */
  public function generateCode(array $bookletData, int $currentBatch, int $bookletBatch)
  {
    // === randomCode*bookletBatchNumber-lastBatchNumber-currentBooklet ===
    $lastBatchNumber = sprintf("%03d", $bookletBatch + ($bookletData['number_booklets'] - 1));
    $currentBooklet = sprintf("%03d", $currentBatch);

    if ($bookletBatch > 1) {
      $bookletBatchNumber = sprintf("%03d", $bookletBatch);
    } elseif (!$bookletBatch) {
      $bookletBatchNumber = "000";
    }

    // === generates randomCode before * ===
    $rand = '';
    $seed = str_split('abcdefghijklmnopqrstuvwxyz'
      . 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'
      . '0123456789');
    shuffle($seed);
    foreach (array_rand($seed, 5) as $k) $rand .= $seed[$k];
    
    // === joins all parts together ===
    $fullCode = $rand . '*' . $bookletBatchNumber . '-' . $lastBatchNumber . '-' . $currentBooklet;
    return $fullCode;
  }

  
  /**
   * Get all the non-deactivated booklets from the database
   *
   * @return array
   */
  public function findAll()
  {
    return  $this->em->getRepository(Booklet::class)->getActiveBooklets();
  }

  /**
   * Get all the deactivated booklets from the database
   *
   * @return array
   */
  public function findDeactivated()
  {
    return  $this->em->getRepository(Booklet::class)->findBy(['status' => Booklet::DEACTIVATED]);
  }

  /**
   * Updates a booklet
   *
   * @param Booklet $booklet
   * @param array $bookletData
   * @return Booklet
   * @throws \Exception
   */
  public function update(Booklet $booklet, array $bookletData)
  {

    try {

        $booklet->setCurrency($bookletData['currency']);
        if (array_key_exists('password', $bookletData) && !empty($bookletData['password'])) {
          $booklet->setPassword($bookletData['password']);
        }
        $this->em->merge($booklet);

        $vouchers = $this->em->getRepository(Voucher::class)->findBy(['booklet' => $booklet->getId()]);
        /** @var $voucher Voucher */
        foreach ($vouchers as $voucher) {
            $voucher->setValue($bookletData['individual_value']);
            if (array_key_exists('password', $bookletData) && !empty($bookletData['password'])) {
              $qrCode = $voucher->getCode();

              // To know if we need to add a new password or replace an existant one
              preg_match('/^[A-Z]+\d+\*[\d]..-[\d]..-[\d]..-[\da-z]+-([\da-zA-Z=\/]+)$/i', $qrCode, $matches);
              if ($matches === null || count($matches) < 1) {
                $qrCode .= '-' . $bookletData['password'];
              } else {
                $qrCode = str_replace($matches[1], $bookletData['password'], $qrCode);
              }
              $voucher->setCode($qrCode);
            }
            $this->em->merge($voucher);
        }

        $this->em->flush();

    } catch (\Exception $e) {
      throw new \Exception('Error updating Booklet');
    }
    return $booklet;
  }


    /**
     * Deactivate a booklet
     *
     * @param Booklet $booklet
     * @return string
     */
    public function deactivate(Booklet $booklet) {
        $booklet->setStatus(Booklet::DEACTIVATED);

        $this->em->merge($booklet);
        $this->em->flush();

        return "Booklet has been deactivated";
    }

    /**
     * Deactivate many booklet
     *
     * @param int[] $bookletIds
     * @return string
     */
    public function deactivateMany(?array $bookletIds = [])
    {
      foreach ($bookletIds as $bookletId) {
        $booklet = $this->em->getRepository(Booklet::class)->find($bookletId);
        $booklet->setStatus(Booklet::DEACTIVATED);
        $this->em->merge($booklet);
      }
      
      $this->em->flush();

      return "Booklets have been deactivated";
    }


    /**
     * Update the password of the booklet
     *
     * @param Booklet $booklet
     * @param int $code
     * @throws \Exception
     *
     * @return string
     */
    public function updatePassword(Booklet $booklet, $password) {
        if ($booklet->getStatus() === Booklet::DEACTIVATED){
            throw new \Exception("This booklet has already been used and is actually deactivated");
        }

        $booklet->setPassword($password);
        $this->em->merge($booklet);
        $this->em->flush();

        return "Password has been set";
    }

    /**
     * Assign the booklet to a beneficiary
     *
     * @param Booklet $booklet
     * @param Beneficiary $beneficiary
     * @throws \Exception
     *
     * @return string
     */
    public function assign(Booklet $booklet, Beneficiary $beneficiary) {
        if ($booklet->getStatus() === Booklet::DEACTIVATED){
            throw new \Exception("This booklet has already been used and is actually deactivated");
        }

        $distributionBeneficiary = $this->em->getRepository(DistributionBeneficiary::class)->findOneByBeneficiary($beneficiary);

        $booklet->setDistributionBeneficiary($distributionBeneficiary)
                ->setStatus(Booklet::DISTRIBUTED);
        $this->em->merge($booklet);
        $this->em->flush();

        return "Booklet successfully assigned to the beneficiary";
    }

  // =============== DELETE 1 BOOKLET AND ITS VOUCHERS FROM DATABASE ===============
  /**
   * Permanently delete the record from the database
   *
   * @param Booklet $booklet
   * @param bool $removeBooklet
   * @return bool
   * @throws \Exception
   */
  public function deleteBookletFromDatabase(Booklet $booklet, bool $removeBooklet = true)
  {
    // === check if booklet has any vouchers ===
    $bookletId = $booklet->getId();
    $vouchers = $this->em->getRepository(Voucher::class)->findBy(['booklet' => $bookletId]);
    if ($removeBooklet && !$vouchers) {
      try {
        // === if no vouchers then delete ===
        $this->em->remove($booklet);
        $this->em->flush();
      } catch (\Exception $exception) {
        throw new \Exception('Unable to delete Booklet');
      }
    } 
    elseif ($removeBooklet && $vouchers) {
      try {
        // === if there are vouchers then delete those that are not used ===
        $this->container->get('voucher.voucher_service')->deleteBatchVouchers($booklet);
        $this->em->remove($booklet);
        $this->em->flush();
      } catch (\Exception $exception) {
        throw new \Exception('This booklet still contains potentially used vouchers.');
      }
    } 
    else {
      return false;
    }
    return true;
  }

  public function printMany(array $bookletIds)
  {
    $booklets = [];
    foreach ($bookletIds as $bookletId) {
      $booklet = $this->em->getRepository(Booklet::class)->find($bookletId);
      $booklets[] = $booklet;
    }

    try {
      return $this->generatePdf($booklets);

    } catch (\Exception $exception) {
        return new Response($exception->getMessage(), Response::HTTP_BAD_REQUEST);
    }
  }

  public function generatePdf(array $booklets)
    {
        $pdfOptions = new Options();
        $pdfOptions->set('defaultFont', 'Arial');
        $pdfOptions->set('isRemoteEnabled', true);
        $dompdf = new Dompdf($pdfOptions);

        try {
            $voucherHtmlSeparation = '<p class="next-voucher"></p>';
            $html = $this->getPdfHtml($booklets[0], $voucherHtmlSeparation);

            foreach($booklets as $booklet) {
                if ($booklet !== $booklets[0]) {
                    $bookletHtml = $this->getPdfHtml($booklet, $voucherHtmlSeparation);
                    preg_match('/<main>([\s\S]*)<\/main>/', $bookletHtml, $matches);
                    $bookletInnerHtml = '<p style="page-break-before: always">' . $matches[1];
                    $pos = strrpos($html, $voucherHtmlSeparation);
                    $html = substr_replace($html, $bookletInnerHtml, $pos, strlen($voucherHtmlSeparation));
                }
            }

            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();
            $output = $dompdf->output();
            $pdfFilepath =  getcwd() . '/otherpdf.pdf';
            file_put_contents($pdfFilepath, $output);

            $response = new BinaryFileResponse($pdfFilepath);
            $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, 'mypdf.pdf');
            $response->headers->set('Content-Type', 'application/pdf');
            $response->deleteFileAfterSend(true);

            return $response;

        } catch (\Exception $e) {
            throw new \Exception($e);
        }
    }

    public function getPdfHtml(Booklet $booklet, string $voucherHtmlSeparation)
    {
        $name = $booklet->getDistributionBeneficiary() ?
            $booklet->getDistributionBeneficiary()->getBeneficiary()->getFamilyName() :
            '_______';
        $currency = $booklet->getCurrency();
        $bookletQrCode = $booklet->getCode();
        $vouchers = $booklet->getVouchers();
        $totalValue = 0;
        $numberVouchers = $booklet->getNumberVouchers();
        
        foreach ($vouchers as $voucher) {
            $totalValue += $voucher->getValue();
        }

        $bookletHtml = $this->container->get('templating')->render(
        '@Voucher/Pdf/booklet.html.twig',
            array(
                'name'  => $name,
                'value' => $totalValue,
                'currency' => $currency,
                'qrCodeLink' => 'https://chart.googleapis.com/chart?chs=300x300&cht=qr&chl=' . $bookletQrCode,
                'numberVouchers' => $numberVouchers
            )
        );

        $pageBreak = true;

        foreach($vouchers as $voucher) {
            $voucherQrCode = $voucher->getCode();
            
            $voucherHtml = $this->container->get('templating')->render(
                '@Voucher/Pdf/voucher.html.twig',
                    array(
                        'name'  => $name,
                        'value' => $voucher->getValue(),
                        'currency' => $currency,
                        'qrCodeLink' => 'https://chart.googleapis.com/chart?chs=250x250&cht=qr&chl=' . $voucherQrCode
                    )
            );

            if ($pageBreak === true) {
                $voucherHtml = '<p style="page-break-before: always">' . $voucherHtml;
            } else {
                $voucherHtml = '<div><img class="scissors" src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABQAAAAUCAYAAACNiR0NAAAABHNCSVQICAgIfAhkiAAAAAlwSFlzAAAAlAAAAJQBeb8N7wAAABl0RVh0U29mdHdhcmUAd3d3Lmlua3NjYXBlLm9yZ5vuPBoAAAE8SURBVDiNrdQ9L0RBFAbgZzchmwgJjX9gSzo/QKJD6zuhRqNUiERJR4coRdQ0oqSjFUGv8ZGQWKxV7Gzc3J29NrJvcjK5c8+8cz7mvLQYubDmMY1RdOIG27hugqMdHXiqbfTgApWUlbCUQVTECT6D/z0mYTdB8ohLfIfvMgYiZMN4iQRxJoRawSt6w4GVhNNaimwOHxGyQxTyKeecxsgF8j20pf5tYhzvsJNK+SqRcgXrgWA/EtUXFtM3d+M84py028jeG8YapZPHjGodTsVrlLQHDGaUpw5DeM6Iti8rshiymtMU8pjFkRak3GhS/t2UrEn517NpdlJqDzuWwUYoW11TYs2oJNZVzKsKQhLLOECB7Ekpoz9ySaY4NJqUEhYiZDUUcexXvu4wkRTYKYygS1Vgt8L6F+oEtqX4AeYWq/jZKMK/AAAAAElFTkSuQmCC" /></div><hr class="separation">' . $voucherHtml;
            }

            $pageBreak = !$pageBreak;

            $pos = strrpos($bookletHtml, $voucherHtmlSeparation);
            $bookletHtml = substr_replace($bookletHtml, $voucherHtml, $pos, strlen($voucherHtmlSeparation));
        }

        return $bookletHtml;
    }


}
