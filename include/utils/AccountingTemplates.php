<?php
/**
 * Starter charts of accounts for different kinds of business. Applying one adds its ledgers (the
 * ones that already exist are kept, nothing is ever removed) and can point the posting roles at
 * industry-specific ledgers, e.g. sales posting to "Service Revenue". The base set that every
 * business gets (receivable, payable, taxes, bank, cash, opening balances) is created by
 * bin/create-journal.php; a template only adds to it.
 *
 * Template format: label, description, ledgers (group => list of names, or name => parent ledger
 * name), roles (posting role => ledger name).
 */
class Vtiger_Accounting_Templates {

	public static function all() {
		return array(
			'trading' => array(
				'label' => 'Trading / Wholesale',
				'description' => 'Buy and sell goods: stock, freight, trade discounts.',
				'ledgers' => array(
					'Assets' => array('Stock in Trade', 'Advances to Suppliers', 'Prepaid Expenses', 'Fixed Assets', 'Accumulated Depreciation'),
					'Liabilities' => array('Statutory Dues Payable', 'Salaries Payable', 'Bank Loans'),
					'Income' => array('Trade Discount Received', 'Commission Income', 'Other Income'),
					'Expenses' => array('Freight Inward', 'Freight Outward', 'Discount Allowed', 'Salaries and Wages', 'Rent', 'Utilities', 'Bank Charges', 'Interest Expense', 'Depreciation', 'Office Expenses', 'Miscellaneous Expenses'),
					'Equity' => array("Owner's Capital", 'Drawings', 'Retained Earnings'),
				),
				'roles' => array(),
			),
			'retail' => array(
				'label' => 'Retail / E-commerce',
				'description' => 'Shops and online stores: stock, card and wallet settlements, shrinkage, platform fees.',
				'ledgers' => array(
					'Assets' => array('Stock in Trade', 'Card and Wallet Settlements Due', 'Security Deposits', 'Fixed Assets'),
					'Liabilities' => array('Gift Vouchers Outstanding', 'Loyalty Points Liability', 'Statutory Dues Payable', 'Salaries Payable'),
					'Income' => array('Online Sales', 'Shop Sales', 'Shipping Recovered', 'Other Income'),
					'Expenses' => array('Marketplace Fees', 'Payment Gateway Charges', 'Packaging', 'Freight Outward', 'Stock Shrinkage and Damage', 'Discount Allowed', 'Salaries and Wages', 'Rent', 'Utilities', 'Advertising', 'Bank Charges', 'Miscellaneous Expenses'),
					'Equity' => array("Owner's Capital", 'Drawings', 'Retained Earnings'),
				),
				'roles' => array(),
			),
			'manufacturing' => array(
				'label' => 'Manufacturing',
				'description' => 'Raw material, work in progress and finished goods; direct labour and factory overheads.',
				'ledgers' => array(
					'Assets' => array('Raw Materials', 'Work in Progress', 'Finished Goods', 'Stores and Spares', 'Plant and Machinery', 'Accumulated Depreciation', 'Advances to Suppliers'),
					'Liabilities' => array('Statutory Dues Payable', 'Salaries Payable', 'Bank Loans'),
					'Income' => array('Sales - Finished Goods', 'Scrap Sales', 'Job Work Income', 'Other Income'),
					'Expenses' => array('Raw Material Consumed', 'Direct Labour', 'Factory Power and Fuel', 'Factory Overheads', 'Freight Inward', 'Freight Outward', 'Depreciation', 'Salaries and Wages', 'Rent', 'Utilities', 'Bank Charges', 'Interest Expense', 'Miscellaneous Expenses'),
					'Equity' => array("Owner's Capital", 'Drawings', 'Retained Earnings'),
				),
				'roles' => array('sales' => 'Sales - Finished Goods', 'purchases' => 'Raw Material Consumed'),
			),
			'services' => array(
				'label' => 'Services / IT / Consulting',
				'description' => 'Fees and retainers, no stock; subcontractors, software and travel.',
				'ledgers' => array(
					'Assets' => array('Unbilled Revenue', 'Prepaid Expenses', 'Security Deposits', 'Fixed Assets', 'Accumulated Depreciation'),
					'Liabilities' => array('Deferred Revenue', 'Statutory Dues Payable', 'Salaries Payable'),
					'Income' => array('Service Revenue', 'Retainer Income', 'Reimbursements Recovered', 'Other Income'),
					'Expenses' => array('Subcontractors', 'Software and Subscriptions', 'Cloud Hosting', 'Travel and Conveyance', 'Salaries and Wages', 'Rent', 'Utilities', 'Professional Fees', 'Advertising', 'Bank Charges', 'Depreciation', 'Miscellaneous Expenses'),
					'Equity' => array("Owner's Capital", 'Drawings', 'Retained Earnings'),
				),
				'roles' => array('sales' => 'Service Revenue', 'purchases' => 'Subcontractors'),
			),
			'construction' => array(
				'label' => 'Construction / Contracting',
				'description' => 'Contract billing, retention and work in progress; materials, labour and equipment.',
				'ledgers' => array(
					'Assets' => array('Contract Work in Progress', 'Retention Receivable', 'Construction Materials', 'Plant and Equipment', 'Accumulated Depreciation', 'Advances to Subcontractors'),
					'Liabilities' => array('Retention Payable', 'Advances from Clients', 'Statutory Dues Payable', 'Salaries Payable'),
					'Income' => array('Contract Revenue', 'Variation Orders', 'Other Income'),
					'Expenses' => array('Materials Consumed', 'Site Labour', 'Subcontractors', 'Equipment Hire', 'Site Overheads', 'Depreciation', 'Salaries and Wages', 'Rent', 'Utilities', 'Bank Charges', 'Interest Expense', 'Miscellaneous Expenses'),
					'Equity' => array("Owner's Capital", 'Drawings', 'Retained Earnings'),
				),
				'roles' => array('sales' => 'Contract Revenue', 'purchases' => 'Materials Consumed'),
			),
			'healthcare' => array(
				'label' => 'Healthcare / Clinics',
				'description' => 'Consultation, procedures and pharmacy; insurer receivables, consumables.',
				'ledgers' => array(
					'Assets' => array('Insurance Claims Receivable', 'Pharmacy Stock', 'Medical Consumables', 'Medical Equipment', 'Accumulated Depreciation'),
					'Liabilities' => array('Patient Advances', 'Statutory Dues Payable', 'Salaries Payable'),
					'Income' => array('Consultation Fees', 'Procedure Income', 'Pharmacy Sales', 'Lab and Diagnostics Income', 'Other Income'),
					'Expenses' => array('Medicines and Consumables', 'Doctors Fees', 'Lab Expenses', 'Salaries and Wages', 'Rent', 'Utilities', 'Equipment Maintenance', 'Depreciation', 'Bank Charges', 'Miscellaneous Expenses'),
					'Equity' => array("Owner's Capital", 'Drawings', 'Retained Earnings'),
				),
				'roles' => array('sales' => 'Consultation Fees', 'purchases' => 'Medicines and Consumables'),
			),
			'education' => array(
				'label' => 'Education / Training',
				'description' => 'Fees collected in advance and recognised over the term; faculty and course costs.',
				'ledgers' => array(
					'Assets' => array('Fees Receivable', 'Prepaid Expenses', 'Security Deposits', 'Equipment and Furniture', 'Accumulated Depreciation'),
					'Liabilities' => array('Fees Received in Advance', 'Student Deposits', 'Statutory Dues Payable', 'Salaries Payable'),
					'Income' => array('Tuition Fees', 'Admission Fees', 'Examination Fees', 'Material Sales', 'Other Income'),
					'Expenses' => array('Faculty Salaries', 'Course Material', 'Examination Expenses', 'Salaries and Wages', 'Rent', 'Utilities', 'Advertising', 'Depreciation', 'Bank Charges', 'Miscellaneous Expenses'),
					'Equity' => array('Capital Fund', 'Retained Earnings'),
				),
				'roles' => array('sales' => 'Tuition Fees', 'customer_advances' => 'Fees Received in Advance'),
			),
			'hospitality' => array(
				'label' => 'Restaurant / Hospitality',
				'description' => 'Food, beverage and room revenue; ingredient purchases, service charge.',
				'ledgers' => array(
					'Assets' => array('Food and Beverage Stock', 'Card Settlements Due', 'Security Deposits', 'Kitchen and Hotel Equipment', 'Accumulated Depreciation'),
					'Liabilities' => array('Advance Bookings', 'Service Charge Payable', 'Statutory Dues Payable', 'Salaries Payable'),
					'Income' => array('Food Sales', 'Beverage Sales', 'Room Revenue', 'Banquet and Events', 'Other Income'),
					'Expenses' => array('Food and Beverage Cost', 'Kitchen Consumables', 'Linen and Laundry', 'Salaries and Wages', 'Rent', 'Utilities', 'Gas and Fuel', 'Aggregator Commission', 'Advertising', 'Depreciation', 'Bank Charges', 'Miscellaneous Expenses'),
					'Equity' => array("Owner's Capital", 'Drawings', 'Retained Earnings'),
				),
				'roles' => array('sales' => 'Food Sales', 'purchases' => 'Food and Beverage Cost', 'customer_advances' => 'Advance Bookings'),
			),
			'realestate' => array(
				'label' => 'Real Estate / Rental',
				'description' => 'Rent and sale income, deposits held, property costs.',
				'ledgers' => array(
					'Assets' => array('Properties', 'Property Under Development', 'Rent Receivable', 'Accumulated Depreciation'),
					'Liabilities' => array('Tenant Security Deposits', 'Advance Rent Received', 'Statutory Dues Payable', 'Property Loans'),
					'Income' => array('Rental Income', 'Property Sales', 'Maintenance Charges Recovered', 'Brokerage Income', 'Other Income'),
					'Expenses' => array('Repairs and Maintenance', 'Property Tax', 'Brokerage Paid', 'Salaries and Wages', 'Utilities', 'Depreciation', 'Interest Expense', 'Bank Charges', 'Miscellaneous Expenses'),
					'Equity' => array("Owner's Capital", 'Drawings', 'Retained Earnings'),
				),
				'roles' => array('sales' => 'Rental Income', 'customer_advances' => 'Advance Rent Received'),
			),
			'nonprofit' => array(
				'label' => 'Non-profit / Association',
				'description' => 'Donations, grants and membership; programme and administration costs.',
				'ledgers' => array(
					'Assets' => array('Grants Receivable', 'Fixed Assets', 'Accumulated Depreciation'),
					'Liabilities' => array('Grants Received in Advance', 'Statutory Dues Payable', 'Salaries Payable'),
					'Income' => array('Donations', 'Grants', 'Membership Fees', 'Event Income', 'Other Income'),
					'Expenses' => array('Programme Expenses', 'Administration Expenses', 'Fundraising Expenses', 'Salaries and Wages', 'Rent', 'Utilities', 'Depreciation', 'Bank Charges', 'Miscellaneous Expenses'),
					'Equity' => array('General Fund', 'Restricted Funds', 'Retained Earnings'),
				),
				'roles' => array('sales' => 'Membership Fees', 'customer_advances' => 'Grants Received in Advance'),
			),
		);
	}

	/** Adds a template's ledgers and posting roles. Returns array(ledgers added, roles set). */
	public static function apply($id) {
		global $adb;
		$all = self::all();
		if (!isset($all[$id])) {
			throw new Exception('Unknown template.');
		}
		$added = 0;
		foreach ($all[$id]['ledgers'] as $group => $names) {
			foreach ($names as $name) {
				$exists = $adb->pquery('SELECT 1 FROM vtiger_ledgers l INNER JOIN vtiger_crmentity c ON c.crmid = l.ledgersid AND c.deleted = 0 WHERE l.ledger_name = ?', array($name));
				if (!$adb->num_rows($exists)) {
					Vtiger_Ledger_Utils::ledgerId($name, $group);
					$added++;
				}
			}
		}
		$roles = 0;
		foreach ($all[$id]['roles'] as $role => $ledgerName) {
			// only roles nobody has configured yet are changed, so applying a second template never overrides choices
			if (!Vtiger_Ledger_Utils::configuredAccount($role)) {
				Vtiger_Ledger_Utils::setAccount($role, Vtiger_Ledger_Utils::ledgerId($ledgerName, 'Income'));
				$roles++;
			}
		}
		return array($added, $roles);
	}
}
