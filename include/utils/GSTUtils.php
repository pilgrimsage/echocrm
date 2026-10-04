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

	/**
	 * Print formats a company's tax system allows: India = GST only, US = standard only,
	 * "all" (or unset) = both. Layout names: 'gst' and 'standard'.
	 */
	public static function printLayoutsForTaxSystem($taxSystem) {
		if ($taxSystem == 'india') {
			return array('gst');
		}
		if ($taxSystem == 'us') {
			return array('standard');
		}
		return array('gst', 'standard');
	}

	/** Modules whose PDF has a GST layout (the others only have the standard print). */
	public static function moduleHasGstPrint($moduleName) {
		return $moduleName === 'Invoice';
	}

	public static function normalizeGSTIN($value) {
		return strtoupper(preg_replace('/\s+/', '', (string)$value));
	}

	public static function isValidGSTIN($value) {
		$gstin = self::normalizeGSTIN($value);
		return preg_match(self::GSTIN_PATTERN, $gstin, $m) && isset(self::$stateCodes[$m[1]]);
	}

	/**
	 * GSTIN check character (the 15th): a base-36 weighted checksum over the first 14 characters.
	 * Catches single-character typos and most transpositions.
	 */
	public static function hasValidCheckCharacter($value) {
		$gstin = self::normalizeGSTIN($value);
		if (strlen($gstin) !== 15) {
			return false;
		}
		$alphabet = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
		$sum = 0;
		for ($i = 0; $i < 14; $i++) {
			$position = strpos($alphabet, $gstin[$i]);
			if ($position === false) {
				return false;
			}
			$product = $position * (($i % 2 === 0) ? 1 : 2);
			$sum += intdiv($product, 36) + ($product % 36);
		}
		return $gstin[14] === $alphabet[(36 - ($sum % 36)) % 36];
	}

	/**
	 * Why a GSTIN is not acceptable: null when it is fine, otherwise a message for the user.
	 * Empty input is fine (the field is optional).
	 */
	public static function gstinProblem($value) {
		$gstin = self::normalizeGSTIN($value);
		if ($gstin === '') {
			return null;
		}
		if (!self::isValidGSTIN($gstin)) {
			return 'Invalid GSTIN. It must be 15 characters: a 2-digit state code, the 10-character PAN, an entity number, the letter Z and a check character (for example 27AAPFU0939F1ZV).';
		}
		if (!self::hasValidCheckCharacter($gstin)) {
			return 'Invalid GSTIN: the check character (last character) does not match. Please re-check the number for a typing mistake.';
		}
		return null;
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

	private static $ones = array('', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten',
		'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen');
	private static $tens = array('', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety');

	/** Words for an integer 0..999 (no unit). */
	private static function belowThousand($n) {
		$parts = array();
		if ($n >= 100) {
			$parts[] = self::$ones[intdiv($n, 100)] . ' Hundred';
			$n %= 100;
		}
		if ($n >= 20) {
			$parts[] = self::$tens[intdiv($n, 10)] . ($n % 10 ? ' ' . self::$ones[$n % 10] : '');
		} elseif ($n > 0) {
			$parts[] = self::$ones[$n];
		}
		return implode(' ', $parts);
	}

	/** Words for a non-negative integer using the Indian system (Thousand, Lakh, Crore). */
	public static function integerInWords($n) {
		$n = (int)$n;
		if ($n === 0) {
			return 'Zero';
		}
		$parts = array();
		if ($n >= 10000000) {
			$parts[] = self::integerInWords(intdiv($n, 10000000)) . ' Crore';
			$n %= 10000000;
		}
		if ($n >= 100000) {
			$parts[] = self::belowThousand(intdiv($n, 100000)) . ' Lakh';
			$n %= 100000;
		}
		if ($n >= 1000) {
			$parts[] = self::belowThousand(intdiv($n, 1000)) . ' Thousand';
			$n %= 1000;
		}
		if ($n > 0) {
			$parts[] = self::belowThousand($n);
		}
		return implode(' ', $parts);
	}

	/** "One Thousand Two Hundred Thirty Four Rupees and Fifty Paise Only" */
	public static function amountInWords($amount, $major = 'Rupees', $minor = 'Paise') {
		$amount = round((float)$amount, 2);
		$prefix = '';
		if ($amount < 0) {
			$prefix = 'Minus ';
			$amount = abs($amount);
		}
		$total = (int)round($amount * 100);
		$whole = intdiv($total, 100);
		$fraction = $total % 100;
		$majorWord = ($whole === 1 && $major === 'Rupees') ? 'Rupee' : $major;
		$minorWord = ($fraction === 1 && $minor === 'Paise') ? 'Paisa' : $minor;
		$words = $prefix . self::integerInWords($whole) . ' ' . $majorWord;
		if ($fraction > 0) {
			$words .= ' and ' . self::integerInWords($fraction) . ' ' . $minorWord;
		}
		return $words . ' Only';
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
