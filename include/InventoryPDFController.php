<?php
/*********************************************************************************
** The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 *
 ********************************************************************************/

include_once 'vtlib/Vtiger/PDF/models/Model.php';
include_once 'vtlib/Vtiger/PDF/inventory/HeaderViewer.php';
include_once 'vtlib/Vtiger/PDF/inventory/FooterViewer.php';
include_once 'vtlib/Vtiger/PDF/inventory/ContentViewer.php';
include_once 'vtlib/Vtiger/PDF/inventory/ContentViewer2.php';
include_once 'vtlib/Vtiger/PDF/inventory/GSTContentViewer.php';
include_once 'vtlib/Vtiger/PDF/viewers/PagerViewer.php';
include_once 'vtlib/Vtiger/PDF/PDFGenerator.php';
include_once 'data/CRMEntity.php';
include_once 'include/utils/GSTUtils.php';
#[\AllowDynamicProperties]
class Vtiger_InventoryPDFController {

	protected $module;
	protected $focus = null;

	function __construct($module) {
		$this->moduleName = $module;
	}

	function loadRecord($id) {
		global $current_user;
		$this->focus = $focus = CRMEntity::getInstance($this->moduleName);
		$focus->retrieve_entity_info($id,$this->moduleName);
		$focus->apply_field_security();
		$focus->id = $id;
		$this->associated_products = getAssociatedProducts($this->moduleName,$focus);
	}

	function getPDFGenerator() {
		return new Vtiger_PDF_Generator();
	}

	function getContentViewer() {
		if($this->focusColumnValue('hdnTaxType') == "individual") {
			$contentViewer = new Vtiger_PDF_InventoryContentViewer();
		} else {
			$contentViewer = new Vtiger_PDF_InventoryTaxGroupContentViewer();
		}
		$contentViewer->setContentModels($this->buildContentModels());
		$contentViewer->setSummaryModel($this->buildSummaryModel());
		$contentViewer->setLabelModel($this->buildContentLabelModel());
		$contentViewer->setWatermarkModel($this->buildWatermarkModel());
		return $contentViewer;
	}

	function getHeaderViewer() {
		$headerViewer = new Vtiger_PDF_InventoryHeaderViewer();
		$headerViewer->setModel($this->buildHeaderModel());
		return $headerViewer;
	}

	function getFooterViewer() {
		$footerViewer = new Vtiger_PDF_InventoryFooterViewer();
		$footerViewer->setModel($this->buildFooterModel());
		$footerViewer->setLabelModel($this->buildFooterLabelModel());
		$footerViewer->setOnLastPage();
		return $footerViewer;
	}

	function getPagerViewer() {
		$pagerViewer = new Vtiger_PDF_PagerViewer();
		$pagerViewer->setModel($this->buildPagermodel());
		return $pagerViewer;
	}

	function Output($filename, $type) {
		if(is_null($this->focus)) return;

		$pdfgenerator = $this->getPDFGenerator();

		$pdfgenerator->setPagerViewer($this->getPagerViewer());
		$pdfgenerator->setHeaderViewer($this->getHeaderViewer());
		$pdfgenerator->setFooterViewer($this->getFooterViewer());
		$pdfgenerator->setContentViewer($this->getContentViewer());

		$pdfgenerator->generate($filename, $type);
	}


	// Helper methods

	function buildContentModels() {
		$associated_products = $this->associated_products;
		$contentModels = array();
		$productLineItemIndex = 0;
		$totaltaxes = 0;
		$no_of_decimal_places = getCurrencyDecimalPlaces();
		foreach($associated_products as $productLineItem) {
			++$productLineItemIndex;

			$contentModel = new Vtiger_PDF_Model();

			$discountPercentage  = 0.00;
			$total_tax_percent = 0.00;
			$producttotal_taxes = 0.00;
			$quantity = ''; $listPrice = ''; $discount = ''; $taxable_total = '';
			$tax_amount = ''; $producttotal = '';


			$quantity	= $productLineItem["qty{$productLineItemIndex}"];
			$listPrice	= $productLineItem["listPrice{$productLineItemIndex}"];
			$discount	= $productLineItem["discountTotal{$productLineItemIndex}"];
			$taxable_total = $quantity * $listPrice - $discount;
			$taxable_total = number_format($taxable_total, $no_of_decimal_places,'.','');
			$producttotal = $taxable_total;
			if($this->focus->column_fields["hdnTaxType"] == "individual") {
				foreach($productLineItem['taxes'] as $tax_count => $productLinetItemTaxInfo) {
					$tax_percent = $productLineItem['taxes'][$tax_count]['percentage'];
					$total_tax_percent += $tax_percent;
					$tax_amount = (($taxable_total*$tax_percent)/100);
					$producttotal_taxes += $tax_amount;
				}
			}

			$producttotal_taxes = number_format($producttotal_taxes, $no_of_decimal_places,'.','');
			$producttotal = $taxable_total+$producttotal_taxes;
			$producttotal = number_format($producttotal, $no_of_decimal_places,'.','');
			$tax = $producttotal_taxes;
			$totaltaxes += $tax;
			$totaltaxes = number_format($totaltaxes, $no_of_decimal_places,'.','');
			$discountPercentage = $productLineItem["discount_percent{$productLineItemIndex}"];
			$productName = decode_html($productLineItem["productName{$productLineItemIndex}"]);
			//get the sub product
			$subProducts = isset($productLineItem["subProductArray{$productLineItemIndex}"]) ? $productLineItem["subProductArray{$productLineItemIndex}"] : "";
			if($subProducts != '') {
				foreach($subProducts as $subProduct) {
					$productName .="\n"." - ".decode_html($subProduct);
				}
			}
			$contentModel->set('Name', $productName);
			$contentModel->set('Code', decode_html($productLineItem["hdnProductcode{$productLineItemIndex}"]));
			$contentModel->set('Quantity', $quantity);
			$contentModel->set('Price',     $this->formatPrice($listPrice));
			$contentModel->set('Discount',  $this->formatPrice($discount)."\n ($discountPercentage%)");
			$contentModel->set('Tax',       $this->formatPrice($tax)."\n ($total_tax_percent%)");
			$contentModel->set('Total',     $this->formatPrice($producttotal));
			$contentModel->set('Comment',   decode_html($productLineItem["comment{$productLineItemIndex}"]));

			$contentModels[] = $contentModel;
		}
		$this->totaltaxes = $totaltaxes; //will be used to add it to the net total

		return $contentModels;
	}

	function buildContentLabelModel() {
		$labelModel = new Vtiger_PDF_Model();
		$labelModel->set('Code',      getTranslatedString('Product Code',$this->moduleName));
		$labelModel->set('Name',      getTranslatedString('Product Name',$this->moduleName));
		$labelModel->set('Quantity',  getTranslatedString('Quantity',$this->moduleName));
		$labelModel->set('Price',     getTranslatedString('LBL_LIST_PRICE',$this->moduleName));
		$labelModel->set('Discount',  getTranslatedString('Discount',$this->moduleName));
		$labelModel->set('Tax',       getTranslatedString('Tax',$this->moduleName));
		$labelModel->set('Total',     getTranslatedString('Total',$this->moduleName));
		$labelModel->set('Comment',   getTranslatedString('Comment'),$this->moduleName);
		return $labelModel;
	}

	function buildSummaryModel() {
		$associated_products = $this->associated_products;
		$final_details = $associated_products[1]['final_details'];

		$summaryModel = new Vtiger_PDF_Model();

		$netTotal = $discount = $handlingCharges =  $handlingTaxes = 0;
		$adjustment = $grandTotal = 0;

		$productLineItemIndex = 0;
		$sh_tax_percent = 0;
		foreach($associated_products as $productLineItem) {
			++$productLineItemIndex;
			$netTotal += $productLineItem["netPrice{$productLineItemIndex}"];
		}
		$netTotal = number_format(($netTotal + $this->totaltaxes), getCurrencyDecimalPlaces(),'.', '');
		$summaryModel->set(getTranslatedString("Net Total", $this->moduleName), $this->formatPrice($netTotal));

		$discount_amount = $final_details["discount_amount_final"];
		$discount_percent = $final_details["discount_percentage_final"];

		$discount = 0.0;
        $discount_final_percent = '0.00';
		if($final_details['discount_type_final'] == 'amount') {
			$discount = $discount_amount;
		} else if($final_details['discount_type_final'] == 'percentage') {
            $discount_final_percent = $discount_percent;
			$discount = (($discount_percent*$final_details["hdnSubTotal"])/100);
		}
		$summaryModel->set(getTranslatedString("Discount", $this->moduleName)."($discount_final_percent%)", $this->formatPrice($discount));

		$group_total_tax_percent = '0.00';
		//To calculate the group tax amount
		if($final_details['taxtype'] == 'group') {
			$group_tax_details = $final_details['taxes'];
			foreach($group_tax_details as $i => $group_tax_info) {
				$group_total_tax_percent += isset($group_tax_details[$i]['percentage']) ? $group_tax_details[$i]['percentage'] : 0.00;
			}
			$summaryModel->set(getTranslatedString("Tax:", $this->moduleName)."($group_total_tax_percent%)", $this->formatPrice($final_details['tax_totalamount']));
		}
		//Shipping & Handling taxes
		$sh_tax_details = $final_details['sh_taxes'];
		foreach($sh_tax_details as $i => $sh_tax_info) {
			$sh_tax_percent = $sh_tax_percent + $sh_tax_details[$i]['percentage'];
		}
		//obtain the Currency Symbol
		$currencySymbol = $this->buildCurrencySymbol();

		$summaryModel->set(getTranslatedString("Shipping & Handling Charges", $this->moduleName), $this->formatPrice($final_details['shipping_handling_charge']));
		$summaryModel->set(getTranslatedString("Shipping & Handling Tax:", $this->moduleName)."($sh_tax_percent%)", $this->formatPrice($final_details['shtax_totalamount']));
		$summaryModel->set(getTranslatedString("Adjustment", $this->moduleName), $this->formatPrice($final_details['adjustment']));
		$summaryModel->set(getTranslatedString("Grand Total:", $this->moduleName)."(in $currencySymbol)", $this->formatPrice($final_details['grandTotal'])); // TODO add currency string

		if ($this->moduleName == 'Invoice') {
			$receivedVal = $this->focusColumnValue("received");
			if (!$receivedVal) {
				$this->focus->column_fields["received"] = 0;
			}
			//If Received value is exist then only Recieved, Balance details should present in PDF
			if ($this->formatPrice($this->focusColumnValue("received")) > 0) {
				$summaryModel->set(getTranslatedString("Received", $this->moduleName), $this->formatPrice($this->focusColumnValue("received")));
				$summaryModel->set(getTranslatedString("Balance", $this->moduleName), $this->formatPrice($this->focusColumnValue("balance")));
			}
		}
		return $summaryModel;
	}

	function buildHeaderModel() {
		$headerModel = new Vtiger_PDF_Model();
		$headerModel->set('title', $this->buildHeaderModelTitle());
		$modelColumns = array($this->buildHeaderModelColumnLeft(), $this->buildHeaderModelColumnCenter(), $this->buildHeaderModelColumnRight());
		$headerModel->set('columns', $modelColumns);

		return $headerModel;
	}

	function buildHeaderModelTitle() {
		return $this->moduleName;
	}

	/** Company tax system as configured under Settings > Taxes ('india', 'us' or 'all'). */
	function getTaxSystem() {
		$taxSystem = Vtiger_CompanyDetails_Model::getInstanceById()->get('tax_system');
		return $taxSystem ? $taxSystem : 'all';
	}

	/** GSTIN stored on the customer's Accounts record ('' when none or when the field is not installed). */
	function getCustomerGSTIN() {
		global $adb;
		$accountId = $this->focusColumnValue('account_id');
		if (empty($accountId) || !in_array('gstin', $adb->getColumnNames('vtiger_account'))) {
			return '';
		}
		$result = $adb->pquery('SELECT gstin FROM vtiger_account WHERE accountid = ?', array($accountId));
		return $adb->num_rows($result) ? Vtiger_GST_Utils::normalizeGSTIN($adb->query_result($result, 0, 'gstin')) : '';
	}

	/**
	 * Customer GSTIN and place of supply rows for the header. Empty when the document has no GST
	 * context (no customer GSTIN, and neither an Indian tax system nor a GSTIN as company tax id).
	 */
	function isGstContext() {
		$companyTaxId = Vtiger_CompanyDetails_Model::getInstanceById()->get('vatid');
		return $this->getCustomerGSTIN() !== '' || $this->getTaxSystem() == 'india' || Vtiger_GST_Utils::isValidGSTIN($companyTaxId);
	}

	function buildGstHeaderRows() {
		if (!$this->isGstContext()) {
			return array();
		}
		$customerGSTIN = $this->getCustomerGSTIN();

		$rows = array();
		if ($customerGSTIN !== '') {
			$rows['Customer GSTIN'] = $customerGSTIN;
		}
		$state = $this->focusColumnValue('ship_state');
		if ($state === '' || $state === null) {
			$state = $this->focusColumnValue('bill_state');
		}
		$placeOfSupply = Vtiger_GST_Utils::placeOfSupplyLabel($state, $customerGSTIN);
		if ($placeOfSupply !== '') {
			$rows['Place of Supply'] = $placeOfSupply;
		}
		return $rows;
	}

	// ---- GST tax invoice (HSN/SAC, CGST/SGST/IGST per line, HSN-wise summary, amount in words) ----

	private $gstData = null;

	/** Money with full decimals regardless of the user's "truncate trailing zeros" preference (3,350.00, not 3,350). */
	function formatMoney($value) {
		global $current_user;
		$user = clone $current_user;
		$user->truncate_trailing_zeros = false;
		$currencyField = new CurrencyField($value);
		return $currencyField->getDisplayValue($user, true);
	}

	/** Splits a tax list into CGST / SGST / IGST percentages and "anything else". */
	private function splitGstPercentages($taxes) {
		$split = array('CGST' => 0.0, 'SGST' => 0.0, 'IGST' => 0.0, 'OTHER' => 0.0);
		if (!is_array($taxes)) {
			return $split;
		}
		foreach ($taxes as $tax) {
			$label = isset($tax['taxlabel']) ? $tax['taxlabel'] : '';
			$percentage = isset($tax['percentage']) ? (float)$tax['percentage'] : 0.0;
			if (isset($split[$label]) && $label !== 'OTHER') {
				$split[$label] += $percentage;
			} else {
				$split['OTHER'] += $percentage;
			}
		}
		return $split;
	}

	private function lookupHsnCode($entityType, $productId) {
		global $adb;
		if (empty($productId)) {
			return '';
		}
		$table = ($entityType === 'Services') ? 'vtiger_service' : 'vtiger_products';
		$idColumn = ($entityType === 'Services') ? 'serviceid' : 'productid';
		if (!in_array('hsn_sac_code', $adb->getColumnNames($table))) {
			return '';
		}
		$result = $adb->pquery("SELECT hsn_sac_code FROM $table WHERE $idColumn = ?", array($productId));
		return $adb->num_rows($result) ? trim(decode_html($adb->query_result($result, 0, 'hsn_sac_code'))) : '';
	}

	/**
	 * Per-line GST figures and document totals, computed once from the loaded line items. Works for
	 * both individual (per line) and group tax types. Amounts are kept unrounded so totals match
	 * vtiger's own arithmetic; they are rounded only for display.
	 */
	function getGstData() {
		if ($this->gstData !== null) {
			return $this->gstData;
		}
		$products = $this->associated_products;
		$final = isset($products[1]['final_details']) ? $products[1]['final_details'] : array();
		$isGroup = isset($final['taxtype']) && $final['taxtype'] == 'group';

		// Share of an overall (document level) discount carried by each line, for group tax
		$factor = 1.0;
		$subTotal = isset($final['hdnSubTotal']) ? (float)$final['hdnSubTotal'] : 0.0;
		$finalDiscount = isset($final['discountTotal_final']) ? (float)$final['discountTotal_final'] : 0.0;
		if ($isGroup && $subTotal > 0 && $finalDiscount > 0) {
			$factor = 1 - ($finalDiscount / $subTotal);
		}
		$groupPercentages = $isGroup ? $this->splitGstPercentages(isset($final['taxes']) ? $final['taxes'] : array()) : null;

		$lines = array();
		$totals = array('taxable' => 0.0, 'CGST' => 0.0, 'SGST' => 0.0, 'IGST' => 0.0, 'other' => 0.0);
		$index = 0;
		foreach ($products as $line) {
			++$index;
			$percentages = $isGroup ? $groupPercentages : $this->splitGstPercentages(isset($line['taxes']) ? $line['taxes'] : array());
			$taxable = (float)$line["totalAfterDiscount{$index}"] * $factor;

			$row = array(
				'name' => decode_html($line["productName{$index}"]),
				'code' => decode_html($line["hdnProductcode{$index}"]),
				'comment' => decode_html($line["comment{$index}"]),
				'hsn' => $this->lookupHsnCode(isset($line["entityType{$index}"]) ? $line["entityType{$index}"] : 'Products', $line["hdnProductId{$index}"]),
				'qty' => $line["qty{$index}"],
				'price' => (float)$line["listPrice{$index}"],
				'discount' => (float)$line["discountTotal{$index}"],
				'discountPercent' => $line["discount_percent{$index}"],
				'taxable' => $taxable,
			);
			$lineTax = 0.0;
			foreach (array('CGST', 'SGST', 'IGST') as $label) {
				$row[$label . 'Rate'] = $percentages[$label];
				$row[$label] = $taxable * $percentages[$label] / 100;
				$totals[$label] += $row[$label];
				$lineTax += $row[$label];
			}
			$otherTax = $taxable * $percentages['OTHER'] / 100;
			$totals['other'] += $otherTax;
			$row['total'] = $taxable + $lineTax + $otherTax;
			$totals['taxable'] += $taxable;
			$lines[] = $row;
		}
		$this->gstData = array('lines' => $lines, 'totals' => $totals, 'final' => $final);
		return $this->gstData;
	}

	/**
	 * The GST layout is used when the company's tax system is India, or (for "all") when the
	 * document actually carries GST and no other kind of tax, so a mixed or non-GST document
	 * keeps the standard layout and nothing is lost from its totals.
	 */
	function isGstLayout() {
		$system = $this->getTaxSystem();
		if ($system == 'us') {
			return false;
		}
		$data = $this->getGstData();
		if ($data['totals']['other'] > 0) {
			return false;
		}
		if ($system == 'india') {
			return true;
		}
		return ($data['totals']['CGST'] + $data['totals']['SGST'] + $data['totals']['IGST']) > 0;
	}

	private function formatRate($rate) {
		$text = rtrim(rtrim(number_format((float)$rate, 2, '.', ''), '0'), '.');
		return ($text === '' ? '0' : $text) . '%';
	}

	/** "9%\n90.00" for a tax cell, or "-" when this tax does not apply to the line. */
	private function formatTaxCell($rate, $amount) {
		if ((float)$rate == 0) {
			return '-';
		}
		return $this->formatRate($rate) . "\n" . $this->formatMoney($amount);
	}

	function buildGstContentModels() {
		$models = array();
		foreach ($this->getGstData()['lines'] as $row) {
			$model = new Vtiger_PDF_Model();
			$name = $row['name'] . ($row['code'] !== '' ? "\n(" . $row['code'] . ")" : '');
			$model->set('Name', $name);
			$model->set('HSN', $row['hsn'] !== '' ? $row['hsn'] : '-');
			$model->set('Quantity', $row['qty']);
			$model->set('Price', $this->formatMoney($row['price']));
			$model->set('Discount', $row['discount'] > 0 ? $this->formatMoney($row['discount']) . "\n(" . $this->formatRate($row['discountPercent']) . ")" : '-');
			$model->set('Taxable', $this->formatMoney($row['taxable']));
			foreach (array('CGST', 'SGST', 'IGST') as $label) {
				$model->set($label, $this->formatTaxCell($row[$label . 'Rate'], $row[$label]));
			}
			$model->set('Total', $this->formatMoney($row['total']));
			$model->set('Comment', $row['comment']);
			$models[] = $model;
		}
		return $models;
	}

	function buildGstContentLabelModel() {
		$labelModel = new Vtiger_PDF_Model();
		$labelModel->set('Name', getTranslatedString('Product Name', $this->moduleName));
		$labelModel->set('HSN', 'HSN/SAC');
		$labelModel->set('Quantity', 'Qty');
		$labelModel->set('Price', 'Rate');
		$labelModel->set('Discount', getTranslatedString('Discount', $this->moduleName));
		$labelModel->set('Taxable', 'Taxable Value');
		$labelModel->set('CGST', 'CGST');
		$labelModel->set('SGST', 'SGST');
		$labelModel->set('IGST', 'IGST');
		$labelModel->set('Total', getTranslatedString('Total', $this->moduleName));
		return $labelModel;
	}

	/** Totals block: taxable value, each GST component that applies, charges, adjustment, grand total. */
	function buildGstSummaryModel() {
		$data = $this->getGstData();
		$totals = $data['totals'];
		$final = $data['final'];
		$summary = new Vtiger_PDF_Model();

		$summary->set('Taxable Value', $this->formatMoney($totals['taxable']));
		$anyTax = false;
		foreach (array('CGST', 'SGST', 'IGST') as $label) {
			if (round($totals[$label], 2) > 0) {
				$summary->set($label, $this->formatMoney($totals[$label]));
				$anyTax = true;
			}
		}
		if (!$anyTax) {
			$summary->set('GST', $this->formatMoney(0));
		}
		if (!empty($final['shipping_handling_charge']) && (float)$final['shipping_handling_charge'] != 0) {
			$summary->set(getTranslatedString('Shipping & Handling Charges', $this->moduleName), $this->formatMoney($final['shipping_handling_charge']));
			if (!empty($final['shtax_totalamount']) && (float)$final['shtax_totalamount'] != 0) {
				$summary->set(getTranslatedString('Shipping & Handling Tax:', $this->moduleName), $this->formatMoney($final['shtax_totalamount']));
			}
		}
		if (!empty($final['adjustment']) && (float)$final['adjustment'] != 0) {
			$summary->set(getTranslatedString('Adjustment', $this->moduleName), $this->formatMoney($final['adjustment']));
		}
		$summary->set(getTranslatedString('Grand Total:', $this->moduleName) . ' (' . $this->buildCurrencySymbol() . ')', $this->formatMoney($final['grandTotal']));

		if ($this->moduleName == 'Invoice') {
			$received = (float)$this->focusColumnValue('received');
			if ($received > 0) {
				$summary->set(getTranslatedString('Received', $this->moduleName), $this->formatMoney($received));
				$summary->set(getTranslatedString('Balance', $this->moduleName), $this->formatMoney($this->focusColumnValue('balance')));
			}
		}
		return $summary;
	}

	/** HSN-wise tax summary rows (grouped by HSN and GST rates) followed by a total row. */
	function buildGstHsnSummary() {
		$groups = array();
		foreach ($this->getGstData()['lines'] as $row) {
			$key = $row['hsn'] . '|' . $row['CGSTRate'] . '|' . $row['SGSTRate'] . '|' . $row['IGSTRate'];
			if (!isset($groups[$key])) {
				$groups[$key] = array('hsn' => $row['hsn'], 'taxable' => 0.0, 'CGSTRate' => $row['CGSTRate'], 'SGSTRate' => $row['SGSTRate'], 'IGSTRate' => $row['IGSTRate'], 'CGST' => 0.0, 'SGST' => 0.0, 'IGST' => 0.0);
			}
			$groups[$key]['taxable'] += $row['taxable'];
			foreach (array('CGST', 'SGST', 'IGST') as $label) {
				$groups[$key][$label] += $row[$label];
			}
		}
		$models = array();
		$sum = array('taxable' => 0.0, 'CGST' => 0.0, 'SGST' => 0.0, 'IGST' => 0.0);
		foreach ($groups as $group) {
			$model = new Vtiger_PDF_Model();
			$model->set('HSN', $group['hsn'] !== '' ? $group['hsn'] : '-');
			$model->set('Taxable', $this->formatMoney($group['taxable']));
			$tax = 0.0;
			foreach (array('CGST', 'SGST', 'IGST') as $label) {
				$model->set($label, (float)$group[$label . 'Rate'] == 0 ? '-' : $this->formatRate($group[$label . 'Rate']) . '  ' . $this->formatMoney($group[$label]));
				$tax += $group[$label];
				$sum[$label] += $group[$label];
			}
			$model->set('TotalTax', $this->formatMoney($tax));
			$sum['taxable'] += $group['taxable'];
			$models[] = $model;
		}
		$total = new Vtiger_PDF_Model();
		$total->set('HSN', 'Total');
		$total->set('Taxable', $this->formatMoney($sum['taxable']));
		foreach (array('CGST', 'SGST', 'IGST') as $label) {
			$total->set($label, round($sum[$label], 2) == 0 ? '-' : $this->formatMoney($sum[$label]));
		}
		$total->set('TotalTax', $this->formatMoney($sum['CGST'] + $sum['SGST'] + $sum['IGST']));
		$models[] = $total;
		return $models;
	}

	function getGstContentViewer() {
		$viewer = new Vtiger_PDF_InventoryGSTContentViewer();
		$viewer->setContentModels($this->buildGstContentModels());
		$viewer->setSummaryModel($this->buildGstSummaryModel());
		$viewer->setLabelModel($this->buildGstContentLabelModel());
		$viewer->setWatermarkModel($this->buildWatermarkModel());
		$viewer->setHsnSummary(
			array('HSN' => 'HSN/SAC', 'Taxable' => 'Taxable Value', 'CGST' => 'CGST', 'SGST' => 'SGST', 'IGST' => 'IGST', 'TotalTax' => 'Total Tax'),
			$this->buildGstHsnSummary()
		);
		$final = $this->getGstData()['final'];
		$amount = isset($final['grandTotal']) ? $final['grandTotal'] : 0;
		$viewer->setAmountInWords('Amount in Words', Vtiger_GST_Utils::amountInWords($amount));
		return $viewer;
	}

	function buildHeaderModelColumnLeft() {
		global $adb;

		// Company information
		$result = $adb->pquery("SELECT * FROM vtiger_organizationdetails", array());
		$num_rows = $adb->num_rows($result);
		if($num_rows) {
			$resultrow = $adb->fetch_array($result);

			$addressValues = array();
			$addressValues[] = $resultrow['address'];
			if(!empty($resultrow['city'])) $addressValues[]= "\n".$resultrow['city'];
			if(!empty($resultrow['state'])) $addressValues[]= ",".$resultrow['state'];
			if(!empty($resultrow['code'])) $addressValues[]= $resultrow['code'];
			if(!empty($resultrow['country'])) $addressValues[]= "\n".$resultrow['country'];

			$additionalCompanyInfo = array();
			if(!empty($resultrow['phone']))		$additionalCompanyInfo[]= "\n".getTranslatedString("Phone: ", $this->moduleName). $resultrow['phone'];
			if(!empty($resultrow['fax']))		$additionalCompanyInfo[]= "\n".getTranslatedString("Fax: ", $this->moduleName). $resultrow['fax'];
			if(!empty($resultrow['website']))	$additionalCompanyInfo[]= "\n".getTranslatedString("Website: ", $this->moduleName). $resultrow['website'];
			if (!empty($resultrow['state']) && $this->isGstContext()) {
				$sellerStateCode = Vtiger_GST_Utils::stateCodeFromName($resultrow['state']);
				$additionalCompanyInfo[]= "\nState: ".decode_html($resultrow['state']).($sellerStateCode !== null ? " (Code: $sellerStateCode)" : '');
			}
			if(!empty($resultrow['vatid'])) {
				$sellerTaxIdLabel = Vtiger_GST_Utils::isValidGSTIN($resultrow['vatid']) ? 'GSTIN: ' : getTranslatedString("VAT ID: ", $this->moduleName);
				$additionalCompanyInfo[]= "\n".$sellerTaxIdLabel.$resultrow['vatid'];
			}

			$modelColumnLeft = array(
					'logo' => "test/logo/".$resultrow['logoname'],
					'summary' => decode_html($resultrow['organizationname']),
					'content' => decode_html($this->joinValues($addressValues, ' '). $this->joinValues($additionalCompanyInfo, ' '))
			);
		}
		return $modelColumnLeft;
	}

	function buildHeaderModelColumnCenter() {
		$customerName = $this->resolveReferenceLabel($this->focusColumnValue('account_id'), 'Accounts');
		$contactName = $this->resolveReferenceLabel($this->focusColumnValue('contact_id'), 'Contacts');

		$customerNameLabel = getTranslatedString('Customer Name', $this->moduleName);
		$contactNameLabel = getTranslatedString('Contact Name', $this->moduleName);
		$modelColumnCenter = array(
				$customerNameLabel => $customerName,
				$contactNameLabel  => $contactName,
		);
		return $modelColumnCenter;
	}

	function buildHeaderModelColumnRight() {
		$issueDateLabel = getTranslatedString('Issued Date', $this->moduleName);
		$validDateLabel = getTranslatedString('Valid Date', $this->moduleName);
		$billingAddressLabel = getTranslatedString('Billing Address', $this->moduleName);
		$shippingAddressLabel = getTranslatedString('Shipping Address', $this->moduleName);

		$modelColumnRight = array(
				'dates' => array(
						$issueDateLabel  => $this->formatDate(date("Y-m-d")),
						$validDateLabel  => $this->formatDate($this->focusColumnValue('validtill')),
				),
				$billingAddressLabel  => $this->buildHeaderBillingAddress(),
				$shippingAddressLabel => $this->buildHeaderShippingAddress()
		);
		return $modelColumnRight;
	}

	function buildFooterModel() {
		$footerModel = new Vtiger_PDF_Model();
		$footerModel->set(Vtiger_PDF_InventoryFooterViewer::$DESCRIPTION_DATA_KEY, from_html($this->focusColumnValue('description')));
		$footerModel->set(Vtiger_PDF_InventoryFooterViewer::$TERMSANDCONDITION_DATA_KEY, from_html($this->focusColumnValue('terms_conditions')));
		return $footerModel;
	}

	function buildFooterLabelModel() {
		$labelModel = new Vtiger_PDF_Model();
		$labelModel->set(Vtiger_PDF_InventoryFooterViewer::$DESCRIPTION_LABEL_KEY, getTranslatedString('Description',$this->moduleName));
		$labelModel->set(Vtiger_PDF_InventoryFooterViewer::$TERMSANDCONDITION_LABEL_KEY, getTranslatedString('Terms & Conditions',$this->moduleName));
		return $labelModel;
	}

	function buildPagerModel() {
		$footerModel = new Vtiger_PDF_Model();
		$footerModel->set('format', '-%s-');
		return $footerModel;
	}

	function getWatermarkContent() {
		return '';
	}

	function buildWatermarkModel() {
		$watermarkModel = new Vtiger_PDF_Model();
		$watermarkModel->set('content', $this->getWatermarkContent());
		return $watermarkModel;
	}

	function buildHeaderBillingAddress() {
		$billPoBox	= $this->focusColumnValues(array('bill_pobox'));
		$billStreet = $this->focusColumnValues(array('bill_street'));
		$billCity	= $this->focusColumnValues(array('bill_city'));
		$billState	= $this->focusColumnValues(array('bill_state'));
		$billCountry = $this->focusColumnValues(array('bill_country'));
		$billCode	=  $this->focusColumnValues(array('bill_code'));
		$address	= $this->joinValues(array($billPoBox, $billStreet), ' ');
		$address .= "\n".$this->joinValues(array($billCity, $billState), ',')." ".$billCode;
		$address .= "\n".$billCountry;
		return $address;
	}

	function buildHeaderShippingAddress() {
		$shipPoBox	= $this->focusColumnValues(array('ship_pobox'));
		$shipStreet = $this->focusColumnValues(array('ship_street'));
		$shipCity	= $this->focusColumnValues(array('ship_city'));
		$shipState	= $this->focusColumnValues(array('ship_state'));
		$shipCountry = $this->focusColumnValues(array('ship_country'));
		$shipCode	=  $this->focusColumnValues(array('ship_code'));
		$address	= $this->joinValues(array($shipPoBox, $shipStreet), ' ');
		$address .= "\n".$this->joinValues(array($shipCity, $shipState), ',')." ".$shipCode;
		$address .= "\n".$shipCountry;
		return $address;
	}

	function buildCurrencySymbol() {
		global $adb;
		$currencyId = $this->focus->column_fields['currency_id'];
		if(!empty($currencyId)) {
			$result = $adb->pquery("SELECT currency_symbol FROM vtiger_currency_info WHERE id=?", array($currencyId));
			return decode_html($adb->query_result($result,0,'currency_symbol'));
		}
		return false;
	}

	function focusColumnValues($names, $delimeter="\n") {
		if(!is_array($names)) {
			$names = array($names);
		}
		$values = array();
		foreach($names as $name) {
			$value = $this->focusColumnValue($name, false);
			if($value !== false) {
				$values[] = $value;
			}
		}
		return $this->joinValues($values, $delimeter);
	}

	function focusColumnValue($key, $defvalue='') {
		$focus = $this->focus;
		if(isset($focus->column_fields[$key])) {
			return decode_html($focus->column_fields[$key]);
		}
		return $defvalue;
	}

	function resolveReferenceLabel($id, $module=false) {
		if(empty($id)) {
			return '';
		}
		if($module === false) {
			$module = getSalesEntityType($id);
		}
		$label = getEntityName($module, array($id));
		return decode_html($label[$id]);
	}

	function joinValues($values, $delimeter= "\n") {
		$valueString = '';
		foreach($values as $value) {
			if(empty($value)) continue;
			$valueString .= $value . $delimeter;
		}
		return rtrim($valueString, $delimeter);
	}

	function formatNumber($value) {
		return number_format($value);
	}

	function formatPrice($value, $decimal=2) {
		$currencyField = new CurrencyField($value);
		return $currencyField->getDisplayValue(null, true);
	}

	function formatDate($value) {
		return DateTimeField::convertToUserFormat($value);
	}

}
?>
