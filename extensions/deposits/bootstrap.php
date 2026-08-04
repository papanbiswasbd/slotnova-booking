<?php
/**
 * Bootstrap entry point for SlotNova Deposits Extension.
 *
 * @package SlotNova\Extensions\Deposits
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/src/Services/DepositSettingsRenderer.php';
require_once __DIR__ . '/src/Services/DepositCartCalculator.php';
require_once __DIR__ . '/src/Services/DepositFrontendRenderer.php';
require_once __DIR__ . '/src/Services/DepositOrderStatusManager.php';
require_once __DIR__ . '/src/Services/DepositMyAccountManager.php';
require_once __DIR__ . '/src/DepositsExtension.php';

return new \SlotNova\Extensions\Deposits\DepositsExtension();
