<?php

namespace app\models\activerecord;

use app\common\BaseActiveRecord;
use app\common\Constants;
use app\common\exceptions\PersistException;
use app\common\Helper;
use app\common\SystemLog;
use Da\QrCode\QrCode;
use Da\TwoFA\Manager;
use Da\TwoFA\Service\GoogleQrCodeUrlGeneratorService;
use Da\TwoFA\Service\TOTPSecretKeyUriGeneratorService;
use Exception;
use IP2Location\Database;
use Lcobucci\JWT\Token;
use Yii;
use yii\helpers\Url;
use yii\web\BadRequestHttpException;
use yii\web\Cookie;
use yii\web\IdentityInterface;

/**
 * This is the model class for table "users".
 *
 * @property int $id
 * @property string $identifier
 * @property string $username
 * @property string|null $first_name
 * @property string|null $last_name
 * @property string $email
 * @property string $password
 * @property int $status_id
 * @property string|null $code
 * @property int|null $ip_country_id
 * @property string $time
 * @property string $updated_on
 * @property int $is_two_fa
 * @property string|null $two_fa_secret
 * @property string $auth_key
 * @property string $ip
 * @property string $useragent
 * @property int $role_id
 * @property string $referral_code
 * @property int|null $referred_by
 * @property Logs[] $logs
 * @property UserLoginHistory[] $userLoginHistories
 * @property Countries $country
 * @property Users $referredBy
 * @property Users[] $users
 * @property UserRoles $role
 * @property UserStatus $status
 */
class Users extends BaseActiveRecord implements IdentityInterface
{


    public function generatePassword($password){
        $password = hash("sha256",$password);
        return  Yii::$app->security->generatePasswordHash($password);
    }

    public function beforeValidate()
    {
        if($this->isNewRecord){

            //check sponsor
            if($this->referral_code == "")
                $this->referral_code = Constants::ADMIN_REFERRAL_CODE;

            $sponsor = Helper::getSponsor($this->referral_code);
            if($sponsor==null) {
                $this->addError("sponsor", "Invalid sponsor code");
                return false;
            }

            $this->password = hash("sha256",$this->new_password);
            $this->password = Yii::$app->security->generatePasswordHash($this->password);

            $this->status_id = Constants::USER_STATUS_INACTIVE;

            $this->auth_key = Yii::$app->security->generateRandomString();

            $this->role_id = Constants::USER_ROLE_USER;

            $this->referral_code = $this->generateReferralCode();
            $this->referred_by = $sponsor->id;

        }

        return parent::beforeValidate();
    }

    public function afterSave($insert, $changedAttributes)
    {
        if($insert){

            $this->postRegistration();

        }
        parent::afterSave($insert, $changedAttributes);
    }


    public function generateReferralCode(){
        $referral_code = Helper::generateRandomInteger();
        //check
        $user = Users::find()->where(["referral_code"=>$referral_code])->one();
        if($user==null)
            return $referral_code;
        else
            return $this->generateReferralCode();
    }

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'users';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['!identifier', 'username', 'email', 'password', 'status_id', '!auth_key', 'ip', 'useragent', 'role_id', 'referral_code', ], 'required'],
            [['status_id',  'ip_country_id', 'is_two_fa', '!role_id',  'referred_by'], 'integer'],
            [['time', 'updated_on'], 'safe'],
            [['identifier', 'auth_key'], 'string', 'max' => 500],
            [['username', 'first_name', 'last_name', 'email', 'two_fa_secret', 'referral_code'], 'string', 'max' => 200],

            [['password'], 'string', 'max' => 600],
            [['code','useragent'], 'string', 'max' => 800],
            [['ip'], 'string', 'max' => 50],

            [['email'], 'email'],
            [['username'], 'unique'],
            [['email'], 'unique'],

            ['username', 'match', 'pattern' => '/^[A-Za-z0-9]{3,20}$/iU', 'message' => 'Username can be alphanumeric and minimum 3 characters and maximum 20. No spaces allowed.'],

            [['ip_country_id'], 'exist', 'skipOnError' => true, 'targetClass' => Countries::class, 'targetAttribute' => ['ip_country_id' => 'id']],
            [['referred_by'], 'exist', 'skipOnError' => true, 'targetClass' => Users::class, 'targetAttribute' => ['referred_by' => 'id']],
            [['role_id'], 'exist', 'skipOnError' => true, 'targetClass' => UserRoles::class, 'targetAttribute' => ['role_id' => 'id']],
            [['status_id'], 'exist', 'skipOnError' => true, 'targetClass' => UserStatus::class, 'targetAttribute' => ['status_id' => 'id']],


        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'identifier' => 'Identifier',
            'username' => 'Username',
            'first_name' => 'First Name',
            'last_name' => 'Last Name',
            'email' => 'Email',
            'password' => 'Password',
            'status_id' => 'Status ID',
            'code' => 'Code',
            'ip_country_id' => 'Country ID',
            'time' => 'Time',
            'updated_on' => 'Updated On',
            'is_two_fa' => 'Is Two Fa',
            'two_fa_secret' => 'Two Fa Secret',
            'auth_key' => 'Auth Key',
            'ip' => 'Ip',
            'useragent' => 'Useragent',
            'role_id' => 'Role ID',
            'referral_code' => 'Referral Code',
            'referred_by' => 'Referred By',

        ];
    }

    /**
     * Gets query for [[Logs]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getLogs()
    {
        return $this->hasMany(Logs::className(), ['user_id' => 'id']);
    }


    /**
     * Gets query for [[UserLoginHistories]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getUserLoginHistories()
    {
        return $this->hasMany(UserLoginHistory::className(), ['user_id' => 'id']);
    }

    /**
     * Gets query for [[Country]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getCountry()
    {
        return $this->hasOne(Countries::className(), ['id' => 'ip_country_id']);
    }

    /**
     * Gets query for [[ReferredBy]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getReferredBy()
    {
        return $this->hasOne(Users::className(), ['id' => 'referred_by']);
    }

    /**
     * Gets query for [[Users]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getReferrals()
    {
        return $this->hasMany(Users::className(), ['referred_by' => 'id']);
    }

    /**
     * Gets query for [[Role]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getRole()
    {
        return $this->hasOne(UserRoles::className(), ['id' => 'role_id']);
    }

    /**
     * Gets query for [[Status]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getStatus()
    {
        return $this->hasOne(UserStatus::className(), ['id' => 'status_id']);
    }

    /**
     * Finds an identity by the given token.
     * @param \Lcobucci\JWT\Token $token
     * @param mixed $type the type of the token. The value of this parameter depends on the implementation.
     * For example, [[\yii\filters\auth\HttpBearerAuth]] will set this parameter to be `yii\filters\auth\HttpBearerAuth`.
     * @return null|\yii\db\ActiveRecord
     * Null should be returned if such an identity cannot be found
     * or the identity is not in an active state (disabled, deleted, etc.)
     * @throws PersistException
     */
    public static function findIdentityByAccessToken($token, $type = null)
    {
        $identity = null;
        $jwt = Yii::$app->jwt;
        $signer = $jwt->getSigner('HS256');
        $key = $jwt->getKey();

        //validate token
        if($token->verify($signer,$key)) {

            $identity = self::find()
                ->where([
                    'identifier'=>$token->getClaim('user_identifier')
                ])->one();
            if($identity) {
                //verify hash
                $session = UserSessions::find()
                    ->where(['hash' => (string)$token])
                    ->one();
                if($session == null)
                    $identity = null;
            }

        }

        return $identity;
    }

    /**
     * Finds an identity by the given ID.
     * @param string|int $id the ID to be looked for
     * @return IdentityInterface|null the identity object that matches the given ID.
     * Null should be returned if such an identity cannot be found
     * or the identity is not in an active state (disabled, deleted, etc.)
     */
    public static function findIdentity($id)
    {
        return self::findOne($id);
    }

    /**
     * Returns an ID that can uniquely identify a user identity.
     * @return string|int an ID that uniquely identifies a user identity.
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Returns a key that can be used to check the validity of a given identity ID.
     *
     * The key should be unique for each individual user, and should be persistent
     * so that it can be used to check the validity of the user identity.
     *
     * The space of such keys should be big enough to defeat potential identity attacks.
     *
     * This is required if [[User::enableAutoLogin]] is enabled. The returned key will be stored on the
     * client side as a cookie and will be used to authenticate user even if PHP session has been expired.
     *
     * Make sure to invalidate earlier issued authKeys when you implement force user logout, password change and
     * other scenarios, that require forceful access revocation for old sessions.
     *
     * @return string a key that is used to check the validity of a given identity ID.
     * @see validateAuthKey()
     */
    public function getAuthKey()
    {
        return $this->auth_key;
    }

    /**
     * Validates the given auth key.
     *
     * This is required if [[User::enableAutoLogin]] is enabled.
     * @param string $authKey the given auth key
     * @return bool whether the given auth key is valid.
     * @see getAuthKey()
     */
    public function validateAuthKey($authKey)
    {
        return $authKey == $this->auth_key;
    }


    public function updatePassword($password){
        $password = hash("sha256",$password);
        $code = hash("sha256",Helper::generateRandomKey());

        $this->password = Yii::$app->security->generatePasswordHash($password);
        $this->code = $code;

        $this->update(false,['password', 'code']);

        SystemLog::log($this->id,'Password updated',Constants::LOG_TYPE_USER_PASSWORD_CHANGED);

        return true;
    }

    public function validatePassword($password){
        $password = hash("sha256",$password);
        return Yii::$app->security->validatePassword($password,$this->password);
    }

    public static function findByUsername($username){
        return self::find()->where(['username'=>$username])
            ->one();
    }

    public function failedLogin(){
        SystemLog::log($this->id,
            'Failed login',
            Constants::LOG_TYPE_USER_FAILED_LOGIN
        );
    }

    public function isSessionValid(){
        $session = UserSessions::find()->where(['user_id'=>$this->id])->one();
        if($session == null || !Yii::$app->session->has('session_hash')){
            Yii::$app->user->logout();
            return false;
        }

        if($session->hash == Yii::$app->session->get('session_hash'))
            return true;
        else{
            Yii::$app->user->logout();
            return false;
        }
    }

    public function isSessionUnique(){
        //delete all sessions
        UserSessions::deleteAll(['user_id'=>$this->id]);
        return true;
    }

    public function postLogin($jwt = false){

        $this->isSessionUnique();

        //insert
        $u = new UserLoginHistory();
        $u->user_id = $this->id;
        $u->validate();

        try {
            $db = new Database(Yii::getAlias("@app") . '/data/ip2location.bin', Database::FILE_IO);

            $records = $db->lookup($u->ip, Database::ALL);

            if($records['countryCode']=="")
            {
                $db = new Database(Yii::getAlias("@app").'/data/ip2location_ipv6.bin', Database::FILE_IO);
                $records = $db->lookup($this->ip, Database::ALL);
                $u->ip_country_id = Helper::getCountryFromCode($records['countryCode']);
            }
            else{
                $u->ip_country_id = Helper::getCountryFromCode($records['countryCode']);
            }
        } catch (Exception $e) {
        }

        $u->save();

        //insert into system log

        SystemLog::log(
            $this->id,
            'Logged in from '.$u->ip." using device ".$u->useragent,
            Constants::LOG_TYPE_USER_LOGIN,
            $this->username
        );

        if($jwt){

            $time = time();
            $jwt = Yii::$app->jwt;
            $signer = $jwt->getSigner('HS256');
            $key = $jwt->getKey();

            /** @var Token $token */
            $token = $jwt->getBuilder()
                ->issuedBy(Url::base(true))
                ->permittedFor(Url::base(true))
                ->identifiedBy(Constants::JWT_IDENTIFIER, true)
                ->issuedAt($time)
                ->withClaim('user_identifier',$this->identifier)
                ->canOnlyBeUsedAfter($time)
                ->expiresAt($time + Constants::SECONDS_JWT_IS_VALID) // Configures the expiration time of the token (exp claim)
                ->getToken($signer,$key);


            $hash = (string)$token;

        }
        else {
            $hash_string = $this->username . ':' . $u->useragent . ':' . $u->ip;
            $hash = hash("sha256", $hash_string);

            //set cookie for re-login
            if(Yii::$app->session->has('rememberMe')
                &&
                Yii::$app->session->get('rememberMe')===true
            ) {
                Yii::$app->response->cookies->add(new Cookie([
                    'name' => 'auth-verification',
                    'value' => Yii::$app->security->encryptByKey(
                        $this->username
                        , Yii::$app->request->cookieValidationKey),
                    'expire' => time() + (86400 * 30) //1 month
                ]));
            }

            Yii::$app->session->set('session_hash',$hash);

        }

        //create session
        $session  = new UserSessions();
        $session->expires = date("Y-m-d H:i:s",strtotime("+20 minutes"));
        $session->hash = $hash;
        $session->user_id = $this->id;

        if(!$session->save()){
            throw new PersistException($session);
        }

        return true;
    }



    public function postRegistration(){

        $code = hash("sha256",Helper::generateRandomKey());

        $this->code = $code;
        $this->update(false,['code']);


        SystemLog::log($this->id,
            'Registration successful ',
            Constants::LOG_TYPE_USER_REGISTER
        );

        SystemLog::log(
            Constants::USER_ADMINISTRATOR,
            'User registered',
            Constants::LOG_TYPE_USER_REGISTER,
            $this->username
        );

    }


    public function forgotPassword(){

        $code = hash("sha256",Helper::generateRandomKey());

        $this->code = $code;
        $this->update(false,['code']);


        SystemLog::log($this->id,
            'Forgot password',
            Constants::LOG_TYPE_USER_FORGOT_PASSWORD
        );

        SystemLog::log(
            Constants::USER_ADMINISTRATOR,
            'User forgot password',
            Constants::LOG_TYPE_USER_FORGOT_PASSWORD,
            $this->username
        );


        return true;
    }


    public function activateAccount(){

        $code = hash("sha256",Helper::generateRandomKey());

        $this->code = $code;
        $this->status_id = Constants::USER_STATUS_ACTIVE;
        $this->update(false,['code','status_id']);


        SystemLog::log($this->id,
            'Account activated successful ',
            Constants::LOG_TYPE_USER_REGISTER
        );

        SystemLog::log(
            Constants::USER_ADMINISTRATOR,
            'User Account activated',
            Constants::LOG_TYPE_USER_REGISTER,
            $this->username
        );


        return true;
    }



    public function verify2FA($code,$temp_secret = false){
        if($code == "")
            return false;

        $secret = ($temp_secret ? $temp_secret : $this->two_fa_secret);

        $manager = new Manager();
        return $manager->verify($code, $secret);
    }

    public function init2FA($overwrite = false){
        $manager = new Manager();
        $secret = $manager->generateSecretKey();

        if($overwrite)
            Yii::$app->session->remove('two_fa_secret');

        if(!Yii::$app->session->has('two_fa_secret'))
            Yii::$app->session->set('two_fa_secret',$secret);

        $totpUri = (new TOTPSecretKeyUriGeneratorService(Yii::$app->name,
            $this->email,
            $secret))->run();

        $uri = (new GoogleQrCodeUrlGeneratorService($totpUri))->run();

        return compact('secret','uri');
    }

    public function getFullName()
    {
        return $this->first_name." ".$this->last_name;
    }


    public function getPublicName(){
        if($this->first_name!=null){
            return $this->getFullName();
        }
        return $this->username;
    }



    /**
     * @return \yii\db\ActiveQuery
     */
    public function getSponsor()
    {
        return self::find()->where(['id'=>$this->referred_by]);
    }

    public function logout(){
        //delete session
        UserSessions::deleteAll(['user_id'=>$this->id]);
        Yii::$app->user->logout();
    }

    public function disable2FA(){
        $this->two_fa_secret = null;
        $this->is_two_fa = Constants::NO_FLAG;
        $this->save();

        SystemLog::log($this->id,
            'Two FA disabled',
            Constants::CMD_DISABLE_TWO_FA,
        );

        return true;
    }

    public function enable2FA($secret = false){

        if($secret === false && !Yii::$app->session->has('two_fa_secret'))
            return false;
        else if($secret === false)
            $secret = Yii::$app->session->get('two_fa_secret');

        $this->two_fa_secret = $secret;
        $this->is_two_fa = Constants::YES_FLAG;
        $this->save();

        Yii::$app->session->remove('two_fa_secret');

        SystemLog::log($this->id,
            'Two FA enabled',
            Constants::CMD_ENABLE_TWO_FA,
        );

        return true;

    }

    public function getSessionHash(){
        if(Yii::$app->user->isGuest)
            return false;

        //get session
        $session = UserSessions::find()
            ->where(['user_id'=>$this->id])
            ->orderBy("id DESC")
            ->one();

        if($session)
            return $session->hash;

        return false;
    }
}
