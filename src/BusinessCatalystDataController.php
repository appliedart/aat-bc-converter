<?php

namespace appliedart\businesscatalyst;

use Craft;

class BusinessCatalystDataController extends Controller {
	protected $allowAnonymous = true;

	public function actionDataTransformer($clientCode, $dataType) {
		$data = [];
		return $this->asJson($data);
	}
}
