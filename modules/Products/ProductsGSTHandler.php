<?php
/*+***********************************************************************************
 * GST handler: keeps CGST/SGST/IGST product-tax associations in sync with a
 * product/service's gst_rate field, split by tax region (Intra-State/Inter-State)
 * so the existing region-based tax-override mechanism applies the correct
 * percentage automatically depending on the transaction's selected region.
 *************************************************************************************/

class ProductsGSTHandler extends VTEventHandler {

	const CGST_LABEL = 'CGST';
	const SGST_LABEL = 'SGST';
	const IGST_LABEL = 'IGST';
	const INTRA_STATE_REGION_LABEL = 'Intra-State (Same State)';
	const INTER_STATE_REGION_LABEL = 'Inter-State (Different State)';

	function handleEvent($eventName, $entityData) {
		if ($eventName != 'vtiger.entity.aftersave') {
			return;
		}

		$moduleName = $entityData->getModuleName();
		if ($moduleName != 'Products' && $moduleName != 'Services') {
			return;
		}

		$productId = $entityData->getId();
		$gstRate = $entityData->get('gst_rate');
		if ($gstRate === null || $gstRate === '') {
			return;
		}
		$gstRate = (float) $gstRate;

		self::syncProductGSTTaxes($productId, $gstRate);
	}

	/**
	 * Create the Intra-State/Inter-State tax regions and CGST/SGST/IGST tax
	 * records if they don't already exist. Safe to call repeatedly.
	 * @return array [intraStateRegionId, interStateRegionId, cgstTaxId, sgstTaxId, igstTaxId]
	 */
	public static function ensureTaxesAndRegionsExist() {
		vimport('~~/modules/Inventory/models/TaxRecord.php');
		vimport('~~/modules/Inventory/models/TaxRegion.php');

		return array(
			self::getOrCreateRegionId(self::INTRA_STATE_REGION_LABEL),
			self::getOrCreateRegionId(self::INTER_STATE_REGION_LABEL),
			self::getOrCreateTaxId(self::CGST_LABEL),
			self::getOrCreateTaxId(self::SGST_LABEL),
			self::getOrCreateTaxId(self::IGST_LABEL),
		);
	}

	/**
	 * Upsert CGST/SGST/IGST rows in vtiger_producttaxrel for the given product,
	 * with region-specific overrides: half the GST rate for CGST/SGST in the
	 * Intra-State region (and 0 in Inter-State), the full rate for IGST in the
	 * Inter-State region (and 0 in Intra-State).
	 */
	public static function syncProductGSTTaxes($productId, $gstRate) {
		list($intraStateRegionId, $interStateRegionId, $cgstTaxId, $sgstTaxId, $igstTaxId) = self::ensureTaxesAndRegionsExist();

		$halfRate = $gstRate / 2;

		self::upsertProductTaxRel($productId, $cgstTaxId, array(
			array('list' => array($intraStateRegionId), 'value' => $halfRate),
			array('list' => array($interStateRegionId), 'value' => 0),
		));
		self::upsertProductTaxRel($productId, $sgstTaxId, array(
			array('list' => array($intraStateRegionId), 'value' => $halfRate),
			array('list' => array($interStateRegionId), 'value' => 0),
		));
		self::upsertProductTaxRel($productId, $igstTaxId, array(
			array('list' => array($intraStateRegionId), 'value' => 0),
			array('list' => array($interStateRegionId), 'value' => $gstRate),
		));
	}

	private static function upsertProductTaxRel($productId, $taxId, $regions) {
		$db = PearDatabase::getInstance();

		$regionsJson = Zend_Json::encode($regions);

		$result = $db->pquery('SELECT 1 FROM vtiger_producttaxrel WHERE productid=? AND taxid=?', array($productId, $taxId));
		if ($db->num_rows($result) > 0) {
			$db->pquery('UPDATE vtiger_producttaxrel SET taxpercentage=?, regions=? WHERE productid=? AND taxid=?',
				array(0, $regionsJson, $productId, $taxId));
		} else {
			$db->pquery('INSERT INTO vtiger_producttaxrel(productid, taxid, taxpercentage, regions) VALUES(?,?,?,?)',
				array($productId, $taxId, 0, $regionsJson));
		}
	}

	private static function getOrCreateTaxId($label) {
		$db = PearDatabase::getInstance();
		$result = $db->pquery('SELECT taxid FROM vtiger_inventorytaxinfo WHERE taxlabel=?', array($label));
		if ($db->num_rows($result) > 0) {
			return $db->query_result($result, 0, 'taxid');
		}

		$taxRecord = new Inventory_TaxRecord_Model();
		$taxRecord->setType(Inventory_TaxRecord_Model::PRODUCT_AND_SERVICE_TAX);
		$taxRecord->set('taxlabel', $label);
		$taxRecord->set('percentage', 0);
		$taxRecord->set('method', 'Simple');
		$taxRecord->set('type', '');
		$taxRecord->set('compoundon', array());
		$taxRecord->set('regions', array());
		return $taxRecord->save();
	}

	private static function getOrCreateRegionId($name) {
		$db = PearDatabase::getInstance();
		$result = $db->pquery('SELECT regionid FROM vtiger_taxregions WHERE name=?', array($name));
		if ($db->num_rows($result) > 0) {
			return $db->query_result($result, 0, 'regionid');
		}

		$regionModel = new Inventory_TaxRegion_Model();
		$regionModel->set('name', $name);
		return $regionModel->save();
	}

}
