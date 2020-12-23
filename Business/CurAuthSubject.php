<?php
/**
 * 当前授权登录用户信息类
 * 
 * Created by Trick
 * user: Trick
 * Date: 2020/12/21
 * Time: 10:21 上午
 */

namespace App\Business;

use App\Entity\UserAuth;

class CurAuthSubject
{
    private static $cur_user_auth;
    
    private static $cur_auth_succes_go_url = '';

    /**
     * 设置当前管理员授权信息
     * 
     * @param UserAuth $userAuth
     */
    public static function setCurUserAuth(UserAuth $userAuth)
    {
        self::$cur_user_auth = $user_auth;
    }

    /**
     * 设置当前可跳转路由
     * 
     * @param $cur_auth_sucess_go_url｜
     */
    public static function setCurAuthSuccesGoUrl($cur_auth_sucess_go_url)
    {
        self::$cur_auth_succes_go_url = $cur_auth_sucess_go_url;
    }

    /**
     * 获取当前管理员授权信息
     * 
     * @return mixed
     */
    public static function getCurUserAuth()
    {
        return self::$cur_user_auth;
    }

    /**
     * 获取当前可跳转路由
     * 
     * @return string
     */
    public static function getCurAuthSuccesGoUrl()
    {
        return self::$cur_auth_succes_go_url;
    }
}