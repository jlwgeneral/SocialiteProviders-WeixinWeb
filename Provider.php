<?php

namespace SocialiteProviders\WeixinWeb;

use Illuminate\Support\Arr;
use Laravel\Socialite\Two\ProviderInterface;
use SocialiteProviders\Manager\OAuth2\AbstractProvider;
use SocialiteProviders\Manager\OAuth2\User;

class Provider extends AbstractProvider implements ProviderInterface
{
    /**
     * Unique Provider Identifier.
     */
    const IDENTIFIER = 'WEIXINWEB';

    /**
     * @var string
     */
    protected $openId;

    /**
     * {@inheritdoc}.
     */
    protected $scopes = ['snsapi_login'];

    /**
     * set Open Id.
     *
     * @param string $openId
     */
    public function setOpenId($openId)
    {
        $this->openId = $openId;
    }

    /**
     * {@inheritdoc}.
     */
    protected function getAuthUrl($state)
    {
        return $this->buildAuthUrlFromBase($this->getConfig(
            'auth_base_uri',
            'https://open.weixin.qq.com/connect/qrconnect'
        ), $state);
    }

    /**
     * {@inheritdoc}.
     */
    protected function buildAuthUrlFromBase($url, $state)
    {
        $query = http_build_query($this->getCodeFields($state), '', '&', $this->encodingType);

        return $url . '?' . $query . '#wechat_redirect';
    }

    /**
     * {@inheritdoc}.
     */
    protected function getCodeFields($state = null)
    {
        return [
            'appid' => $this->clientId, 'redirect_uri' => $this->redirectUrl,
            'response_type' => 'code',
            'scope' => $this->formatScopes($this->scopes, $this->scopeSeparator),
            'state' => $state,
        ];
    }


    /**
     * 通过token获取用户信息
     * {@inheritdoc}.
     */
    protected function getUserByToken($token)
    {
        $response = $this->getHttpClient()->get('https://api.weixin.qq.com/sns/userinfo', [
            'query' => [
                'access_token' => $token,
                'openid' => $this->openId,
                'lang' => 'zh_CN',
            ],
        ]);

        $userInfo = json_decode($response->getBody(), true);

        if (isset($userInfo['errcode'])) {
            throw new \Exception('getUserByToken获取微信用户信息失败' . json_encode($userInfo));
        }

        return $userInfo;

    }

    /**
     * {@inheritdoc}.
     */
    protected function mapUserToObject(array $user)
    {
        if (!isset($user['openid'], $user['nickname'], $user['headimgurl'])) {
            throw new \Exception('mapUserToObject参数错误' . json_encode($user));
        }

        return (new User())->setRaw($user)->map([
            'id' => Arr::get($user, 'openid'), 'nickname' => $user['nickname'],
            'avatar' => $user['headimgurl'], 'name' => null, 'email' => null,
        ]);
    }

    /**
     * 获取微信token的url
     * {@inheritdoc}
     */
    protected function getTokenUrl()
    {
        return 'https://api.weixin.qq.com/sns/oauth2/access_token';
    }

    /**
     * 通过code获取微信token需要的字段
     * {@inheritdoc}.
     */
    protected function getTokenFields($code)
    {
        return [
            'appid' => $this->clientId, 'secret' => $this->clientSecret,
            'code' => $code, 'grant_type' => 'authorization_code',
        ];
    }

    /**
     * 调用接口获取微信token
     * {@inheritdoc}.
     */
    public function getAccessTokenResponse($code)
    {
        $response = $this->getHttpClient()->get($this->getTokenUrl(), [
            'query' => $this->getTokenFields($code),
        ]);

        $this->credentialsResponseBody = json_decode($response->getBody(), true);
        if (!isset($this->credentialsResponseBody['openid'])) {
            throw new \Exception('getAccessTokenResponse获取微信token失败' . json_encode($this->credentialsResponseBody));
        }
        $this->openId = $this->credentialsResponseBody['openid'];

        return $this->credentialsResponseBody;
    }

    public static function additionalConfigKeys()
    {
        return ['auth_base_uri'];
    }

}
