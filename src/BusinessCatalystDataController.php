<?php

namespace appliedart\businesscatalyst;

use Craft;
use craft\web\Controller;
use PhpOffice\PhpSpreadsheet\IOFactory;
use \Exception;

class BusinessCatalystDataController extends Controller {
	protected $allowAnonymous = true;
	protected $security;

	public function actionDataTransformer($clientCode, $dataType) {
		if (!$this->security) {
			$this->security = Craft::$app->getSecurity();
		}

		//$dataHandle = null;
		$worksheet = null;
		$data = [];
		$response = [
			'success' => false,
			'message' => 'Nothing to process.'
		];

		$limit = intval(\Craft::$app->request->getQueryParam('limit'));
		$offset = intval(\Craft::$app->request->getQueryParam('offset'));

		$dataSource = realpath($_SERVER['DOCUMENT_ROOT'] . '/../data/' . trim(basename($dataType), './') . '.csv');

		if (empty($dataSource)) { // || $dataHandle = fopen($dataSource, 'r') === FALSE) {
			$response['success'] = false;
			$response['message'] = 'Source file missing for data type (' . $dataType . ')!';
		} else if (($worksheet = $this->loadSpreadsheet($dataSource)) === false) {
			$response['success'] = false;
			$response['message'] = 'Unable to load source spreadsheet (' . basename($dataSource) . ')!';
		} else {
			try {
				switch (strtoupper($clientCode)) {
					case 'CTD':
						$response['nextpage'] = $this->processCountryTravelDiscoveries($worksheet, $data, $limit, $offset);
						$response['success'] = true;
						$response['message'] = 'Data processed successfully!';
						break;
					case 'TORO':
						$response['nextpage'] = $this->processToroLive($worksheet, $data, $limit, $offset);
						$response['success'] = true;
						$response['message'] = 'Data processed successfully!';
						break;
					case 'AATEXPO':
						$response['nextpage'] = $this->processGeneric($worksheet, $data, $limit, $offset);
						$response['success'] = true;
						$response['message'] = 'Data processed successfully!';
						break;
					case 'IDN':
						$response['nextpage'] = $this->processGeneric($worksheet, $data, $limit, $offset);
						$response['success'] = true;
						$response['message'] = 'Data processed successfully!';
						break;
					default:
						$response['success'] = false;
						$response['message'] = 'Invalid client code (' . $clientCode . ')!';
				}
			} catch (Exception $e) {
				$response['success'] = false;
				$response['message'] = 'An error occured while processing data!';
			}
			//fclose($dataHandle);
		}

		$response['data'] =& $data;

		return $this->asJson($response);
	}

	protected function processGeneric($worksheet, &$data, $limit=100, $offset=0) {
		$columnLookupMap = [];
		$highestRow = $worksheet->getHighestRow();
		$highestColumn = $worksheet->getHighestColumn();
		$headingsArray = $worksheet->rangeToArray('A1:' . $highestColumn . '1', null, true, true, true);
		$headingsArray = $headingsArray[1];
		$lowestRow = is_int($offset) && $offset > 0 ? 2 + $offset : 2;
		$highestRow = is_int($limit) && $limit > 0 ? ($lowestRow < $highestRow ? $lowestRow + $limit - 1 : $lowestRow - 1) : $highestRow;

		for ($row = $lowestRow; $row <= $highestRow; $row++) {
			$dataRow = $worksheet->rangeToArray('A' . $row . ':' . $highestColumn . $row, null, true, true, true);
			if ((isset($dataRow[$row]['A'])) && ($dataRow[$row]['A'] > '')) {
				$namedData = [];
				foreach ($headingsArray as $columnKey => $columnHeading) {
					if ($row == $lowestRow) {
						$columnHeading = trim($columnHeading);
						$headingsArray[$columnKey] = $columnHeading;
						$columnLookupMap[strtolower($columnHeading)] = $columnHeading;
					}

					$namedData[$columnHeading] = $dataRow[$row][$columnKey];
				}

				$splitColumns = ['Counties', 'Hospitals'];
				foreach ($splitColumns as $splitColumn) {
					if (array_key_exists($splitColumn, $namedData)) {
						$namedData[$splitColumn] = preg_split('/\s*,\s*/', $namedData[$splitColumn]);
					}
				}

				if (array_key_exists('Enabled', $namedData)) {
					$namedData['Enabled'] = ($namedData['Enabled'] == 'Y');
				}

				if (array_key_exists('Show Contact Info', $namedData)) {
					$namedData['Show Contact Info'] = ($namedData['Show Contact Info'] == 'Yes');
				}

				$data[] = $namedData;
			}
		}

		$path = \Craft::$app->request->getFullPath();
		$pageUrl = \craft\helpers\UrlHelper::url($path);
		$params = [];

		if (is_int($limit) && $limit > 0) {
			$params[] = 'limit=' . $limit;
		} else {
			// No limit means all records were read
			$pageUrl = null;
		}

		if (!is_null($pageUrl)) {
			$offset = is_int($offset) && $offset > 0 ? $offset + $limit : $limit;
			$params[] = 'offset=' . $offset;
			$pageUrl = $pageUrl . '?' . implode('&', $params);
		}

		return $lowestRow > $highestRow ? null : $pageUrl;
	}

	protected function processToroLive($worksheet, &$data, $limit=100, $offset=0) {
		$priceColumns = [ 'US MSRP', 'US Standard Rental', 'US Co-op', 'CAN MSRP', 'Canadian Standard Rental', 'Canadian Co-op' ];
		$priceAccessMap = [
			'US Standard Rental' => 'US Standard Rental',
			'US Coop' => 'US Co-op',
			'CAN Standard Rental' => 'Canadian Standard Rental',
			'CAN Coop' => 'Canadian Co-op'
		];

		$columnLookupMap = [];
		$highestRow = $worksheet->getHighestRow();
		$highestColumn = $worksheet->getHighestColumn();
		$headingsArray = $worksheet->rangeToArray('A1:' . $highestColumn . '1', null, true, true, true);
		$headingsArray = $headingsArray[1];
		$lowestRow = is_int($offset) && $offset > 0 ? 2 + $offset : 2;
		$highestRow = is_int($limit) && $limit > 0 ? ($lowestRow < $highestRow ? $lowestRow + $limit - 1 : $lowestRow - 1) : $highestRow;

		for ($row = $lowestRow; $row <= $highestRow; $row++) {
			$dataRow = $worksheet->rangeToArray('A' . $row . ':' . $highestColumn . $row, null, true, true, true);
			if ((isset($dataRow[$row]['A'])) && ($dataRow[$row]['A'] > '')) {
				$namedData = [];
				foreach ($headingsArray as $columnKey => $columnHeading) {
					if ($row == $lowestRow) {
						$columnHeading = trim($columnHeading);
						$headingsArray[$columnKey] = $columnHeading;
						$columnLookupMap[strtolower($columnHeading)] = $columnHeading;
					}

					$namedData[$columnHeading] = $dataRow[$row][$columnKey];
				}

				$prices = [];
				$priceAccess = [];

				foreach ($priceColumns as $priceColumn) {
					if (array_key_exists($priceColumn, $namedData) && is_numeric($namedData[$priceColumn])) {
						$prices[] = [
							'priceGroup' => $priceColumn,
							'price' => $namedData[$priceColumn]
						];
					}
				}

				foreach ($priceAccessMap as $old => $new) {
					if (array_key_exists($old, $namedData) && strtoupper(trim($namedData[$old])) == 'X') {
						$priceAccess[] = $new;
					}
				}

				$namedData['prices'] = $prices;
				$namedData['priceAccess'] = $priceAccess;

				if (array_key_exists('ZipCodes', $namedData)) {
					$namedData['ZipCodes'] = preg_split('/\s*,\s*/', $namedData['ZipCodes'], -1, PREG_SPLIT_NO_EMPTY);
				}

				/*
				$passwordColumn = array_key_exists('password', $columnLookupMap) ? $columnLookupMap['password'] : NULL;
				if (!empty($passwordColumn)) {
					$namedData[$passwordColumn] = $this->security->hashPassword($namedData[$passwordColumn]);
				}
				*/

				$data[] = $namedData;
			}
		}

		$path = \Craft::$app->request->getFullPath();
		$pageUrl = \craft\helpers\UrlHelper::url($path);
		$params = [];

		if (is_int($limit) && $limit > 0) {
			$params[] = 'limit=' . $limit;
		} else {
			// No limit means all records were read
			$pageUrl = null;
		}

		if (!is_null($pageUrl)) {
			$offset = is_int($offset) && $offset > 0 ? $offset + $limit : $limit;
			$params[] = 'offset=' . $offset;
			$pageUrl = $pageUrl . '?' . implode('&', $params);
		}

		return $lowestRow > $highestRow ? null : $pageUrl;
	}

	protected function processCountryTravelDiscoveries($worksheet, &$data, $limit=100, $offset=0) {
		$highestRow = $worksheet->getHighestRow();
		$highestColumn = $worksheet->getHighestColumn();
		$headingsArray = $worksheet->rangeToArray('A1:' . $highestColumn . '1', null, true, true, true);
		$headingsArray = $headingsArray[1];
		$lowestRow = is_int($offset) && $offset > 0 ? 2 + $offset : 2;
		$highestRow = is_int($limit) && $limit > 0 ? ($lowestRow < $highestRow ? $lowestRow + $limit - 1 : $lowestRow - 1) : $highestRow;

		for ($row = $lowestRow; $row <= $highestRow; $row++) {
			$dataRow = $worksheet->rangeToArray('A' . $row . ':' . $highestColumn . $row, null, true, true, true);
			if ((isset($dataRow[$row]['A'])) && ($dataRow[$row]['A'] > '')) {
				$namedData = [];
				foreach ($headingsArray as $columnKey => $columnHeading) {
					$namedData[$columnHeading] = $dataRow[$row][$columnKey];
				}

				$travelDates = [];
				for ($i = 1; $i <= 6; $i++) {
					if (!empty($namedData["Start Date {$i}"])) {
						$travelDate['startDate'] = $namedData["Start Date {$i}"];
						$travelDate['endDate'] = $namedData["End Date {$i}"];
						$travelDate['range'] = $travelDate['startDate'] . (empty($travelDate['endDate']) ? '' : ' - ' . $travelDate['endDate']);

						$travelDates[] = $travelDate;
					}

				}
				$namedData['Travel Dates'] = $travelDates;

				if (array_key_exists('Enabled', $namedData)) {
					$namedData['Enabled'] = ($namedData['Enabled'] == 'Y');
				}

				if (array_key_exists('Location', $namedData)) {
					$namedData['Location'] = explode(',', $namedData['Location']);
				}

				if (array_key_exists('Season', $namedData)) {
					$namedData['Season'] = explode(',', $namedData['Season']);
				}

				if (array_key_exists('Tags', $namedData)) {
					$namedData['Tags'] = explode(',', $namedData['Tags']);
				}

				$namedData['Gallery Images'] = [];

				try {
					if (array_key_exists('Featured Image', $namedData) && !empty($namedData['Featured Image'])) {
						$dir = dirname($namedData['Featured Image']);
						$path = $_SERVER['DOCUMENT_ROOT'] . DIRECTORY_SEPARATOR . trim($dir, '/\\');
						$path = realpath($path);

						if (!empty($path) && basename($path) != 'maps' && is_dir($path)) {
							$files = scandir($path);
							foreach ($files as $file) {
								if (!preg_match('/^\./', $file) && is_file($path . DIRECTORY_SEPARATOR . $file)) {
									$namedData['Gallery Images'][] = $dir . DIRECTORY_SEPARATOR . $file;
								}
							}
						}
					}
				} catch (Exception $e) {
					// Fail silently
				}

				$data[] = $namedData;
			}
		}

		$path = \Craft::$app->request->getFullPath();
		$pageUrl = \craft\helpers\UrlHelper::url($path);
		$params = [];

		if (is_int($limit) && $limit > 0) {
			$params[] = 'limit=' . $limit;
		} else {
			// No limit means all records were read
			$pageUrl = null;
		}

		if (!is_null($pageUrl)) {
			$offset = is_int($offset) && $offset > 0 ? $offset + $limit : $limit;
			$params[] = 'offset=' . $offset;
			$pageUrl = $pageUrl . '?' . implode('&', $params);
		}

		return $lowestRow > $highestRow ? null : $pageUrl;
	}

	protected function loadSpreadsheet($dataSource) {
		$spreadsheet = false;
		$worksheet = false;

		try {
			$type = IOFactory::identify($dataSource);
			$reader = IOFactory::createReaderForFile($dataSource);
			$reader->setReadDataOnly(true);

			if (method_exists($reader, 'setSheetIndex')) {
				$reader->setSheetIndex(0);
			}

			switch (strtoupper($type)) {
				case 'CSV':
					$reader->setInputEncoding('UTF-8');
					$reader->setDelimiter(',');
					$reader->setEnclosure('"');
					break;
				default:
					break;
			}

			$spreadsheet = $reader->load($dataSource);
			//echo get_class($spreadsheet);

			$worksheet = $spreadsheet->getActiveSheet();
		} catch (Exception $e) {
			return false;
		}

		return $worksheet;
	}
}
