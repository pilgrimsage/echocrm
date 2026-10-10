<?php
/*+***********************************************************************************
 * Fixed asset rules, applied however an asset is saved: it needs a class, a cost above its salvage value
 * and a purchase date; once depreciation has been posted its cost, dates, class and depreciation terms
 * cannot be changed (the postings would no longer match); an asset can only be disposed of after its
 * depreciation is posted up to the disposal month, and not once later months were depreciated; an asset
 * with depreciation or a disposal cannot be deleted. After a save, delete or restore the acquisition
 * and disposal entries are rebuilt.
 *************************************************************************************/

require_once 'include/events/VTEventHandler.inc';
include_once 'include/utils/LedgerUtils.php';
include_once 'include/utils/AssetUtils.php';

class FixedAssetsHandler extends VTEventHandler {

	function handleEvent($eventName, $entityData) {
		if ($entityData->getModuleName() != 'FixedAssets') {
			return;
		}
		$id = (int)$entityData->getId();
		switch ($eventName) {
			case 'vtiger.entity.beforesave':
				$this->validate($entityData, $id);
				break;
			case 'vtiger.entity.aftersave':
			case 'vtiger.entity.afterrestore':
			case 'vtiger.entity.afterdelete':
				Vtiger_Asset_Utils::syncAsset($id);
				break;
			case 'vtiger.entity.beforedelete':
				global $adb;
				$asset = Vtiger_Asset_Utils::getAsset($id);
				$rows = $adb->pquery('SELECT COUNT(*) FROM vtiger_asset_depreciation WHERE asset_id = ?', array($id));
				if ($asset && ((int)$adb->query_result($rows, 0, 0) > 0 || $asset['asset_status'] == 'Disposed')) {
					throw new Exception('This asset has depreciation posted or has been disposed of, so it cannot be deleted. Mark it Disposed instead.');
				}
				break;
		}
	}

	private function validate($entityData, $id) {
		global $adb;
		$d = $entityData->getData();
		$cost = (float)($d['cost'] ?? 0);
		$salvage = (float)($d['salvage_value'] ?? 0);
		if ($cost <= 0) {
			throw new Exception('Enter the cost of the asset.');
		}
		if ($salvage < 0 || $salvage >= $cost) {
			throw new Exception('The salvage value must be less than the cost.');
		}
		$purchase = Vtiger_Ledger_Utils::dbDate($d['purchase_date'] ?? '');
		if (!$purchase) {
			throw new Exception('Enter the purchase date.');
		}
		$from = Vtiger_Ledger_Utils::dbDate($d['depreciation_from'] ?? '') ?: $purchase;
		if ($from < $purchase) {
			throw new Exception('Depreciation cannot start before the purchase date.');
		}
		$classes = Vtiger_Asset_Utils::classes();
		$class = $classes[decode_html($d['asset_class'] ?? '')] ?? null;
		if (!$class) {
			throw new Exception('Choose the asset class (Ledgers > Asset Classes sets what each class posts to).');
		}
		$method = !empty($d['dep_method']) ? $d['dep_method'] : $class['dep_method'];
		if ($method == 'SLM' && ((float)($d['life_years'] ?? 0) > 0 ? (float)$d['life_years'] : (float)$class['life_years']) <= 0) {
			throw new Exception('Straight-line depreciation needs a useful life in years.');
		}
		if ($method == 'WDV' && ((float)($d['dep_rate'] ?? 0) > 0 ? (float)$d['dep_rate'] : (float)$class['dep_rate']) <= 0) {
			throw new Exception('Declining-balance depreciation needs an annual rate.');
		}
		if ((float)($d['opening_accumulated'] ?? 0) < 0 || (float)($d['opening_accumulated'] ?? 0) > $cost - $salvage) {
			throw new Exception('The accumulated depreciation brought forward cannot exceed cost less salvage value.');
		}
		if (!empty($d['cost_centre'])) {
			$problem = Vtiger_Ledger_Utils::costCentreProblem($d['cost_centre'], $purchase);
			if ($problem !== null) {
				throw new Exception($problem);
			}
		}
		Vtiger_Ledger_Utils::guard('FixedAsset', $id, 'acq', $purchase, ($d['acquisition_posting'] ?? '') != 'Already booked');

		// disposal
		if (($d['asset_status'] ?? '') == 'Disposed') {
			$disposal = Vtiger_Ledger_Utils::dbDate($d['disposal_date'] ?? '');
			if (!$disposal || $disposal < $purchase) {
				throw new Exception('Enter the disposal date (not before the purchase date).');
			}
			Vtiger_Ledger_Utils::guard('FixedAsset', $id, 'disposal', $disposal, true);
			if ($id) {
				$month = substr($disposal, 0, 7);
				$later = $adb->pquery('SELECT 1 FROM vtiger_asset_depreciation WHERE asset_id = ? AND period > ? LIMIT 1', array($id, $month));
				if ($adb->num_rows($later)) {
					throw new Exception('Depreciation was already posted for months after the disposal date.');
				}
				$pending = Vtiger_Asset_Utils::preview($month, $id);
				if ($pending) {
					throw new Exception('Post depreciation up to ' . $month . ' first (Ledgers > Run Depreciation), then record the disposal.');
				}
			}
		}

		// no changes to what the postings were built from
		if ($id) {
			$rows = $adb->pquery('SELECT COUNT(*) FROM vtiger_asset_depreciation WHERE asset_id = ?', array($id));
			if ((int)$adb->query_result($rows, 0, 0) > 0) {
				$stored = Vtiger_Asset_Utils::getAsset($id);
				$changed = abs((float)$stored['cost'] - $cost) > 0.004 || abs((float)$stored['salvage_value'] - $salvage) > 0.004
					|| $stored['purchase_date'] != $purchase || substr((string)($stored['depreciation_from'] ?: $stored['purchase_date']), 0, 10) != $from
					|| decode_html($stored['asset_class']) != decode_html($d['asset_class'] ?? '') || (string)$stored['dep_method'] != (string)($d['dep_method'] ?? '')
					|| abs((float)$stored['life_years'] - (float)($d['life_years'] ?? 0)) > 0.0001 || abs((float)$stored['dep_rate'] - (float)($d['dep_rate'] ?? 0)) > 0.0001
					|| abs((float)$stored['opening_accumulated'] - (float)($d['opening_accumulated'] ?? 0)) > 0.004;
				if ($changed) {
					throw new Exception('Depreciation has been posted for this asset, so its cost, dates, class and depreciation terms cannot be changed.');
				}
			}
		}
	}
}
