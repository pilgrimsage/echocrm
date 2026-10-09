<?php
/*+***********************************************************************************
 * Banking rules, applied however a record is saved (form, inline edit, import, webservice):
 *  - bank transactions need an active account, a positive amount and a sensible date; reconciled,
 *    payment-posted and transfer transactions cannot be altered or deleted from under what they
 *    represent; cash accounts cannot go negative;
 *  - after any save, delete or restore the account balances are rebuilt;
 *  - a bank account or ledger that is still in use cannot be deleted.
 *************************************************************************************/

require_once 'include/events/VTEventHandler.inc';
include_once 'include/utils/BankUtils.php';

class BankTransactionsHandler extends VTEventHandler {

	function handleEvent($eventName, $entityData) {
		switch ($entityData->getModuleName()) {
			case 'BankTransactions':
				$this->transactionEvent($eventName, $entityData);
				break;
			case 'BankAccounts':
				if ($eventName == 'vtiger.entity.aftersave' || $eventName == 'vtiger.entity.afterrestore') {
					Vtiger_Bank_Utils::recalculate($entityData->getId());
				} elseif ($eventName == 'vtiger.entity.beforedelete') {
					$this->refuseDeleteWhenUsed($entityData->getId(), 'vtiger_banktransactions', 'bank_account', 'bank account', 'transactions');
				}
				break;
			case 'Ledgers':
				if ($eventName == 'vtiger.entity.beforedelete') {
					$this->refuseDeleteWhenUsed($entityData->getId(), 'vtiger_banktransactions', 'ledger', 'ledger', 'transactions');
					$this->refuseDeleteWhenUsed($entityData->getId(), 'vtiger_ledgers', 'parent_ledger', 'ledger', 'sub-ledgers');
				}
				break;
		}
	}

	private function transactionEvent($eventName, $entityData) {
		global $adb;
		$id = (int)$entityData->getId();
		switch ($eventName) {
			case 'vtiger.entity.beforesave':
				$stored = $id ? Vtiger_Bank_Utils::getTransaction($id) : null;
				if (!Vtiger_Bank_Utils::$internal) {
					$problem = Vtiger_Bank_Utils::saveProblem($entityData->getData(), $id, $stored);
					if ($problem !== null) {
						throw new Exception($problem);
					}
				}
				break;
			case 'vtiger.entity.aftersave':
				$row = Vtiger_Bank_Utils::getTransaction($id);
				if ($row) {
					// reconciled date follows the checkbox
					if (!empty($row['reconciled']) && empty($row['reconciled_date'])) {
						$adb->pquery('UPDATE vtiger_banktransactions SET reconciled_date = ? WHERE banktransactionsid = ?', array(date('Y-m-d'), $id));
					} elseif (empty($row['reconciled']) && !empty($row['reconciled_date'])) {
						$adb->pquery('UPDATE vtiger_banktransactions SET reconciled_date = NULL WHERE banktransactionsid = ?', array($id));
					}
					Vtiger_Bank_Utils::recalculate($row['bank_account']);
				}
				break;
			case 'vtiger.entity.beforedelete':
				if (Vtiger_Bank_Utils::$internal) {
					break;
				}
				$row = Vtiger_Bank_Utils::getTransaction($id);
				if (!$row) {
					break;
				}
				if (!empty($row['reconciled'])) {
					throw new Exception('A reconciled transaction cannot be deleted. Un-reconcile it first.');
				}
				if (!empty($row['payment'])) {
					throw new Exception('This transaction was posted by a payment. Delete or change the payment instead.');
				}
				if (!empty($row['transfer_pair'])) {
					$pair = Vtiger_Bank_Utils::getTransaction($row['transfer_pair']);
					if ($pair && !empty($pair['reconciled'])) {
						throw new Exception('The other side of this transfer is reconciled. Un-reconcile it first.');
					}
					// a transfer goes as a whole
					$adb->pquery('UPDATE vtiger_banktransactions SET transfer_pair = NULL WHERE banktransactionsid IN (?, ?)', array($id, $row['transfer_pair']));
					Vtiger_Bank_Utils::removeTransaction($row['transfer_pair']);
				}
				break;
			case 'vtiger.entity.afterdelete':
			case 'vtiger.entity.afterrestore':
				$row = Vtiger_Bank_Utils::getTransaction($id);
				if ($row) {
					Vtiger_Bank_Utils::recalculate($row['bank_account']);
				}
				break;
		}
	}

	private function refuseDeleteWhenUsed($id, $table, $column, $what, $usedBy) {
		global $adb;
		$idColumn = $table == 'vtiger_ledgers' ? 'ledgersid' : 'banktransactionsid';
		$result = $adb->pquery("SELECT COUNT(*) AS n FROM $table t INNER JOIN vtiger_crmentity c ON c.crmid = t.$idColumn AND c.deleted = 0 WHERE t.$column = ?", array($id));
		$count = (int)$adb->query_result($result, 0, 'n');
		if ($count > 0) {
			throw new Exception("This $what still has $count $usedBy and cannot be deleted. Remove or move them first.");
		}
	}
}
