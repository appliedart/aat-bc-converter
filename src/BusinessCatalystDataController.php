<?php

namespace appliedart\businesscatalyst;

use Craft;
use craft\web\Controller;

class BusinessCatalystDataController extends Controller {
	protected $allowAnonymous = true;

	public function actionDataTransformer($clientCode, $dataType) {
		$data = [];
		return $this->asJson($data);
	}
}
