<?php

/**
 * This file is part of the HttpClient package.
 *
 * @author  zhanguangcheng<14712905@qq.com>
 * @license http://www.opensource.org/licenses/mit-license.php MIT License
 */

namespace Zane\HttpClient;

/**
 * Class CookieJar
 */
class CookieJar
{
    protected array $cookies = [];

    /**
     * Save cookies from array.
     *
     * @param array $cookies
     * @return void
     */
    public function saveCookies(array $cookies): void
    {
        foreach ($cookies as $cookie) {
            $this->addCookie($cookie);
        }
    }

    /**
     * Add cookie.
     *
     * @param string $cookie
     * @return void
     */
    public function addCookie(string $cookie): void
    {
        if (!in_array($cookie, $this->cookies)) {
            $this->cookies[] = $cookie;
        }
    }

    /**
     * Get all cookies.
     *
     * @return array
     */
    public function getCookies(): array
    {
        return $this->cookies;
    }

}