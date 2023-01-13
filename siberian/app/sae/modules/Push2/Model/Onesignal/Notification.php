<?php

namespace Push2\Model\Onesignal;

use Push2\Model\Onesignal\Targets\Android;
use Push2\Model\Onesignal\Targets\Ios;
use Push2\Model\Onesignal\Targets\Player;
use Push2\Model\Onesignal\Targets\Segment;
use Push2\Model\Onesignal\Targets\Web;

use Siberian\Hook as Hook;

require_once path('/lib/onesignal/vendor/autoload.php');

/**
 * Class Notification
 * @package Push\Model\Onesignal
 */
class Notification {

    public $application;

    public $APP_ID;
    public $APP_KEY_TOKEN;

    /**
     * @var \onesignal\client\api\DefaultApi
     */
    public $apiInstance;
    /**
     * @var \onesignal\client\model\Notification
     */
    public $notification;

    /**
     * Notification constructor.
     * @param $app_id
     * @param $app_key_token
     */
    public function __construct($app_id, $app_key_token)
    {
        $this->APP_ID = $app_id;
        $this->APP_KEY_TOKEN = $app_key_token;

        $config = \onesignal\client\Configuration::getDefaultConfiguration()
            ->setAppKeyToken($this->APP_KEY_TOKEN);

        $this->apiInstance = new \onesignal\client\api\DefaultApi(
            new \GuzzleHttp\Client(),
            $config
        );
    }

    /**
     * @param $application
     * @return void
     */
    public function setApplication($application) {
        $this->application = $application;
    }

    /**
     * @param $message \Push_Model_Message
     */
    public function regularPush(\Push2\Model\Message $message)
    {
        $title = new \onesignal\client\model\StringMap();
        $title->setEn($message->getTitle());

        $body = new \onesignal\client\model\StringMap();
        $body->setEn($message->getText());

        $newUuid = \Siberian\UUID::v4();

        $this->notification = new \onesignal\client\model\Notification();
        $this->notification->setExternalId($newUuid);
        $this->notification->setAppId($this->APP_ID);
        $this->notification->setHeadings($title);
        $this->notification->setContents($body);

        // Cover image, if exists!
        $coverUrl = $message->getCoverUrl();
        if (!preg_match("#^https?://#", $coverUrl)) {
            $coverUrl = $message->getData("base_url") . $coverUrl;
        }

        if ($coverUrl === $message->getData("base_url")) {
            $coverUrl = null;
        }
        $this->notification->setBigPicture($coverUrl);


        // Geolocated push
        if ($message->getLongitude() &&
            $message->getLatitude()) {

            $filterLocation = new \onesignal\client\model\FilterNotificationTarget([
                'location' => [
                    'radius' => (int) $message->getRadius(),
                    'lat' => (float) $message->getLatitude(),
                    'long' => (float) $message->getLongitude(),
                ]
            ]);
            $this->notification->setFilters([$filterLocation]);
        }

        // Push icon color
        $pushColor = strtoupper($this->application->getAndroidPushColor() ?? '#0099C7');

        if (is_numeric($message->getActionValue())) {
            $option_value = new Application_Model_Option_Value();
            $option_value->find($message->getActionValue());

            // In case we use only value_id
            if (!$this->application || !$this->application->getId()) {
                $application = (new Application_Model_Application())->find($option_value->getAppId());
            }

            $mobileUri = $option_value->getMobileUri();
            if (preg_match('/^goto\/feature/', $mobileUri)) {
                $action_url = sprintf("/%s/%s/value_id/%s",
                    $application->getKey(),
                    $mobileUri,
                    $option_value->getId());
            } else {
                $action_url = sprintf("/%s/%sindex/value_id/%s",
                    $application->getKey(),
                    $option_value->getMobileUri(),
                    $option_value->getId());
            }
        } else {
            $action_url = $message->getActionValue();
        }

        $this->notification->setContentAvailable(true);
        $this->notification->setData([
            'soundname' => 'sb_beep4',
            'title' => $message->getTitle(),
            'body' => $message->getText(),
            'sound' => 'sb_beep4',
            'icon' => 'ic_icon',
            'color' => $pushColor,
            'action_value' => $action_url,
        ]);

        $this->notification->setAndroidSound('sb_beep4');

        $this->notification->setPriority(10);

        //$this->setTargets($notification, $message->getTargets());

        // Test targets
        $targets = new Segment(['Subscribed Users']);
        $this->setTargets($this->notification, $targets);

        //
        $result = Hook::trigger('push.message.android.parsed',
            [
                'notification' => $this->notification,
                'application' => $this->application
            ]);

        $this->notification = $result['notification'];
    }

    /**
     * @param \Push2\Model\Message $message
     * @return \onesignal\client\model\Notification
     */
    public function silentPush(\Push2\Model\Message $message): \onesignal\client\model\Notification
    {
        $notification = new \onesignal\client\model\Notification();
        $notification->setAppId($this->APP_ID);

        $this->setTargets($notification, $message->getTargets());

        $notification->setContentAvailable(true);
        $notification->setData([
            'cabride' => true,
            'page' => 42,
            'action' => 'show'
        ]);

        return $notification;
    }

    /**
     * Agnostic method to correctly set the users/devices/segments depending on the object type
     *
     * @param \onesignal\client\model\Notification $notification
     * @param $targets
     */
    public function setTargets(\onesignal\client\model\Notification $notification, $targets)
    {
        switch (get_class($targets)) {
            case Android::class:
                $notification->setIncludeAndroidRegIds($targets->getTargets());
                break;

            case Ios::class:
                $notification->setIncludeIosTokens($targets->getTargets());
                break;

            case Web::class:
                $notification->setIncludeChromeRegIds($targets->getTargets());
                break;

            case Segment::class:
                $notification->setIncludedSegments($targets->getTargets());
                break;

            case Player::class:
                $notification->setIncludePlayerIds($targets->getTargets());
                break;
        }
    }

    /**
     * @param \onesignal\client\model\Notification $notification
     * @throws \onesignal\client\ApiException
     */
    public function sendNotification(\onesignal\client\model\Notification $notification) {
        $result = $this->apiInstance->createNotification($notification);
        file_put_contents(path("/var/log/onesignal.log"), print_r($result, true), FILE_APPEND);
    }
}