<?php
/*+***********************************************************************************
 * Keeps the books in step with the records: after an invoice, purchase order, credit/debit
 * note, payment, bank transaction, bank account or ledger is saved, deleted or restored, its
 * journal entry is rebuilt (see Vtiger_Ledger_Utils). Before saving, changes that would touch a
 * locked period are refused.
 *************************************************************************************/

require_once 'include/events/VTEventHandler.inc';
include_once 'include/utils/LedgerUtils.php';
include_once 'include/utils/BankUtils.php';
include_once 'include/utils/PaymentUtils.php';

class JournalHandler extends VTEventHandler {

	private static $documentModules = array('Invoice', 'PurchaseOrder', 'SalesOrder');

	function handleEvent($eventName, $entityData) {
		$module = $entityData->getModuleName();
		$id = (int)$entityData->getId();
		$after = in_array($eventName, array('vtiger.entity.aftersave', 'vtiger.entity.afterdelete', 'vtiger.entity.afterrestore'));
		$before = $eventName == 'vtiger.entity.beforesave';

		if (in_array($module, self::$documentModules)) {
			if ($after) {
				Vtiger_Ledger_Utils::syncDocument($module, $id);
			} elseif ($before) {
				$this->guardDocument($module, $id, $entityData->getData());
			}
		} elseif ($module == 'Payments') {
			if ($after) {
				Vtiger_Ledger_Utils::syncPayment($id);
			} elseif ($before) {
				$data = $entityData->getData();
				Vtiger_Ledger_Utils::guard('Payments', $id, 'payment', $data['payment_date'] ?? null, ($data['status'] ?? '') == 'Completed');
			}
		} elseif ($module == 'BankTransactions') {
			if ($before && !Vtiger_Bank_Utils::$internal) {
				$data = $entityData->getData();
				Vtiger_Ledger_Utils::guard('BankTransactions', $id, 'bank', $data['transaction_date'] ?? null, true);
			} elseif ($eventName == 'vtiger.entity.aftersave') {
				// transactions created by a payment or a transfer are booked by that payment / transfer
				if (!Vtiger_Bank_Utils::$internal) {
					Vtiger_Ledger_Utils::syncBankTransaction($id);
				}
			} elseif ($after) {
				Vtiger_Ledger_Utils::syncBankTransaction($id);
			}
		} elseif ($module == 'BankAccounts') {
			if ($before) {
				$data = $entityData->getData();
				Vtiger_Ledger_Utils::guard('BankAccounts', $id, 'opening', $data['opening_date'] ?? null, !empty($data['opening_balance']));
			} elseif ($after) {
				Vtiger_Ledger_Utils::bankLedger($id);
				Vtiger_Ledger_Utils::syncBankOpening($id);
			}
		} elseif ($module == 'Ledgers') {
			if ($before) {
				$data = $entityData->getData();
				Vtiger_Ledger_Utils::guard('Ledgers', $id, 'opening', Vtiger_Ledger_Utils::openingDate(), !empty($data['opening_balance']));
			} elseif ($after) {
				Vtiger_Ledger_Utils::syncLedgerOpening($id);
			}
		}
	}

	/** A document dated in a locked period cannot be posted, changed into a posted status or edited once posted. */
	private function guardDocument($module, $id, $data) {
		$posted = array(
			'Invoice' => array('Approved', 'Sent', 'Credit Invoice', 'Paid'),
			'PurchaseOrder' => array('Approved', 'Delivered', 'Received Shipment'),
			'SalesOrder' => array('Approved', 'Sent'),
		);
		$statusField = array('Invoice' => 'invoicestatus', 'PurchaseOrder' => 'postatus', 'SalesOrder' => 'sostatus');
		$dateField = array('Invoice' => 'invoicedate', 'PurchaseOrder' => null, 'SalesOrder' => 'duedate');
		$status = $data[$statusField[$module]] ?? '';
		$date = $dateField[$module] ? ($data[$dateField[$module]] ?? null) : ($id ? Vtiger_Ledger_Utils::documentDate($module, $id) : date('Y-m-d'));
		$willPost = in_array($status, $posted[$module], true);
		Vtiger_Ledger_Utils::guard($module, $id, 'doc', $date, $willPost);
		// a document that has payments stays on the books; remove or refund the payments first
		if ($id && !$willPost && $status !== '' && Vtiger_Payment_Utils::sumPayments($module, $id, array('Completed', 'Pending')) > 0.004) {
			throw new Exception("This document has payments recorded against it, so it cannot be moved to '$status'. Delete or reverse the payments first.");
		}
	}
}
