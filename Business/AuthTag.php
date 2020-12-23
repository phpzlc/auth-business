<?php
/**
 * AuthTag类
 * 
 * Created by Trick
 * user: Trick
 * Date: 2020/12/21
 * Time: 3:30 下午
 */

namespace App\Business;

use App\Business\PlatformBusiness\PlatformBusiness;
use App\Business\PlatformBusiness\PlatformClass;
use Psr\Container\ContainerInterface;

class AuthTag
{
    /**
     * 设置Session标记
     * 
     * @param ContainerInterface $container
     * @param $user_auth_id
     * @return string
     * @throws \Exception
     */
    public static function set(ContainerInterface $container, $user_auth_id)
    {
        $tag = '';

        switch (PlatformClass::getPlatform()){
            case $container->get('parameter_bag')->get('platform_admin'):
                $container->get('session')->set(PlatformClass::getPlatform() . $container->get('parameter_bag')->get('login_tag_session_name'), $user_auth_id);
                break;

            default:
                throw new \Exception('来源溢出');
        }

        return $tag;
    }

    /**
     * 获取Session标记内容
     * 
     * @param ContainerInterface $container
     * @return mixed
     * @throws \Exception
     */
    public static function get(ContainerInterface $container)
    {
        switch (PlatformClass::getPlatform()){
            case $container->get('parameter_bag')->get('platform_admin'):
                $user_auth_id = $container->get('session')->get(PlatformClass::getPlatform() . $container->get('parameter_bag')->get('login_tag_session_name'));
                break;

            default:
                throw new \Exception('来源溢出');
        }

        return $user_auth_id;
    }

    /**
     * 移除Session标记
     * 
     * @param ContainerInterface $container
     * @throws \Exception
     */
    public static function remove(ContainerInterface $container)
    {
        switch (PlatformClass::getPlatform()){
            case $container->get('parameter_bag')->get('platform_admin'):
                $container->get('session')->remove(PlatformClass::getPlatform() . $container->get('parameter_bag')->get('login_tag_session_name'));
                break;

            default:
                throw new \Exception('来源溢出');
        }
    }

}