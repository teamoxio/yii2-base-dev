<?php


namespace app\common;


class Constants
{

    const DB_TABLE_OPTIONS = 'CHARACTER SET utf8 COLLATE utf8_general_ci ENGINE=InnoDB';
    const PHP_DATE_FORMAT = 'Y-m-d H:i:s';
    const PHP_DATE_FORMAT_SHORT = 'Y-m-d';
    const INVOICE_DATE_FORMAT = 'Ymd';

    const USER_ROLE_ADMIN = 1;
    const USER_ROLE_USER = 2;

    const USER_ADMINISTRATOR = 1;

    const USER_STATUS_ACTIVE = 1;
    const USER_STATUS_INACTIVE = 2;
    const USER_STATUS_BANNED = 3;

    const BACKGROUND_TASK_TYPE_PING = 1;

    const STATUS_ACTIVE = 1;
    const STATUS_INACTIVE = 0;

    const YES_FLAG = 1;
    const NO_FLAG = 0;

    const LOG_TYPE_INIT = "init";
    const LOG_TYPE_ERROR = "error";
    const LOG_TYPE_ERROR_PERSIST = "error_persist";
    const LOG_TYPE_ERROR_EXCEPTION = "error_exception";
    const LOG_TYPE_USER_LOGIN = "user_login";
    const LOG_TYPE_USER_REGISTER = "user_register";
    const LOG_TYPE_USER_FAILED_LOGIN = "user_failed_login";
    const LOG_TYPE_USER_FORGOT_PASSWORD = "user_forgot_password";
    const LOG_TYPE_USER_ACCOUNT_ACTIVATED = "user_account_activated";
    const LOG_TYPE_USER_PASSWORD_CHANGED = "user_password_changed";

    const LOG_TYPE_DISABLE_2FA = 'disable_2fa';
    const LOG_TYPE_ENABLE_2FA = 'enable_2fa';

    const SETTINGS_RECAPTCHA_KEY = 1;
    const SETTINGS_RECAPTCHA_SECRET = 2;

    const CURRENCY_PRECISION = 2;
    const BTC_PRECISION = 6;

    const FILE_TYPE_SSH_KEY = 'ssh_key';

    const FILE_PATH_SYSTEM = 'system';
    const FILE_PATH_SSH = 'keys';

    const ADMIN_REFERRAL_CODE = "teamoxio";

    const CRYPTO_BUFFER = 0; //increase the price of crypto prices using this as a percentage
    const CRYPTO_WITHDRAWAL_BUFFER = 0; //decrease the price of crypto prices using this as a percentage

    const CMD_ENABLE_TWO_FA = 'enable_two_fa';
    const CMD_DISABLE_TWO_FA = 'disable_two_fa';

    const SECONDS_JWT_IS_VALID = 3600;//1 hour

    const JWT_IDENTIFIER = '4f1m2003ffeleadmedia';

    const CORS_ALLOWED_DOMAINS = array(
        'http://localhost:4200','https://localhost:4200'
    );

    const CORS_ALLOWED_HEADERS = array(
        'Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Authorization'
    );
}

