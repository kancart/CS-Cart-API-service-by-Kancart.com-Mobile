<?php

if (!defined('IN_KANCART')) {
    header('HTTP/1.1 404 Not Found');
    die();
}

class kancart_user_forgotpwd_action extends BaseAction {

    public function execute() {

        $email = !empty($_POST['uname']) ? trim($_POST['uname']) : '';
        $uid = db_get_field("SELECT user_id FROM ?:users WHERE email = ?s", $email);
        $u_data = fn_get_user_info($uid, false);
        if (!empty($u_data['email'])) {
            $_data = array(
                'object_id' => $u_data['user_id'],
                'object_type' => 'U',
                'ekey' => md5(uniqid(rand())),
                'ttl' => strtotime("+1 day")
            );

            db_query("REPLACE INTO ?:ekeys ?e", $_data);

            $zone = !empty($u_data['user_type']) ? $u_data['user_type'] : 'C';

            $view_mail->assign('ekey', $_data['ekey']);
            $view_mail->assign('zone', $zone);

            $result = fn_send_mail($u_data['email'], Registry::get('settings.Company.company_users_department'), 'profiles/recover_password_subj.tpl', 'profiles/recover_password.tpl', '', $u_data['lang_code']);

            if (!result) {
                $this->setError(KancartResult::ERROR_SYSTEM_SERVICE_UNAVAILABLE, 'fail_send_password');
            }
        } else {
            $this->setError(KancartResult::ERROR_USER_INVALID_USER_DATA, 'username_not_match_email');
        }
    }

}

?>
