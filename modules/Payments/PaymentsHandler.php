<?php
/*+***********************************************************************************
 * Payment rules, applied however a payment is saved (form, inline edit, import, webservice):
 * it must belong to a document that accepts payments, be a positive amount that does not exceed
 * what is still outstanding, and its direction and parties follow the document. After a save,
 * delete or restore the document's received/paid and balance are brought up to date.
 *************************************************************************************/

require_once 'include/events/VTEventHandler.inc';
include_once 'include/utils/PaymentUtils.php';
include_once 'include/utils/BankUtils.php';

class PaymentsHandler extends VTEventHandler {

	/** Document a payment pointed to before it was edited, so both old and new document are refreshed. */
	private static $previousDocument = array();

	function handleEvent($eventName, $entityData) {
		if ($entityData->getModuleName() != 'Payments') {
			return;
		}
		switch ($eventName) {
			case 'vtiger.entity.beforesave':
				$this->validate($entityData);
				break;
			case 'vtiger.entity.beforedelete':
				$transactionId = Vtiger_Bank_Utils::transactionForPayment($entityData->getId());
				$stored = $transactionId ? Vtiger_Bank_Utils::getTransaction($transactionId) : null;
				if ($stored && !empty($stored['reconciled'])) {
					throw new Exception('The bank transaction of this payment is reconciled with the bank statement. Un-reconcile it before deleting the payment.');
				}
				break;
			case 'vtiger.entity.aftersave':
				$this->applyDocumentDetails($entityData);
				$this->refresh($entityData);
				Vtiger_Bank_Utils::syncPayment($entityData->getId());
				break;
			case 'vtiger.entity.afterdelete':
			case 'vtiger.entity.afterrestore':
				$this->refresh($entityData);
				Vtiger_Bank_Utils::syncPayment($entityData->getId());
				break;
		}
	}

	private function validate($entityData) {
		$data = $entityData->getData();
		$paymentId = $entityData->getId() ? (int)$entityData->getId() : 0;
		$documentId = $data['related_to'] ?? null;
		$documentModule = $documentId ? getSalesEntityType($documentId) : null;
		if (!$documentModule || !in_array($documentModule, Vtiger_Payment_Utils::documentModules())) {
			throw new Exception('Choose the quote, invoice or purchase order the payment belongs to.');
		}
		$status = !empty($data['status']) ? $data['status'] : Vtiger_Payment_Utils::STATUS_COMPLETED;
		$amount = (float)($data['amount'] ?? 0);
		$problem = Vtiger_Payment_Utils::saveProblem($documentModule, $documentId, $amount, $status, $paymentId);
		if ($problem !== null) {
			throw new Exception($problem);
		}
		$method = $data['payment_method'] ?? '';
		$bankAccount = $data['bank_account'] ?? null;
		if ($status == 'Completed' && $method != 'Cash' && empty($bankAccount)) {
			throw new Exception('Choose the bank account this payment goes through (only cash payments can go without one).');
		}
		if (!empty($bankAccount)) {
			$account = Vtiger_Bank_Utils::getAccount($bankAccount);
			if (!$account || $account['status'] != 'Active') {
				throw new Exception('The bank account is not available (missing or inactive).');
			}
		}
		if ($paymentId) {
			$problem = Vtiger_Bank_Utils::paymentChangeProblem($paymentId, $bankAccount, $amount, $status, $data['payment_date'] ?? null);
			if ($problem !== null) {
				throw new Exception($problem);
			}
			self::$previousDocument[$paymentId] = Vtiger_Payment_Utils::paymentDocument($paymentId);
		}

		$entityData->set('status', $status);
	}

	/**
	 * Direction and parties follow the document, not the form. The fields are display-only (not
	 * part of the form's insert), so they are written straight to the table after the save.
	 */
	private function applyDocumentDetails($entityData) {
		global $adb;
		$documentId = Vtiger_Payment_Utils::paymentDocument($entityData->getId());
		$documentModule = $documentId ? getSalesEntityType($documentId) : null;
		$document = $documentModule ? Vtiger_Payment_Utils::getDocument($documentModule, $documentId) : null;
		if ($document) {
			$adb->pquery('UPDATE vtiger_payments SET direction = ?, account_id = ?, vendor_id = ? WHERE paymentsid = ?',
				array(Vtiger_Payment_Utils::directionFor($document), $document['account'] ?: null, $document['vendor'] ?: null, $entityData->getId()));
		}
	}

	private function refresh($entityData) {
		$data = $entityData->getData();
		$documents = array();
		$documentId = $data['related_to'] ?? Vtiger_Payment_Utils::paymentDocument($entityData->getId());
		if ($documentId) {
			$documents[$documentId] = true;
		}
		$id = (int)$entityData->getId();
		if (!empty(self::$previousDocument[$id])) {
			$documents[self::$previousDocument[$id]] = true;
		}
		foreach (array_keys($documents) as $id) {
			$module = getSalesEntityType($id);
			if ($module) {
				Vtiger_Payment_Utils::refreshDocument($module, $id);
			}
		}
	}
}
