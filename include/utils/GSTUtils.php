<?php
/**
 * India GST helpers used by the inventory PDFs (Invoice, Quotes, Sales Order, Purchase Order).
 *
 * GSTIN layout (15 characters): 2-digit state code, 10-character PAN, entity number,
 * the letter Z, and a check character.
 */
class Vtiger_GST_Utils {

	const GSTIN_PATTERN = '/^(\d{2})([A-Z]{5}\d{4}[A-Z])([1-9A-Z])Z([0-9A-Z])$/';

	/** GST state / union-territory codes (as printed on invoices) => name. */
	private static $stateCodes = array(
		'01' => 'Jammu and Kashmir', '02' => 'Himachal Pradesh', '03' => 'Punjab', '04' => 'Chandigarh',
		'05' => 'Uttarakhand', '06' => 'Haryana', '07' => 'Delhi', '08' => 'Rajasthan',
		'09' => 'Uttar Pradesh', '10' => 'Bihar', '11' => 'Sikkim', '12' => 'Arunachal Pradesh',
		'13' => 'Nagaland', '14' => 'Manipur', '15' => 'Mizoram', '16' => 'Tripura',
		'17' => 'Meghalaya', '18' => 'Assam', '19' => 'West Bengal', '20' => 'Jharkhand',
		'21' => 'Odisha', '22' => 'Chhattisgarh', '23' => 'Madhya Pradesh', '24' => 'Gujarat',
		'26' => 'Dadra and Nagar Haveli and Daman and Diu', '27' => 'Maharashtra', '29' => 'Karnataka',
		'30' => 'Goa', '31' => 'Lakshadweep', '32' => 'Kerala', '33' => 'Tamil Nadu',
		'34' => 'Puducherry', '35' => 'Andaman and Nicobar Islands', '36' => 'Telangana',
		'37' => 'Andhra Pradesh', '38' => 'Ladakh', '97' => 'Other Territory',
	);

	/** Common alternative spellings => canonical name above. */
	private static $aliases = array(
		'orissa' => 'Odisha', 'pondicherry' => 'Puducherry', 'uttaranchal' => 'Uttarakhand',
		'nct of delhi' => 'Delhi', 'new delhi' => 'Delhi', 'jammu & kashmir' => 'Jammu and Kashmir',
		'andaman & nicobar islands' => 'Andaman and Nicobar Islands',
		'dadra & nagar haveli' => 'Dadra and Nagar Haveli and Daman and Diu',
		'daman & diu' => 'Dadra and Nagar Haveli and Daman and Diu',
	);

	public static function normalizeGSTIN($value) {
		return strtoupper(preg_replace('/\s+/', '', (string)$value));
	}

	public static function isValidGSTIN($value) {
		$gstin = self::normalizeGSTIN($value);
		return preg_match(self::GSTIN_PATTERN, $gstin, $m) && isset(self::$stateCodes[$m[1]]);
	}

	/** Two-digit state code embedded in a GSTIN, or null. */
	public static function stateCodeFromGSTIN($value) {
		$gstin = self::normalizeGSTIN($value);
		return self::isValidGSTIN($gstin) ? substr($gstin, 0, 2) : null;
	}

	/** State code for a free-text state name (case/spacing/alias tolerant), or null. */
	public static function stateCodeFromName($name) {
		$name = trim(preg_replace('/\s+/', ' ', html_entity_decode((string)$name)));
		if ($name === '') {
			return null;
		}
		$key = strtolower($name);
		if (isset(self::$aliases[$key])) {
			$name = self::$aliases[$key];
		}
		foreach (self::$stateCodes as $code => $stateName) {
			if (strcasecmp($stateName, $name) === 0 || strcasecmp(str_replace(' and ', ' & ', $stateName), $name) === 0) {
				// numeric-looking array keys are ints in PHP; codes are always two-digit strings
				return sprintf('%02d', $code);
			}
		}
		return null;
	}

	public static function stateName($code) {
		$code = sprintf('%02d', (int)$code);
		return isset(self::$stateCodes[$code]) ? self::$stateCodes[$code] : null;
	}

	/** "29-Karnataka" for a state name or GSTIN-derived code; falls back to the text as given. */
	public static function placeOfSupplyLabel($stateName, $fallbackGSTIN = '') {
		$code = self::stateCodeFromName($stateName);
		if ($code === null && $fallbackGSTIN !== '') {
			$code = self::stateCodeFromGSTIN($fallbackGSTIN);
		}
		if ($code !== null) {
			return $code . '-' . self::stateName($code);
		}
		return trim(html_entity_decode((string)$stateName));
	}
}
