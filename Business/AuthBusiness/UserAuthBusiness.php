<?php
/**
 * 登录核心模块
 * 
 * Created by Trick
 * user: Trick
 * Date: 2020/12/21
 * Time: 4:05 下午
 */

namespace App\Business\AuthBusiness;

use App\Entity\UserAuth;
use App\Repository\UserAuthRepository;
use PHPZlc\PHPZlc\Abnormal\Errors;
use PHPZlc\PHPZlc\Bundle\Business\AbstractBusiness;
use PHPZlc\Validate\Validate;
use Psr\Container\ContainerInterface;

class UserAuthBusiness extends AbstractBusiness
{
    /**
     * 授权登录表Repository
     *
     * @var UserAuthRepository
     */
    public $userAuthRepository;

    /**
     * 多类型用户表业务
     *
     * @var SubjectAuthInterface
     */
    private $subjectAuthCaches = [];
    
    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);
        
        $this->userAuthRepository = $this->getDoctrine()->getRepository(UserAuth::class);
    }

    /**
     * 新建用户授权
     * 
     * @param UserAuth $userAuth
     * @param bool $is_flush
     * @return bool
     * @throws \Exception
     */
    public function create(UserAuth $userAuth, $is_flush = true)
    {
        $userAuth->setCreateAt(new \DateTime());
        
        if(!$this->validator($userAuth)){
            return false;
        }

        try {
            $this->em->persist($userAuth);
            
            if($is_flush){
                $this->em->flush();
                $this->em->clear();
            }
            
            return true;
            
        }catch (\Exception $exception){
            $this->networkError($exception);
            return false;
        }
    }

    /**
     * 获取指定平台端方法
     *
     * @param $subject_type
     * @return SubjectAuthInterface
     * @throws \Exception
     */
    private function getUserAuthService($subject_type)
    {
        if(!array_key_exists($subject_type, $this->subjectAuthCaches)){
            switch ($subject_type){
                default:
                    throw new \Exception('授权登录权限不存在');
            }
        }
        
        return $this->subjectAuthCaches[$subject_type];
    }

    /**
     * 账号登录
     *
     * @param $account
     * @param $password
     * @param $subject_type
     * @param string $account_field
     * @param string $userAuthFunctionName
     * @param string $account_title
     * @return false|void
     * @throws \Exception
     */
    public function accountLogin($account, $password, $subject_type, $account_field = 'account', $account_title = '账号', $userAuthFunctionName = 'getUserAuth')
    {
        $userAuth = $this->accountCheck($account, $password, $subject_type, $account_field, $account_title, $userAuthFunctionName);

        if($userAuth === false){
            return false;
        }

        return $this->login($userAuth);
    }

    /**
     * 账号校验
     *
     * @param $account
     * @param $password
     * @param $subject_type
     * @param string $account_field
     * @param string $account_title
     * @param string $userAuthFunctionName
     * @return false|UserAuth
     * @throws \Exception
     */
    public function accountCheck($account, $password, $subject_type, $account_field = 'account', $account_title = '账号', $userAuthFunctionName = 'getUserAuth')
    {
        if(empty($account)){
            Errors::setErrorMessage($account_title . '不能为空');
            return false;
        }

        if(empty($password)){
            Errors::setErrorMessage('密码不能为空');
            return false;
        }

        $user = $this->getUserAuthService($subject_type)->user([$account_field => $account]);

        if(empty($user)){
            Errors::setErrorMessage($account_title . '不存在');
            return false;
        }

        $userAuth = $user->$userAuthFunctionName();

        if($userAuth->getPassword() != $this->encryptPassword($password, $userAuth->getSalt())){
            Errors::setErrorMessage('密码错误');
            return false;
        }

        return $userAuth;
    }

    /**
     * 登录
     *
     * @param UserAuth $userAuth
     * @return false|string
     * @throws \Exception
     */
    public function login(UserAuth $userAuth)
    {
        if(empty($userAuth)){
            Errors::setErrorMessage('账号不存在');
            return false;
        }

        if(empty($userAuth->getId())){
            Errors::setErrorMessage('账号不存在');
            return false;
        }

        $user = $this->checkStatus(['id' => $userAuth->getSubjectId()], $userAuth->getSubjectType());

        if($user === false){
            Errors::getError();
            return false;
        }

        $userAuth->setLastLoginAt(new \DateTime());
        $userAuth->setLastLoginIp($this->get('request_stack')->getCurrentRequest()->getClientIp());

        $this->em->flush();

        CurAuthSubject::setCurUserAuth($userAuth);
        CurAuthSubject::setCurUser($user);

        return AuthTag::set($this->container, $userAuth);

    }

    /**
     * 检查当前登录账号的状态
     *
     * @param $rules
     * @param $subject_type
     * @return false|UserInterface[]
     * @throws \Exception
     */
    public function checkStatus($rules, $subject_type)
    {
        $user = $this->getUserAuthService($subject_type)->user($rules);

        if(empty($user)){
            Errors::setErrorMessage('账号不存在');
            return false;
        }

        if(!$this->getUserAuthService($subject_type)->checkStatus($user)){
            return false;
        }

        return $user;
    }

    /**
     * 使用旧密码修改密码
     *
     * @param UserAuth $userAuth
     * @param $old_password
     * @param $new_password
     * @return bool
     * @throws \Exception
     */
    public function changePassword(UserAuth $userAuth, $old_password, $new_password)
    {
        if(empty($userAuth)){
            Errors::setErrorMessage('账号不存在');
            return false;
        }
        
        if(empty($old_password)){
            Errors::setErrorMessage('原始密码不能为空');
            return false;
        }
        
        if(empty($new_password)){
            Errors::setErrorMessage('新密码不能为空');
            return false;
        }
        
        if($userAuth->getPassword() != $this->encryptPassword($old_password, $userAuth->getSalt())){
            Errors::setErrorMessage('原始密码不正确');
            return false;
        }

        return $this->updatePassword($userAuth, $new_password);
    }


    /**
     * 找回密码
     *
     * @param UserAuth $userAuth
     * @param string $new_password
     * @param string $again_password 再次输入确认密码
     * @return bool
     */
    public function retrievePassword(UserAuth $userAuth, $new_password, $again_password)
    {
        if(empty($userAuth)){
            Errors::setErrorMessage('账号不存在');
            return false;
        }

        if(empty($new_password)){
            Errors::setErrorMessage('密码不能为空');
            return false;
        }

        if($new_password != $again_password){
            Errors::setErrorMessage('两次密码输入不一致');
            return false;
        }

        return $this->updatePassword($userAuth, $new_password);
    }

    /**
     * 修改密码
     *
     * @param UserAuth $userAuth
     * @param $new_password
     * @return bool
     */
    public function updatePassword(UserAuth $userAuth, $new_password)
    {
        $userAuth->setPassword($new_password);

        if(Errors::isExistError()){
            return false;
        }

        try {
            $this->em->flush();
            $this->em->clear();

            return true;
        }catch (\Exception $exception){
            $this->networkError($exception);
            return false;
        }
    }

    /**
     * 检查登录状态
     *
     * @return UserAuth|false|object
     * @throws \Exception
     */
    public function isLogin()
    {
        $userAuth = AuthTag::get($this->container);

        if(empty($userAuth)){
            Errors::setErrorMessage('登录超时');
            return false;
        }

        $result = $this->checkStatus(['id' => $userAuth->getSubjectId()], $userAuth->getSubjectType());
        if($result === false){
            return false;
        }

        $user = $this->getUserAuthService($userAuth->getSubjectType())->user(['id' => $userAuth->getSubjectId()]);
        
        $this->em->refresh($userAuth);
        $this->em->refresh($user);
        CurAuthSubject::setCurUserAuth($userAuth);
        CurAuthSubject::setCurUser($user);

        return $userAuth;
    }



    /**
     * 密码加密
     * 
     * @param $password
     * @param string $salt
     * @return string
     */
    public static function encryptPassword($password, $salt = '')
    {
        return sha1(md5($password) . $salt);
    }

    /**
     * 生成盐值
     * 
     * @param int $length
     * @param false $has_letter
     * @return false|string
     */
    public function generateSalt($length = 6, $has_letter = false)
    {
        $salt = '';
        if($has_letter){
            $intermediateSalt = md5(uniqid(rand(), true));
            $salt = substr($intermediateSalt, 0, $length);
        }else{
            for ($i = 0; $i < $length; $i++){
                $salt .= mt_rand(0, 9);
            }
        }
        
        return $salt;
    }
}
