<?php

namespace VoucherBundle\Controller;

use JMS\Serializer\SerializationContext;
use JMS\Serializer\Serializer;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use FOS\RestBundle\Controller\Annotations as Rest;
use Swagger\Annotations as SWG;
use Nelmio\ApiDocBundle\Annotation\Model;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use VoucherBundle\Entity\Voucher;
use VoucherBundle\Entity\Booklet;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\MimeType\FileinfoMimeTypeGuesser;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

/**
 * Class VoucherController
 * @package VoucherBundle\Controller
 */
class VoucherController extends Controller
{
    /**
     * Create a new Voucher.
     *
     * @Rest\Put("/vouchers", name="add_voucher")
     *
     * @SWG\Tag(name="Vouchers")
     *
     * @SWG\Parameter(
     *     name="voucher",
     *     in="body",
     *     required=true,
     *     @Model(type=Voucher::class, groups={"FullVoucher"})
     * )
     *
     * @SWG\Response(
     *     response=200,
     *     description="Voucher created",
     *     @Model(type=Voucher::class)
     * )
     *
     * @SWG\Response(
     *     response=400,
     *     description="BAD_REQUEST"
     * )
     *
     * @param Request $request
     * @return Response
     */
    public function createVoucherAction(Request $request)
    {
        /** @var Serializer $serializer */
        $serializer = $this->get('jms_serializer');

        $voucherData = $request->request->all();

        try {
            $return = $this->get('voucher.voucher_service')->create($voucherData);
        } catch (\Exception $exception) {
            return new Response($exception->getMessage(), Response::HTTP_BAD_REQUEST);
        }

        $voucherJson = $serializer->serialize(
            $return,
            'json',
            SerializationContext::create()->setGroups(['FullVoucher'])->setSerializeNull(true)
        );

        return new Response($voucherJson);
    }

    /**
     * Get all vouchers
     *
     * @Rest\Get("/vouchers", name="get_all_vouchers")
     *
     * @SWG\Tag(name="Vouchers")
     *
     * @SWG\Response(
     *     response=200,
     *     description="Vouchers delivered",
     *     @SWG\Schema(
     *         type="array",
     *         @SWG\Items(ref=@Model(type=Voucher::class, groups={"FullVoucher"}))
     *     )
     * )
     *
     * @SWG\Response(
     *     response=400,
     *     description="BAD_REQUEST"
     * )
     *
     * @return Response
     */
    public function getAllAction()
    {
        try {
            $vouchers = $this->get('voucher.voucher_service')->findAll();
        } catch (\Exception $exception) {
            return new Response($exception->getMessage(), Response::HTTP_BAD_REQUEST);
        }

        $json = $this->get('jms_serializer')->serialize($vouchers, 'json', SerializationContext::create()->setGroups(['FullVoucher'])->setSerializeNull(true));
        return new Response($json);
    }


    /**
     * Get single voucher
     *
     * @Rest\Get("/vouchers/{id}", name="get_single_voucher")
     *
     * @SWG\Tag(name="Single Voucher")
     *
     * @SWG\Response(
     *     response=200,
     *     description="Voucher delivered",
     *     @SWG\Schema(
     *         type="array",
     *         @SWG\Items(ref=@Model(type=Voucher::class, groups={"FullVoucher"}))
     *     )
     * )
     *
     * @SWG\Response(
     *     response=400,
     *     description="BAD_REQUEST"
     * )
     *
     * @param Voucher $voucher
     * @return Response
     */
    public function getSingleVoucherAction(Voucher $voucher)
    {
        $json = $this->get('jms_serializer')->serialize($voucher, 'json', SerializationContext::create()->setGroups(['FullVoucher'])->setSerializeNull(true));

        return new Response($json);
    }


    /**
     * When a vendor sends their scanned vouchers
     *
     * @Rest\Post("/vouchers/scanned", name="scanned_vouchers")
     * @SWG\Tag(name="Vouchers")
     *
     * @SWG\Response(
     *     response=200,
     *     description="SUCCESS",
     * )
     *
     * @SWG\Response(
     *     response=400,
     *     description="BAD_REQUEST"
     * )
     *
     * @param Request $request
     * @return Response
     */
    public function scannedVouchersAction(Request $request)
    {
        $vouchersData = $request->request->all();
        $newVouchers = [];

        foreach ($vouchersData as $voucherData) {
            try {
                $newVoucher = $this->get('voucher.voucher_service')->scanned($voucherData);
                $newVouchers[] = $newVoucher;
            } catch (\Exception $exception) {
                return new Response($exception->getMessage(), Response::HTTP_BAD_REQUEST);
            }
        }

        $json = $this->get('jms_serializer')->serialize($newVouchers, 'json', SerializationContext::create()->setGroups(['FullVoucher'])->setSerializeNull(true));
        return new Response($json);
    }


    /**
     * Delete a booklet
     * @Rest\Delete("/vouchers/{id}", name="delete_voucher")
     *
     * @SWG\Tag(name="Vouchers")
     *
     * @SWG\Response(
     *     response=200,
     *     description="Success or not",
     *     @SWG\Schema(type="boolean")
     * )
     *
     * @param Voucher $voucher
     * @return Response
     */
    public function deleteAction(Voucher $voucher)
    {
        try {
            $isSuccess = $this->get('voucher.voucher_service')->deleteOneFromDatabase($voucher);
        } catch (\Exception $exception) {
            return new Response($exception->getMessage(), Response::HTTP_BAD_REQUEST);
        }

        return new Response(json_encode($isSuccess));
    }


    /**
     * Delete a batch of vouchers
     * @Rest\Delete("/vouchers/delete_batch/{id}", name="delete_batch_vouchers")
     *
     * @SWG\Tag(name="Vouchers")
     *
     * @SWG\Response(
     *     response=200,
     *     description="Success or not",
     *     @SWG\Schema(type="boolean")
     * )
     *
     * @param Booklet $booklet
     * @return Response
     */
    public function deleteBatchVouchersAction(Booklet $booklet)
    {
        try {
            $isSuccess = $this->get('voucher.voucher_service')->deleteBatchVouchers($booklet);
        } catch (\Exception $exception) {
            return new Response($exception->getMessage(), Response::HTTP_BAD_REQUEST);
        }
        
        return new Response(json_encode($isSuccess));
    }

     /**
     * To print a voucher
     *
     * @Rest\Get("/vouchers/print/{id}", name="print_voucher")
     * @SWG\Tag(name="Vouchers")
     *
     * @SWG\Response(
     *     response=200,
     *     description="SUCCESS",
     * )
     *
     * @SWG\Response(
     *     response=400,
     *     description="BAD_REQUEST"
     * )
     *
     * @param Voucher $voucher
     * @return Response
     */
    public function printVoucherAction(Voucher $voucher)
    {
        $vouchers = [$voucher, $voucher, $voucher, $voucher];
        $initialVoucher = $vouchers[0];
        try {
            // $printed = $voucher->getPrinted();
            $printed = false;
        
            if ($printed === false) {
                return $this->printVouchersAction([$voucher]);
            } else {
                // Do else
            }
        } catch (\Exception $e) {
            throw new \Exception($e);
        }
    }

    /**
     * To print all vouchers
     *
     * @Rest\Get("/print-vouchers", name="print_vouchers")
     * @SWG\Tag(name="Vouchers")
     *
     * @SWG\Response(
     *     response=200,
     *     description="SUCCESS",
     * )
     *
     * @SWG\Response(
     *     response=400,
     *     description="BAD_REQUEST"
     * )
     *
     * @return Response
     */
    public function printAllVouchersAction()
    {
        $vouchers = $this->get('voucher.voucher_service')->findAll(); // Has to be replaced by findNotPrinted()
        return $this->printVouchersAction($vouchers);
    }

    public function printVouchersAction(array $vouchers)
    {
        $initialVoucher = $vouchers[0];

        $pdfOptions = new Options();
        $pdfOptions->set('defaultFont', 'Arial');
        $pdfOptions->set('isRemoteEnabled', true);
        $dompdf = new Dompdf($pdfOptions);


        try {
            // $printed = $voucher->getPrinted();
            $printed = false;
        
            if ($printed === false) {
                // $initialVoucher->setPrinted(true);
                // $this->em->merge($voucher);
                // $this->em->flush();

                $name = $initialVoucher->getBooklet()->getDistributionBeneficiary()->getBeneficiary()->getFamilyName();
                $currency = $initialVoucher->getBooklet()->getCurrency();
                $qrCode = $initialVoucher->getCode();

                $html = $this->renderView(
                '@Voucher/Pdf/voucher.html.twig',
                    array(
                        'name'  => $name,
                        'value' => $initialVoucher->getValue(),
                        'currency' => $currency,
                        'qrCodeLink' => 'https://chart.googleapis.com/chart?chs=300x300&cht=qr&chl=' . $qrCode
                    )
                );

                foreach($vouchers as $otherVoucher) {
                    if ($otherVoucher !== $initialVoucher) {

                        // $otherVoucher->setPrinted(true);
                        // $this->em->merge($voucher);
                        // $this->em->flush();

                        $name = $otherVoucher->getBooklet()->getDistributionBeneficiary()->getBeneficiary()->getFamilyName();
                        $currency = $otherVoucher->getBooklet()->getCurrency();
                        $qrCode = $otherVoucher->getCode();
                        
                        $otherHtml = $this->renderView(
                            '@Voucher/Pdf/other-voucher.html.twig',
                                array(
                                    'name'  => $name,
                                    'value' => $otherVoucher->getValue(),
                                    'currency' => $currency,
                                    'qrCodeLink' => 'https://chart.googleapis.com/chart?chs=300x300&cht=qr&chl=' . $qrCode
                                )
                        );
                        $pos = strrpos($html, '<p style="page-break-before: always">');
                        $html = substr_replace($html, $otherHtml, $pos, strlen('<p style="page-break-before: always">'));
                    }
                }

                $pos = strrpos($html, '<p style="page-break-before: always">');
                $html = substr_replace($html, '', $pos, strlen('<p style="page-break-before: always">'));

                $dompdf->loadHtml($html);
        
                // (Optional) Setup the paper size and orientation 'portrait' or 'portrait'
                $dompdf->setPaper('A4', 'portrait');
        
                // Render the HTML as PDF
                $dompdf->render();
        
                // Store PDF Binary Data
                $output = $dompdf->output();
                
                // e.g /var/www/project/public/mypdf.pdf
                $pdfFilepath =  getcwd() . '/otherpdf.pdf';
                
                // Write file to the desired path
                file_put_contents($pdfFilepath, $output);

                $response = new BinaryFileResponse($pdfFilepath);

                $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, 'mypdf.pdf');
                $response->headers->set('Content-Type', 'application/pdf');
                $response->deleteFileAfterSend(true);

                return $response;
            } else {
                // Do else
            }
        } catch (\Exception $e) {
            throw new \Exception($e);
        }
    }
}
