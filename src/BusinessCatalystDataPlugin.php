<?php

namespace appliedart\businesscatalyst;

use Craft;
use craft\base\Plugin;
use craft\web\UrlManager;
use craft\events\RegisterUrlRulesEvent;
use yii\base\Event;

class BusinessCatalystDataPlugin extends Plugin
{
	/** @var array */
	public $controllerMap = [
		'default' => BusinessCatalystDataController::class,
	];

	public function init()
	{
		parent::init();

		// Custom initialization code goes here...
		Event::on(UrlManager::class, UrlManager::EVENT_REGISTER_SITE_URL_RULES, function (RegisterUrlRulesEvent $event) {
			$event->rules['data/transform-data/<clientCode:\w+>-<dataType:\w+>.json'] = 'aat-bc-converter/default/data-transformer';
		});
	}
}
