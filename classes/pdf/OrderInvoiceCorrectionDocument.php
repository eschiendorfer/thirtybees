<?php

/** Existing invoice/credit-slip layout with the updated QR amount. No shared temporary files. */
class OrderInvoiceCorrectionDocumentCore
{
    public function render(Order $order, OrderSlip $slip, string $output = 'I'): string
    {
        if (!Validate::isLoadedObject($order) || !Validate::isLoadedObject($slip) || (int)$slip->id_order !== (int)$order->id) {
            throw new PrestaShopException('Rechnung und Gutschrift gehören nicht zur selben Bestellung.');
        }
        $invoices = $order->getInvoicesCollection()->getResults();
        if (count($invoices) !== 1) {
            throw new PrestaShopException('Dieses Dokument benötigt eine eindeutig zugeordnete Rechnung.');
        }
        if (!in_array($output, ['I', 'S'], true)) {
            throw new PrestaShopException('Ungültige PDF-Ausgabe.');
        }
        require_once _PS_MODULE_DIR_.'genzo_accounting/autoload.php';
        $smarty = Context::getContext()->smarty;
        $outstanding = (new OrderBalanceService())->calculate($order)['outstanding'];
        $templates = [new HTMLTemplateInvoice(reset($invoices), $smarty), new HTMLTemplateOrderSlip($slip, $smarty)];
        $collection = new \Tomsgu\PdfMerger\PdfCollection();
        $streams = [];
        try {
            foreach ($templates as $index => $template) {
                $withQr = $index === 1 && $outstanding > 0;
                $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8');
                $pdf->setPrintHeader(false);
                $pdf->setPrintFooter(false);
                $pdf->SetMargins(10, 8, 10);
                if ($withQr) {
                    $pdf->SetAutoPageBreak(true, 115);
                }
                $pdf->AddPage();
                $pdf->writeHTML($template->getHeader().$template->getContent(), true, false, true, false, '');
                $pdf->lastPage();
                if ($withQr && !(Genzo_Accounting::addQrBillToBottom((int)$order->id, $pdf, $outstanding, false) instanceof TCPDF)) {
                    throw new PrestaShopException('Der QR-Zahlteil konnte nicht erstellt werden.');
                }
                $stream = fopen('php://temp', 'w+b');
                if ($stream === false) {
                    throw new PrestaShopException('Das PDF konnte nicht zusammengefügt werden.');
                }
                $streams[] = $stream;
                $content = $pdf->Output('', 'S');
                if (fwrite($stream, $content) !== strlen($content)) {
                    throw new PrestaShopException('Das PDF konnte nicht zusammengefügt werden.');
                }
                rewind($stream);
                $collection->addPdf($stream);
            }
            $merger = new \Tomsgu\PdfMerger\PdfMerger(new \setasign\Fpdi\Fpdi());
            return $merger->merge($collection, 'Rechnung-Gutschrift-'.$order->reference.'-'.$slip->id.'.pdf', $output);
        } finally {
            foreach ($streams as $stream) {
                fclose($stream);
            }
        }
    }
}
