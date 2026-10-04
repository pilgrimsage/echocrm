<?php
/**
 * Item table for Indian GST tax invoices: HSN/SAC, taxable value and CGST/SGST/IGST (rate and
 * amount) per line, and a bottom block with the HSN-wise tax summary, the amount in words and the
 * totals.
 */
include_once dirname(__FILE__) . '/ContentViewer.php';

class Vtiger_PDF_InventoryGSTContentViewer extends Vtiger_PDF_InventoryContentViewer {

	protected $headerRowHeight = 10; // two-line column labels

	/** Models for the HSN-wise tax summary, one per HSN/rate combination, plus a final total row. */
	protected $hsnSummaryModels = array();
	protected $hsnSummaryLabels = array();
	protected $amountInWords = '';
	protected $amountInWordsLabel = '';

	function __construct() {
		// NOTE: A4 content width is ~190 (excluding margins)
		$this->cells = array( // Name => Width
			'Name'     => 32,
			'HSN'      => 19,
			'Quantity' => 9,
			'Price'    => 17,
			'Discount' => 17,
			'Taxable'  => 19,
			'CGST'     => 19,
			'SGST'     => 19,
			'IGST'     => 19,
			'Total'    => 20
		);
	}

	function setHsnSummary($labels, $models) {
		$this->hsnSummaryLabels = $labels;
		$this->hsnSummaryModels = $models;
	}

	function setAmountInWords($label, $words) {
		$this->amountInWordsLabel = $label;
		$this->amountInWords = $words;
	}

	protected function getCellAlignment($cellName) {
		return in_array($cellName, array('Name', 'HSN', 'Quantity')) ? 'L' : 'R';
	}

	protected function getCommentCellWidth() {
		return $this->cells['Name'];
	}

	protected function getCommentOffsetX() {
		return 0;
	}

	/** HSN summary columns (name => width); sums to the table width. */
	protected function hsnColumns() {
		return array('HSN' => 28, 'Taxable' => 32, 'CGST' => 33, 'SGST' => 33, 'IGST' => 33, 'TotalTax' => 31);
	}

	protected function drawSummary($parent, $contentFrame, $contentLineX, $contentLineY, $overflowOffsetH) {
		$pdf = $parent->getPDF();
		$tableWidth = array_sum($this->cells);
		$rowHeight = $pdf->GetStringHeight('TEST', 40);
		$gap = 2;

		// Geometry of the three parts of the bottom block
		$hsnColumns = $this->hsnColumns();
		$hsnHeight = $rowHeight * (1 + php7_count($this->hsnSummaryModels));

		$summaryKeys = $this->contentSummaryModel ? $this->contentSummaryModel->keys() : array();
		$totalsWidth = 90;
		$totalsLabelWidth = 55;
		$totalsHeight = $rowHeight * php7_count($summaryKeys);

		$wordsWidth = $tableWidth - $totalsWidth;
		$wordsTextHeight = $pdf->GetStringHeight($this->amountInWords, $wordsWidth);
		$wordsHeight = $rowHeight + $wordsTextHeight;

		$bottomHeight = ceil($hsnHeight + $gap + max($totalsHeight, $wordsHeight));

		// Not enough room below the last line item: finish this page and continue on a new one
		if ($this->getContentBottom($contentFrame) - ($contentLineY + $overflowOffsetH) < $bottomHeight) {
			$this->drawCellBorder($parent);
			$parent->createPage();

			$contentFrame = $parent->getContentFrame();
			$contentLineX = $contentFrame->x;
			$contentLineY = $contentFrame->y;
		}

		$blockX = $contentLineX;
		$blockTop = ($contentFrame->h + $contentFrame->y - $this->headerRowHeight) - $bottomHeight;

		// HSN-wise tax summary
		$y = $blockTop;
		$offsetX = 0;
		$pdf->SetFont('', 'B');
		foreach ($hsnColumns as $name => $width) {
			$label = isset($this->hsnSummaryLabels[$name]) ? $this->hsnSummaryLabels[$name] : $name;
			$pdf->MultiCell($width, $rowHeight, $label, 1, 'L', 0, 1, $blockX + $offsetX, $y);
			$offsetX += $width;
		}
		$y += $rowHeight;
		$pdf->SetFont('', '');
		$lastIndex = php7_count($this->hsnSummaryModels) - 1;
		foreach ($this->hsnSummaryModels as $index => $model) {
			$offsetX = 0;
			if ($index === $lastIndex) {
				$pdf->SetFont('', 'B');
			}
			foreach ($hsnColumns as $name => $width) {
				$align = ($name === 'HSN') ? 'L' : 'R';
				$pdf->MultiCell($width, $rowHeight, (string)$model->get($name), 1, $align, 0, 1, $blockX + $offsetX, $y);
				$offsetX += $width;
			}
			$y += $rowHeight;
		}
		$pdf->SetFont('', '');
		$y += $gap;

		// Amount in words (left) and totals (right)
		$pdf->SetFont('', 'B');
		$pdf->MultiCell($wordsWidth, $rowHeight, $this->amountInWordsLabel, 1, 'L', 0, 1, $blockX, $y);
		$pdf->SetFont('', '');
		$pdf->MultiCell($wordsWidth, $bottomHeight - ($y - $blockTop) - $rowHeight, $this->amountInWords, 1, 'L', 0, 1, $blockX, $y + $rowHeight);

		$totalsX = $blockX + $wordsWidth;
		$lineY = $y;
		$lastKey = end($summaryKeys);
		foreach ($summaryKeys as $key) {
			$bold = ($key === $lastKey);
			$pdf->SetFont('', $bold ? 'B' : '');
			$pdf->MultiCell($totalsLabelWidth, $rowHeight, $key, 1, 'L', 0, 1, $totalsX, $lineY);
			$pdf->MultiCell($totalsWidth - $totalsLabelWidth, $rowHeight, $this->contentSummaryModel->get($key), 1, 'R', 0, 1, $totalsX + $totalsLabelWidth, $lineY);
			$lineY = $pdf->GetY();
		}
		$pdf->SetFont('', '');

		// Item columns run down to the top of the bottom block
		$cellHeights = array();
		foreach ($this->cells as $cellName => $cellWidth) {
			$cellHeights[$cellName] = $contentFrame->h - $bottomHeight;
		}
		return $cellHeights;
	}
}
