<?php

namespace Push2\Model\Onesignal;

require_once path('/lib/onesignal/vendor/autoload.php');

use Push2\Model\Onesignal\Targets\AbstractTarget;
use Push2\Model\Onesignal\Targets\Segment;
use Push2\Model\Onesignal\Targets\Player;

use Core\Model\Base as BaseModel;

/**
 * Class Message
 * @package Push2\Model\Onesignal
 *
 * @method $this setAppId($appId)
 * @method $this setValueId($valueId)
 * @method $this setTitle($title)
 * @method $this setSubtitle($subtitle)
 * @method $this setBody($body)
 * @method $this setBigPicture($big_picture)
 * @method $this setBigPictureUrl($big_picture_url)
 * @method $this setSendAfter($send_after)
 * @method $this setDelayedOption($delayed_option)
 * @method $this setDeliveryTimeOfDay($delivery_time_of_day)
 * @method $this setTargets($targets)
 * @method $this setOnesignalId($onesignal_id)
 * @method $this setExternalId($external_id)
 * @method $this setRecipients($recipients)
 * @method $this setOpenFeature(bool $open_feature)
 * @method $this setIsTest(bool $is_test)
 * @method $this setIsForModule(bool $is_for_module)
 * @method $this setIsIndividual(bool $is_individual)
 * @method $this setFeatureId(integer $feature_id)
 * @method $this setFeatureUrl(string $feature_url)
 * @method $this setPlayerIds(array $player_ids)
 * @method $this setActionValue($action_value)
 * @method $this setSegment($segment)
 * @method Db\Table\Message getTable()
 * @method integer getAppId()
 * @method integer getValueId()
 * @method string getTitle()
 * @method string getSubtitle()
 * @method string getBody()
 * @method string getSendAfter()
 * @method string getDelayedOption()
 * @method string getDeliveryTimeOfDay()
 * @method string getActionUrl()
 * @method string getOnesignalId()
 * @method string getExternalId()
 * @method string getRecipients()
 * @method bool getOpenFeature()
 * @method bool getIsTest()
 * @method bool getIsForModule()
 * @method bool getIsIndividual()
 * @method integer getFeatureId()
 * @method array getPlayerIds()
 * @method string getActionValue()
 * @method string getSegment()
 * @method AbstractTarget[] getTargets()
 */
class Message extends BaseModel
{

    /**
     * @var string
     */
    public $_db_table = Db\Table\Message::class;

    /**
     * @param $app_id
     * @param $player_id
     * @return mixed
     * @throws \Zend_Exception
     */
    public function findAllForPlayer($app_id, $player_id = null)
    {
        return $this->getTable()->findAllForPlayer($app_id, $player_id);
    }

    /**
     * @param $data
     * @return $this
     */
    public function fromArray($data): self
    {
        // Failsafe in case the application is not defined in the context
        try {
            $application = self::sGetApplication();
            $defaultSegment = $application->getOnesignalDefaultSegment();
            $defaultSegment = !empty($defaultSegment) ? $defaultSegment : "Subscribed Users";
        } catch (\Exception $e) {
            $defaultSegment = "Subscribed Users";
        }

        $this->setAppId($data['app_id']);
        $this->setValueId($data['value_id']);
        $this->setTitle($data['title']);
        $this->setSubtitle($data['subtitle'] ?? null);
        $this->setBody($data['body']);
        $this->setBigPicture($data['big_picture'] ?? null);
        $this->setBigPictureUrl($data['big_picture_url'] ?? null);
        $this->setSendAfter($data['send_after'] ?? null);
        $this->setDelayedOption($data['delayed_option'] ?? null);
        $this->setDeliveryTimeOfDay($data['delivery_time_of_day'] ?? null);
        $this->setIsForModule(filter_var($data['is_for_module'] ?? null, FILTER_VALIDATE_BOOLEAN));
        $this->setIsTest(filter_var($data['is_test'] ?? null, FILTER_VALIDATE_BOOLEAN));
        $this->setIsIndividual(filter_var($data['is_individual'] ?? null, FILTER_VALIDATE_BOOLEAN));
        $this->setOpenFeature(filter_var($data['open_feature'] ?? null, FILTER_VALIDATE_BOOLEAN));
        $this->setFeatureId($data['feature_id'] ?? null);
        $this->setFeatureUrl($data['feature_url'] ?? null);
        $this->setPlayerIds($data['player_ids'] ?? null);
        $this->setSegment($data['segment'] ?? $defaultSegment);
        $this->addTargets(new Segment($data['segment'] ?? $defaultSegment));

        $this->checkSchedulingOptions();
        $this->checkTargets();
        $this->checkOpenFeature();

        return $this;
    }

    /**
     * @return array|mixed|string|null
     */
    public function getBigPicture()
    {
        $baseUrl = self::sGetBaseUrl();
        $bigPictureUrl = $this->getData('big_picture_url');
        $bigPicture = $this->getData('big_picture');

        $cover_image = !empty($bigPictureUrl) ? $bigPictureUrl : $bigPicture;
        if (!preg_match("#^https?://#", $cover_image)) {
            $cover_image = $baseUrl . "/images/application" . $cover_image;
        }

        if ($cover_image === $baseUrl) {
            return null;
        }

        return $cover_image;
    }

    /**
     * @return void
     */
    public function checkSchedulingOptions()
    {
        // send_after is the determining factor for scheduling
        $sendAfter = $this->getSendAfter();
        if (!empty($sendAfter)) {
            $this->setDelayedOption('timezone');
            if (preg_match("/^([0-1]?[0-9]|2[0-3]):[0-5][0-9]$/", $this->getDeliveryTimeOfDay()) === 0) {
                $this->setDeliveryTimeOfDay('9:00 AM');
            }
        }
    }

    /**
     * @return void
     */
    public function checkTargets()
    {
        if ($this->getIsIndividual()) {
            $this->clearTargets();
            $this->addTargets(new Player($this->getPlayerIds()));
            $this->setSegment('Individual');
        }
    }

    /**
     * @return void
     */
    public function checkOpenFeature()
    {
        if ($this->getOpenFeature()) {
            $this->setActionValue($this->getFeatureId());
        }
    }

    /**
     * @return $this
     */
    public function clearTargets(): self
    {
        return $this->setTargets([]);
    }

    /**
     * @param AbstractTarget $targets
     * @return $this
     */
    public function addTargets(AbstractTarget $targets): self
    {
        $newTargets = $this->getTargets();
        $newTargets[] = $targets;
        return $this->setTargets($newTargets);
    }
}