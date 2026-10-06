<?php
namespace Application\Controller;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Laminas\View\Renderer\RendererInterface;


class PrintController extends AbstractActionController
{

    /**
     * @var \TCPDF
     */
    protected $tcpdf;

    /**
     * @var RendererInterface
     */
    protected $renderer;

    public function __construct($tcpdf, $renderer)
    {
        $this->tcpdf = $tcpdf;
        $this->renderer = $renderer;
    }

    public function indexAction()
   {
		if($this->getRequest()->isPost()):
			$data = $this->getRequest()->getPost();
		endif;
        //echo '<pre>'; print_r($data['dom']); exit;
        $html= '<style>
		*{box-sizing:border-box;}
		body{font-size:11px;line-height:1.35;}
        ul.list-unstyled{list-style-type:none;margin:0;padding:0;}
        li.center{text-align:center;}
		table {
			width: 100%;
			word-wrap: break-word;
			border-collapse: collapse;
		}
		.table td, .table th {
			border: 1px solid #dddddd;
			text-align: left;
			padding: 4px 6px;
		}
		.print-title{display:block !important;}
		.table th{font-size:14px;}
		.table td{font-size:13px;}
		.table{margin-top:8px;}
        h5{margin:0 0 6px 0;}
		/* Match signature/footer text sizing with transaction print layout */
		table#nivoice-tab0 td{
			vertical-align:top;
			font-size:13px !important;
			line-height:1.3 !important;
		}
		table#nivoice-tab0,
		table#nivoice-tab0 td,
		table#nivoice-tab0 label{
			font-family:Times New Roman, serif !important;
		}
		table#nivoice-tab0 td:empty{
			display:none;
		}
		table#nivoice-tab0 tr td:first-child{
			text-align:left !important;
			width:65% !important;
		}
		table#nivoice-tab0 tr td:last-child{
			text-align:right !important;
			width:35% !important;
		}
		table#nivoice-tab0 tr td:last-child,
		table#nivoice-tab0 tr td:last-child label{
			font-size:13px !important;
			font-family:Times New Roman, serif !important;
			font-weight:700 !important;
		}
		table#nivoice-tab0 label{
			font-size:13px !important;
			line-height:1.3 !important;
			white-space:normal !important;
			word-break:normal !important;
		}
        .table-primary{background-color:#d2e1f3;color:#1e293b;border-color:#c0cfe1};
        .table-success{background-color:#d5f0da;color:#1e293b;border-color:#c3dcca;}
        .table-info{background-color:#d9ebf9;color:#1e293b;border-color:#c6d8e6;}
        .table-warning{background-color:#fde1cd;color:#1e293b;border-color:#e7cfbe;}
        .table-danger{background-color:#f7d7d7;color:#1e293b;border-color:#e1c6c7;}
        .text-start{text-align:left!important;}
		.text-right{text-align:right!important;}
        .text-end{text-align:right!important;}
	   .text-center{text-align:center!important;}
        .fs-1{font-size:24px!important;}
        .fs-2{font-size:20px!important;}
        .fs-3{font-size:16px!important;}
        .fs-4{font-size:14px!important;}
        .fs-5{font-size:12px!important;}
        .fs-6{font-size:10px!important;}
        .fw-bold{font-weight:700;}
        .text-wrap{white-space:normal!important;}
        input{white-space:normal!important;}
		.remarks { width: 20%;font-size:9px; }
    </style>' . $data['dom'];
		 // Initialize TCPDF
		$pdf = $this->tcpdf;
		$pdf->SetTitle($data['title']);
		$pdf->SetFont('times', '', 10, '', false);
		
		$pdf->SetMargins(12, 10, 15); // Increase the values as needed
		$pdf->SetHeaderMargin(3);
		$pdf->SetFooterMargin(5);
        

		// Language settings
		$lg = [];
		$lg['a_meta_charset'] = 'UTF-8';
		$pdf->setLanguageArray($lg);

		// Add a page with the specified orientation and size
		$pdf->AddPage($data['orentation'], $data['size']);

		// Determine the absolute path to the public folder
		$publicPath = getcwd() . '/public/';

		// Resolve a best-effort header image path; continue without image if none exists.
		$headerCandidates = ($data['orentation']=="P")
			? array('images/bhutanpostheader.jpg', 'images/header3.jpg')
			: array('images/header3.jpg', 'images/bhutanpostheader.jpg');

		$headerImagePath = null;
		foreach($headerCandidates as $candidate){
			$candidatePath = $publicPath . $candidate;
			if(file_exists($candidatePath)){
				$headerImagePath = $candidatePath;
				break;
			}
		}

		if ($headerImagePath !== null) {
			if($data['orentation']=="P"){$pdf->Image($headerImagePath, 10, 10, 190, 30, '', '', '', false, 300, '', false, false, 0, false, false, false);}
			else{$pdf->Image($headerImagePath, 10, 10, 270, 27, '', '', '', false, 300, '', false, false, 0, false, false, false);}
			$pdf->Ln(16);
		}

		// Write HTML content to the PDF
		$pdf->writeHTML($html, true, false, true, false, '');

		// Output the PDF
		$pdf->Output();
    }
}
