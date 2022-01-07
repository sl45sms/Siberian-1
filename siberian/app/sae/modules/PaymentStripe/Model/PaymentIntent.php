<?php

namespace PaymentStripe\Model;

use Core\Model\Base;
use PaymentStripe\Model\Stripe as PaymentStripe;
use Siberian\Exception;
use Application_Model_Application as SiberianApplication;

/**
 * Class PaymentIntent
 * @package PaymentStripe\Model
 *
 * @method Db\Table\PaymentIntent getTable()
 * @method integer getId()
 * @method string getStatus()
 */
class PaymentIntent extends Base
{
    /**
     * @var string
     */
    protected $_db_table = Db\Table\PaymentIntent::class;

    /**
     * @param $reason
     * @param null $cron
     */
    public function cancel($reason, $cron = null)
    {

    }

    /**
     * @return array|mixed|string|null
     * @throws Exception
     * @throws \Zend_Exception
     */
    public function getToken ()
    {
        return PaymentStripe::isProd(SiberianApplication::getApplication()->getId()) ?
            $this->getData('token') : $this->getData('test_token');
    }

    /**
     * @param $token
     * @return PaymentIntent
     * @throws Exception
     * @throws \Zend_Exception
     */
    public function setToken($token)
    {
        return PaymentStripe::isProd(SiberianApplication::getApplication()->getId()) ?
            $this->setData('token', $token) : $this->setData('test_token', $token);
    }

    /**
     * @return array|string
     */
    public function toJson()
    {
        $payload = [
            'id' => (integer) $this->getId(),
            'token' => (string) $this->getToken(),
            'status' => (string) $this->getStatus(),
        ];

        return $payload;
    }
}