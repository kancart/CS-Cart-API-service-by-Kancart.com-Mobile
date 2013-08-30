<?php

if (!defined('IN_KANCART')) {
    header('HTTP/1.1 404 Not Found');
    die();
}

class BaseAction {

    protected $params = array();
    protected $paramSources = array('_GET', '_POST');
    protected $result = null;

    public function getParamSources() {
        return $this->paramSources;
    }

    public function init() {
        $this->result = new KancartResult();

        if (!is_long($_REQUEST['timestamp'])) {
            $timezone = date_default_timezone_get();
            date_default_timezone_set('UTC');
            $_REQUEST['timestamp'] = strtotime($_REQUEST['timestamp']);
            date_default_timezone_set($timezone);
        }
    }

    public function getParam($keyName) {
        $paramSources = $this->getParamSources();
        if (isset($this->_params[$keyName])) {
            return $this->_params[$keyName];
        } elseif (in_array('_GET', $paramSources) && (isset($_GET[$keyName]))) {
            return $_GET[$keyName];
        } elseif (in_array('_POST', $paramSources) && (isset($_POST[$keyName]))) {
            return $_POST[$keyName];
        }
        return null;
    }

    public function setParam($key, $val) {
        if (isset($key)) {
            $this->params[$key] = $val;
        }
    }

    public function setSuccess($info = null) {
        if (!is_null($this->result)) {
            $this->result->setSuccess($info);
        }
    }

    public function setError($code, $msg = null, $redirectTo = false) {
        if (!is_null($this->result)) {
            $this->result->setError($code, $msg, $redirectTo);
        }
    }

    public function getResult() {
        return $this->result;
    }

    public function execute() {
        
    }

    private function validateRequestSign(array $requestParams) {
        if (!isset($requestParams['sign']) || $requestParams['sign'] == '') {
            return false;
        }
        $sign = $requestParams['sign'];
        unset($requestParams['sign']);
        unset($requestParams['XDEBUG_SESSION_START']);
        ksort($requestParams);
        reset($requestParams);
        $tempStr = "";
        $tempStrNoStrip = '';
        foreach ($requestParams as $key => $value) {
            $tempStr = $tempStr . $key . stripslashes($value);   //error if product name contains a '
            $tempStrNoStrip = $tempStrNoStrip . $key . $value;
        }
        $tempStr = $tempStr . KANCART_APP_SECRET;
        $tempStrNoStrip = $tempStrNoStrip . KANCART_APP_SECRET;
        return (strtoupper(md5($tempStr)) === $sign || strtoupper(md5($tempStrNoStrip)) === $sign);
    }

    public function validate() {
        if (defined('KC_DEBUG_MODE') && KC_DEBUG_MODE) {
            return true;
        }
        $appkey = CryptoUtil::Crypto($this->getParam('app_key'), 'AES-256', KANCART_APP_SECRET, false);
        $_POST['app_key'] = $appkey;
        $_GET['app_key'] = $appkey;
        if (!$this->getParam('app_key') || $this->getParam('app_key') != KANCART_APP_KEY) {
            $this->setError(KancartResult::ERROR_SYSTEM_INVALID_API_VERSION,'Incorrect app_key');
            return false;
        }
        if (!$this->getParam('v') || $this->getParam('v') != '1.1') {
            $this->setError(KancartResult::ERROR_SYSTEM_INVALID_API_VERSION);
            return false;
        }
        if (!$this->getParam('timestamp') || abs(time() - intval($_REQUEST['timestamp'])) > 1800) {
            $this->setError(KancartResult::ERROR_SYSTEM_TIME_OVER_TEM_MIN);
            return false;
        }
        if (!$this->getParam('sign_method') || $this->getParam('sign_method') != 'md5') {
            $this->setError(KancartResult::ERROR_SYSTEM_INVALID_ENCRYPTION_METHOD);
            return false;
        }
        if (!$this->getParam('sign') || !$this->validateRequestSign($_POST)) {
            $this->setError(KancartResult::ERROR_SYSTEM_INVALID_SIGNATURE);
            return false;
        }
        return true;
    }

}

?>
