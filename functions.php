<?php

require 'plugin-update-checker/plugin-update-checker.php';

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$myUpdateChecker = PucFactory::buildUpdateChecker(
	'https://github.com/searchly-ai/searchly-ai-woocommerce',
	__FILE__,
	'searchly-ai-woocommerce'
);

$myUpdateChecker->setBranch('main');