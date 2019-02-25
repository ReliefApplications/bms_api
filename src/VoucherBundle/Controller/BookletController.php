<?php

namespace VoucherBundle\Controller;

use BeneficiaryBundle\Entity\Beneficiary;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\Serializer;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use FOS\RestBundle\Controller\Annotations as Rest;
use Swagger\Annotations as SWG;
use Nelmio\ApiDocBundle\Annotation\Model;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use VoucherBundle\Entity\Booklet;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\MimeType\FileinfoMimeTypeGuesser;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Doctrine\Common\Collections\Collection;


/**
 * Class BookletController
 * @package VoucherBundle\Controller
 */
class BookletController extends Controller
{
    /**
     * Create a new Booklet.
     *
     * @Rest\Put("/booklets", name="add_booklet")
     *
     * @SWG\Tag(name="Booklets")
     *
     * @SWG\Parameter(
     *     name="booklet",
     *     in="body",
     *     required=true,
     *     @Model(type=Booklet::class, groups={"FullBooklet"})
     * )
     *
     * @SWG\Response(
     *     response=200,
     *     description="Booklet created",
     *     @Model(type=Booklet::class)
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
    public function createBookletAction(Request $request)
    {
        /** @var Serializer $serializer */
        $serializer = $this->get('jms_serializer');

        $bookletData = $request->request->all();

        try {
            $return = $this->get('voucher.booklet_service')->create($bookletData);
        } catch (\Exception $exception) {
            return new Response($exception->getMessage(), Response::HTTP_BAD_REQUEST);
        }

        $bookletJson = $serializer->serialize(
            $return,
            'json',
            SerializationContext::create()->setGroups(['FullBooklet'])->setSerializeNull(true)
        );
        return new Response($bookletJson);
    }

    /**
     * Get all booklets
     *
     * @Rest\Get("/booklets", name="get_all_booklets")
     *
     * @SWG\Tag(name="Booklets")
     *
     * @SWG\Response(
     *     response=200,
     *     description="Booklets delivered",
     *     @SWG\Schema(
     *         type="array",
     *         @SWG\Items(ref=@Model(type=Booklet::class, groups={"FullBooklet"}))
     *     )
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
    public function getAllAction(Request $request)
    {
        try {
            $booklets = $this->get('voucher.booklet_service')->findAll();
        } catch (\Exception $exception) {
            return new Response($exception->getMessage(), Response::HTTP_BAD_REQUEST);
        }

        $json = $this->get('jms_serializer')->serialize($booklets, 'json', SerializationContext::create()->setGroups(['FullBooklet'])->setSerializeNull(true));
        return new Response($json);
    }

    /**
     * Get booklets that have been deactivated
     *
     * @Rest\Get("/deactivated-booklets", name="get_deactivated_booklets")
     *
     * @SWG\Tag(name="Booklets")
     *
     * @SWG\Response(
     *     response=200,
     *     description="Booklets delivered",
     *     @SWG\Schema(
     *         type="array",
     *         @SWG\Items(ref=@Model(type=Booklet::class, groups={"FullBooklet"}))
     *     )
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
    public function getDeactivatedAction(Request $request)
    {
        try {
            $booklets = $this->get('voucher.booklet_service')->findDeactivated();
        } catch (\Exception $exception) {
            return new Response($exception->getMessage(), Response::HTTP_BAD_REQUEST);
        }

        $json = $this->get('jms_serializer')->serialize($booklets, 'json', SerializationContext::create()->setGroups(['FullBooklet'])->setSerializeNull(true));
        return new Response($json);
    }

    /**
     * Get single booklet
     *
     * @Rest\Get("/booklets/{id}", name="get_single_booklet")
     *
     * @SWG\Tag(name="Single Booklet")
     *
     * @SWG\Response(
     *     response=200,
     *     description="Booklet delivered",
     *     @SWG\Schema(
     *         type="array",
     *         @SWG\Items(ref=@Model(type=Booklet::class, groups={"FullBooklet"}))
     *     )
     * )
     *
     * @SWG\Response(
     *     response=400,
     *     description="BAD_REQUEST"
     * )
     *
     * @param Booklet $booklet
     * @return Response
     */
    public function getSingleBookletAction(Booklet $booklet)
    {
        $json = $this->get('jms_serializer')->serialize($booklet, 'json', SerializationContext::create()->setGroups(['FullBooklet'])->setSerializeNull(true));

        return new Response($json);
    }

    /**
     * Edit a booklet {id} with data in the body
     *
     * @Rest\Post("/booklets/{id}", name="update_booklet")
     *
     * @SWG\Tag(name="Booklets")
     *
     * @SWG\Parameter(
     *     name="booklet",
     *     in="body",
     *     type="string",
     *     required=true,
     *     description="fields of the booklet which must be updated",
     *     @Model(type=Booklet::class)
     * )
     *
     * @SWG\Response(
     *     response=200,
     *     description="SUCCESS",
     *     @Model(type=Booklet::class)
     * )
     *
     * @SWG\Response(
     *     response=400,
     *     description="BAD_REQUEST"
     * )
     *
     * @param Request $request
     * @param Booklet $booklet
     * @return Response
     */
    public function updateAction(Request $request, Booklet $booklet)
    {
        $bookletData = $request->request->all();

        try {
            $newBooklet = $this->get('voucher.booklet_service')->update($booklet, $bookletData);
        } catch (\Exception $exception) {
            return new Response($exception->getMessage(), Response::HTTP_BAD_REQUEST);
        }

        $json = $this->get('jms_serializer')->serialize($newBooklet, 'json', SerializationContext::create()->setGroups(['FullBooklet'])->setSerializeNull(true));
        return new Response($json);
    }

    /**
     * Deactivate booklets
     * @Rest\Post("/deactivate-booklets", name="deactivate_booklets")
     *
     * @SWG\Tag(name="Booklets")
     *
     * @SWG\Response(
     *     response=200,
     *     description="Success or not",
     *     @SWG\Schema(type="boolean")
     * )
     *
     * @return Response
     */
    public function deactivateBooklets(Request $request){
        try {
            $booklets = $request->request->all();
            $this->get('voucher.booklet_service')->archiveMany($booklets);
        } catch (\Exception $exception) {
            return new Response($exception->getMessage(), Response::HTTP_BAD_REQUEST);
        }

        return new Response(json_encode('Booklet successfully archived'));
    }

    /**
     * Archive a booklet
     * @Rest\Delete("/booklets/{id}", name="archive_booklet")
     *
     * @SWG\Tag(name="Booklets")
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
    public function archiveAction(Booklet $booklet){
        try {
            $this->get('voucher.booklet_service')->archive($booklet);
        } catch (\Exception $exception) {
            return new Response($exception->getMessage(), Response::HTTP_BAD_REQUEST);
        }

        return new Response(json_encode('Booklet successfully archived'));
    }

    /**
     * Delete a booklet
     * @Rest\Delete("/booklets/{id}", name="delete_booklet")
     *
     * @SWG\Tag(name="Booklets")
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
    public function deleteAction(Booklet $booklet)
    {
        try {
            $this->get('voucher.booklet_service')->deleteBookletFromDatabase($booklet);
        } catch (\Exception $exception) {
            return new Response($exception->getMessage(), Response::HTTP_BAD_REQUEST);
        }

        return new Response(json_encode('Booklet successfully deleted'));
    }

    /**
     * Update password of the booklet
     * @Rest\Post("/booklets/{code}/password", name="update_password_booklet")
     *
     * @SWG\Tag(name="Booklets")
     *
     * @SWG\Response(
     *     response=200,
     *     description="SUCCESS",
     *     @SWG\Schema(type="string")
     * )
     *
     * @param Request $request
     * @param Booklet $booklet
     * @return Response
     */
    public function updatePasswordAction(Request $request, Booklet $booklet)
    {
        $password = $request->request->get('password');
         if (!isset($password) || empty($password)) {
            return new Response("The password is missing", Response::HTTP_BAD_REQUEST);
        }

        try {
            $return = $this->get('voucher.booklet_service')->updatePassword($booklet, $password);
        } catch (\Exception $exception) {
            return new Response($exception->getMessage(), Response::HTTP_BAD_REQUEST);
        }

        return new Response(json_encode($return));
    }


    /**
     * Assign the booklet to a specific beneficiary
     * @Rest\Post("/booklets/{bookletId}/assign/{beneficiaryId}", name="assign_booklet")
     * @ParamConverter("booklet", options={"mapping": {"bookletId": "code"}})
     * @ParamConverter("beneficiary", options={"mapping": {"beneficiaryId": "id"}})
     *
     * @SWG\Tag(name="Booklets")
     *
     * @SWG\Response(
     *     response=200,
     *     description="SUCCESS",
     *     @SWG\Schema(type="string")
     * )
     *
     * @param Booklet $booklet
     * @param Beneficiary $beneficiary
     * @return Response
     */
    public function assignAction(Booklet $booklet, Beneficiary $beneficiary)
    {
        try {
            $return = $this->get('voucher.booklet_service')->assign($booklet, $beneficiary);
        } catch (\Exception $exception) {
            return new Response($exception->getMessage(), Response::HTTP_BAD_REQUEST);
        }

        return new Response(json_encode($return));
    }

    /**
     * To print a batch of booklets
     *
     * @Rest\Post("/booklets-print", name="print_booklets")
     * @SWG\Tag(name="Booklets")
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
    public function printBookletsAction(Request $request)
    {
        $bookletData = $request->request->all();
        $bookletIds = $bookletData['bookletIds'];
        $booklets = [];
        foreach ($bookletIds as $bookletId) {
            $booklet = $this->get('voucher.booklet_service')->findOne($bookletId);
            $booklets[] = $booklet;
        }

        try {
            return $this->generatePdf($booklets);
        } catch (\Exception $exception) {
            return new Response($exception->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * To print a booklet
     *
     * @Rest\Get("/booklets/print/{id}", name="print_booklet")
     * @SWG\Tag(name="Booklets")
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
     * @param Booklet $booklet
     * @return Response
     */
    public function printBookletAction(Booklet $booklet)
    {
        try {
            return $this->generatePdf([$booklet]);;
        } catch (\Exception $e) {
            throw new \Exception($e);
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
        $name = $booklet->getDistributionBeneficiary()->getBeneficiary()->getFamilyName();
        $currency = $booklet->getCurrency();
        $bookletQrCode = $booklet->getCode();
        $vouchers = $booklet->getVouchers();
        $totalValue = 0;
        $numberVouchers = $booklet->getNumberVouchers();
        
        foreach ($vouchers as $voucher) {
            $totalValue += $voucher->getValue();
        }

        $bookletHtml = $this->renderView(
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
            
            $voucherHtml = $this->renderView(
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
