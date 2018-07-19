<?php
/**
 * Shop System Plugins - Terms of Use
 *
 * The plugins offered are provided free of charge by Wirecard AG and are explicitly not part
 * of the Wirecard AG range of products and services.
 *
 * They have been tested and approved for full functionality in the standard configuration
 * (status on delivery) of the corresponding shop system. They are under General Public
 * License version 3 (GPLv3) and can be used, developed and passed on to third parties under
 * the same terms.
 *
 * However, Wirecard AG does not provide any guarantee or accept any liability for any errors
 * occurring when used in an enhanced, customized shop system configuration.
 *
 * Operation in an enhanced, customized configuration is at your own risk and requires a
 * comprehensive test phase by the user of the plugin.
 *
 * Customers use the plugins at their own risk. Wirecard AG does not guarantee their full
 * functionality neither does Wirecard AG assume liability for any disadvantages related to
 * the use of the plugins. Additionally, Wirecard AG does not guarantee the full functionality
 * for customized shop systems or installed plugins of other vendors of plugins within the same
 * shop system.
 *
 * Customers are responsible for testing the plugin's functionality before starting productive
 * operation.
 *
 * By installing the plugin into the shop system the customer agrees to these terms of use.
 * Please do not use the plugin if you do not agree to these terms of use!
 */

require_once(dirname(__FILE__) . '/pg_basket.php');
require_once(dirname(__FILE__) . '/pg_account_holder.php');

use Wirecard\PaymentSdk\Transaction\Transaction;

/**
 * Class AdditionalInformationHelper
 *
 * @since 1.0.0
 */
class AdditionalInformationHelper extends Model {
	const OPENCART_GATEWAY_WIRECARD_VERSION = '1.0.0';
	const OPENCART_GATEWAY_WIRECARD_NAME = 'Wirecard OpenCart Extension';
	const CURRENCYCODE = 'currency_code';
	const CURRENCYVALUE = 'currency_value';

	/**
	 * @var string
	 * @since 1.0.0
	 */
	private $prefix;

	/**
	 * @var Config
	 * @since 1.0.0
	 */
	private $config;

	/**
	 * AdditionalInformationHelper constructor.
	 * @param $registry
	 * @param $prefix
	 * @since 1.0.0
	 */
	public function __construct($registry, $prefix, $config) {
		parent::__construct($registry);
		$this->prefix = $prefix;
		$this->config = $config;
	}

	/**
	 * @param Transaction $transaction
	 * @param array $items
	 * @param array $shipping
	 * @param array $currency
	 * @param float $total
	 * @return Transaction
	 * @since 1.0.0
	 */
	public function addBasket($transaction, $items, $shipping, $currency, $total) {
		$basket_factory = new PGBasket($this);
		$transaction->setBasket($basket_factory->getBasket($transaction, $items, $shipping, $currency, $total));

		return $transaction;
	}

	/**
	 * Add account holder to transaction.
	 *
	 * @param Transaction $transaction
	 * @param $order
	 * @return Transaction
	 * @since 1.0.0
	 */
	public function addAccountHolder($transaction, $order) {
		$account_holder = new PGAccountHolder();

		$transaction->setAccountHolder($account_holder->createAccountHolder($order, $account_holder::BILLING));
		$transaction->setShipping($account_holder->createAccountHolder($order, $account_holder::SHIPPING));

		return $transaction;
	}

	/**
	 * Create identification data
	 *
	 * @param Transaction $transaction
	 * @param array $order
	 * @return Transaction
	 * @since 1.0.0
	 */
	public function setIdentificationData($transaction, $order) {
		$custom_fields = new \Wirecard\PaymentSdk\Entity\CustomFieldCollection();
		$custom_fields->add(new \Wirecard\PaymentSdk\Entity\CustomField('orderId', $order['order_id']));
		$custom_fields->add(new \Wirecard\PaymentSdk\Entity\CustomField('shopName', 'OpenCart'));
		$custom_fields->add(new \Wirecard\PaymentSdk\Entity\CustomField('shopVersion', VERSION));
		$custom_fields->add(new \Wirecard\PaymentSdk\Entity\CustomField('pluginName', self::OPENCART_GATEWAY_WIRECARD_NAME));
		$custom_fields->add(new \Wirecard\PaymentSdk\Entity\CustomField('pluginVersion', self::OPENCART_GATEWAY_WIRECARD_VERSION));
		$transaction->setCustomFields($custom_fields);
		$transaction->setLocale(substr($order['language_code'], 0, 2));

		return $transaction;
	}

	/**
	 * Create additional information data
	 *
	 * @param Transaction $transaction
	 * @param array $order
	 * @return Transaction
	 * @since 1.0.0
	 */
	public function setAdditionalInformation($transaction, $order) {
		if ($transaction instanceof \Wirecard\PaymentSdk\Transaction\PayPalTransaction) {
			$transaction->setOrderDetail(sprintf(
				'%s %s %s',
				$order['email'],
				$order['firstname'],
				$order['lastname']
			));
		}

		if ($order['ip']) {
			$transaction->setIpAddress($order['ip']);
		} else {
			$transaction->setIpAddress($_SERVER['REMOTE_ADDR']);
		}

		if (strlen($order['customer_id'])) {
			$transaction->setConsumerId($order['customer_id']);
		}
		$transaction->setOrderNumber($order['order_id']);
		$transaction->setDescriptor($this->createDescriptor($order));

		return $transaction;
	}

	/**
	 * Create descriptor including shopname and ordernumber
	 *
	 * @param array $order
	 * @return string
	 * @since 1.0.0
	 */
	public function createDescriptor($order) {
		return sprintf(
			'%s %s',
			substr( $order['store_name'], 0, 9),
			$order['order_id']
		);
	}

	/**
	 * Convert amount with currency format
	 *
	 * @param float $amount
	 * @param array $currency
	 * @return float
	 * @since 1.0.0
	 */
	public function convert($amount, $currency) {
		return ($currency[self::CURRENCYVALUE]) ? (float)$amount * $currency[self::CURRENCYVALUE] : (float)$amount;
	}

	/**
	 * Convert amount with currency format including tax
	 *
	 * @param float $amount
	 * @param array $currency
	 * @param int $taxClassId
	 * @return float
	 * @since 1.0.0
	 */
	public function convertWithTax($amount, $currency, $taxClassId) {
		return $this->tax->calculate($this->convert($amount, $currency), $taxClassId, 'P');
	}
}
