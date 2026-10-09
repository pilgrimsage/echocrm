<?php
/*+***********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 *************************************************************************************/
include_once 'modules/Ledgers/views/ReportBase.php';
include_once 'include/utils/GstReturns.php';

/** One GSTR-1 section or the purchase register as a CSV file, in the column order of the GST offline utility. */
class Ledgers_GstExport_Action extends Vtiger_Action_Controller {

	public function requiresPermission(\Vtiger_Request $request) {
		return array(array('module_parameter' => 'module', 'action' => 'DetailView'));
	}

	public function process(Vtiger_Request $request) {
		$section = (string)$request->get('section');
		list($period) = Vtiger_Gst_Returns::periodBounds((string)$request->get('period'));
		$money = function ($v) { return number_format((float)$v, 2, '.', ''); };
		$date = function ($v) { return $v ? date('d-M-Y', strtotime($v)) : ''; };
		$g1 = in_array($section, array('b2b', 'b2cl', 'b2cs', 'cdnr', 'cdnur', 'hsn', 'docs'), true) ? Vtiger_Gst_Returns::gstr1($period) : null;

		$rows = array();
		switch ($section) {
			case 'b2b':
				$header = array('GSTIN/UIN of Recipient', 'Receiver Name', 'Invoice Number', 'Invoice date', 'Invoice Value', 'Place Of Supply', 'Reverse Charge', 'Invoice Type', 'Rate', 'Taxable Value', 'IGST', 'CGST', 'SGST', 'Other tax');
				foreach ($g1['b2b'] as $r) {
					$rows[] = array($r['gstin'], $r['name'], $r['doc_no'], $date($r['date']), $money($r['value']), $r['pos'], 'N', 'Regular', $r['rate'], $money($r['taxable']), $money($r['igst']), $money($r['cgst']), $money($r['sgst']), $money($r['other']));
				}
				break;
			case 'b2cl':
				$header = array('Invoice Number', 'Invoice date', 'Invoice Value', 'Place Of Supply', 'Rate', 'Taxable Value', 'IGST', 'Other tax');
				foreach ($g1['b2cl'] as $r) {
					$rows[] = array($r['doc_no'], $date($r['date']), $money($r['value']), $r['pos'], $r['rate'], $money($r['taxable']), $money($r['igst']), $money($r['other']));
				}
				break;
			case 'b2cs':
				$header = array('Type', 'Place Of Supply', 'Rate', 'Taxable Value', 'IGST', 'CGST', 'SGST', 'Other tax');
				foreach ($g1['b2cs'] as $r) {
					$rows[] = array('OE', $r['pos'], $r['rate'], $money($r['taxable']), $money($r['igst']), $money($r['cgst']), $money($r['sgst']), $money($r['other']));
				}
				break;
			case 'cdnr':
			case 'cdnur':
				$header = array('GSTIN/UIN of Recipient', 'Receiver Name', 'Note Number', 'Note date', 'Note Type', 'Place Of Supply', 'Note Value', 'Rate', 'Taxable Value', 'IGST', 'CGST', 'SGST', 'Other tax');
				foreach ($g1[$section] as $r) {
					$rows[] = array($r['gstin'], $r['name'], $r['doc_no'], $date($r['date']), $r['note_type'], $r['pos'], $money($r['value']), $r['rate'], $money($r['taxable']), $money($r['igst']), $money($r['cgst']), $money($r['sgst']), $money($r['other']));
				}
				break;
			case 'hsn':
				$header = array('HSN', 'UQC', 'Total Quantity', 'Rate', 'Taxable Value', 'IGST', 'CGST', 'SGST', 'Other tax');
				foreach ($g1['hsn'] as $r) {
					$rows[] = array($r['hsn'], $r['unit'], $r['qty'], $r['rate'], $money($r['taxable']), $money($r['igst']), $money($r['cgst']), $money($r['sgst']), $money($r['other']));
				}
				break;
			case 'docs':
				$header = array('Nature of Document', 'Sr. No. From', 'Sr. No. To', 'Total Number', 'Cancelled', 'Net Issued');
				foreach ($g1['documents'] as $r) {
					$rows[] = array($r['type'], $r['from'], $r['to'], $r['total'], $r['cancelled'], $r['net']);
				}
				break;
			case 'purchases':
				$header = array('GSTIN of Supplier', 'Supplier', 'Document Number', 'Date', 'Document Value', 'Place Of Supply', 'Taxable Value', 'IGST', 'CGST', 'SGST', 'Other tax', 'Type');
				$p = Vtiger_Gst_Returns::purchases($period);
				foreach (array('purchases' => 'Purchase', 'debit_notes' => 'Debit note (reversal)') as $key => $label) {
					foreach ($p[$key] as $r) {
						$rows[] = array($r['gstin'], $r['name'], $r['doc_no'], $date($r['date']), $money($r['value']), $r['pos'], $money($r['taxable']), $money($r['igst']), $money($r['cgst']), $money($r['sgst']), $money($r['other']), $label);
					}
				}
				break;
			default:
				throw new AppException('Unknown section.');
		}
		header('Content-Type: text/csv; charset=utf-8');
		header('Content-Disposition: attachment; filename="gst_' . $section . '_' . $period . '.csv"');
		$out = fopen('php://output', 'w');
		fputcsv($out, $header);
		foreach ($rows as $row) {
			fputcsv($out, $row);
		}
		fclose($out);
	}
}
