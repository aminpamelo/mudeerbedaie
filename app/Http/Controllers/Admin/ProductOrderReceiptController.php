<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProductOrder;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductOrderReceiptController extends Controller
{
    /**
     * Stream a downloadable PDF receipt for the given product order.
     *
     * Loads the same relationships as the order-receipt Volt component so the
     * existing PDF template renders identically when downloaded via this route.
     */
    public function download(ProductOrder $order): StreamedResponse
    {
        $order->load([
            'items.product',
            'items.package',
            'items.warehouse',
            'customer',
            'addresses',
            'payments',
            'agent',
        ]);

        $pdf = Pdf::loadView('livewire.admin.orders.order-receipt-pdf', ['order' => $order])
            ->setPaper('a4', 'portrait')
            ->setOptions([
                'defaultFont' => 'DejaVu Sans',
                'isHtml5ParserEnabled' => true,
                'isPhpEnabled' => false,
                'isRemoteEnabled' => false,
            ]);

        $filename = 'receipt-'.$order->order_number.'.pdf';

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->stream();
        }, $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }
}
