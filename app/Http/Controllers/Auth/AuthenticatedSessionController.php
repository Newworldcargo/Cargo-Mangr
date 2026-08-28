<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Providers\RouteServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class AuthenticatedSessionController extends Controller
{
    /**
     * Redirect to login if guest, to home if auth.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function index()
    {
        if (Auth::guard('web')->check()) {
            if ($this->alreadyVerifiedByOtp()) {

                // if (auth()->user()->two_factor_secret) {
                //     // Store user ID in session temporarily before full authentication
                //     Session::put('2fa:user:id', auth()->user()->id);

                //     // Log out the user temporarily
                //     Auth::logout();

                //     // Redirect to the 2FA verification page
                //     return redirect()->route('2fa.verify');
                // }
                return redirect(env('PREFIX_ADMIN', 'admin') . RouteServiceProvider::HOME);
            } else {
                return redirect()->route('verify.otp',['refs'=>'true']);
            }
        } else {
            return redirect()->route('home');
        }
    }



    /**
     * Display the login view.
     *
     * @return \Illuminate\View\View
     */
    public function main()
    {
        // The admin subdomain has a single browser sign-in entry point. Customer
        // authentication is handled by the customer portal JSON API, not Blade.
        return redirect()->route('login');
    }

    /**
     * Display the login view.
     *
     * @return \Illuminate\View\View
     */
    public function create()
    {
        $current_version = \app\Models\Settings::where('name','current_version')->first();
        if(!$current_version){
            // Run sql modifications
            $sql_current_version_path = base_path('database/set_current_version.sql');
            if (file_exists($sql_current_version_path)) {
                DB::unprepared(file_get_contents($sql_current_version_path));
            }
            DB::commit();
        }

        $adminTheme = env('ADMIN_THEME', 'adminLte');
        return view($adminTheme.'.auth.login');
    }

    /**
     * Handle an incoming authentication request.
     *
     * @param  \App\Http\Requests\Auth\LoginRequest  $request
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(LoginRequest $request)
    {

        $request->authenticate();

        $request->session()->regenerate();

        //first time verify otp
        if ($this->alreadyVerifiedByOtp()) {

            //place the 2fa check and redirect to 2fa page if user enabled it
            // Check if the user has 2FA enabled
            // if (auth()->user()->two_factor_secret) {
            //     // Store user ID in session temporarily before full authentication
            //     Session::put('2fa:user:id', auth()->user()->id);

            //     // Log out the user temporarily
            //     Auth::logout();

            //     // Redirect to the 2FA verification page
            //     return redirect()->route('2fa.verify');
            // }
            return redirect()->intended(env('PREFIX_ADMIN', 'admin') . RouteServiceProvider::HOME);

        } else {
            return redirect()->route('verify.otp',['refs'=>'true']);
        }

    }

    /**
     * Destroy an authenticated session.
     *
     * @param  \Illuminate\Http\Request  $request
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(Request $request)
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect()->route('home');
    }


    public function alreadyVerifiedByOtp(){
        if (Auth::check() && Auth::user()->verified) {
            return true;
        }else{
            return false;
        }
    }
}
