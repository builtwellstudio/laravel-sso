<?php

namespace Zefy\LaravelSSO\Middleware;

use Closure;
use Illuminate\Http\Request;
use Zefy\LaravelSSO\LaravelSSOBroker;

class SSOAutoLogin
{
    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $broker = new LaravelSSOBroker();
        $response = $broker->getUserInfo();

        // If client is logged out in SSO server but still logged in broker.
        if (!isset($response['data']) && !auth()->guest()) {
            //return $this->logout($request);
        }

        // If there is a problem with data in SSO server, we will re-attach client session.
        if (isset($response['error']) && strpos($response['error'], 'There is no saved session data associated with the broker session id') !== false) {
            return $this->clearSSOCookie($request);
        }

        $username = config('laravel-sso.username');
        // If client is logged in SSO server and didn't logged in broker...
        if (isset($response['data']) && (auth()->guest() || auth()->user()->$username != $response['data'][$username])) {
            // ... we will authenticate our client.
            $user = config('laravel-sso.usersModel')::where($username, $response['data'][$username])->first();
            if(empty($user)){
                return $this->logout($request);
            }
            auth()->loginUsingId($user->id);
        }

        return $next($request);
    }

    /**
     * Clearing SSO cookie so broker will re-attach SSO server session.
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    protected function clearSSOCookie(Request $request)
    {
        return redirect($request->fullUrl())->cookie(cookie('sso_token_' . config('laravel-sso.brokerName')));
    }

    /**
     * Logging out authenticated user.
     * Need to make a page refresh because current page may be accessible only for authenticated users.
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    protected function logout(Request $request)
    {
        auth()->logout();
        return redirect($request->fullUrl());
    }
}
