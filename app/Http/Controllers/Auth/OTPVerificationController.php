<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\OTPMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class OTPVerificationController extends Controller
{
    /**
     * Show OTP verification page
     */
    public function index()
    {
        try {
            if (Auth::check() && Auth::user()->verified) {
                return redirect()->route('admin.dashboard')->with('success', 'Your account is already verified.');
            }

            // Auto-send OTP when showing verification page
            if (isset($_GET['refs'])) {
                $this->resendOtp();
            }

            $adminTheme = env('ADMIN_THEME', 'adminLte');
            return view($adminTheme . '.auth.otp-verification');
        } catch (\Throwable $th) {
            dd($th);
        }
    }

    /**
     * Verify OTP
     */
    public function verifyOtp(Request $request)
    {
        try {

            // Compile OTP from individual fields if needed
            if ($request->has('complete_otp') && $request->complete_otp) {
                $request->merge(['otp' => $request->complete_otp]);
            } else if ($request->has('otp_digit1')) {
                // Compile from individual digits
                $otp = '';
                for ($i = 1; $i <= 6; $i++) {
                    $otp .= $request->get('otp_digit' . $i, '');
                }
                $request->merge(['otp' => $otp]);
            }

            $request->validate([
                'otp' => 'required|numeric|digits:6',
            ]);

            $user = Auth::user();

            if (!$user) {
                return redirect()->route('home')->with('error', 'Session expired. Please login again.');
            }

            // Check if OTP matches
            if ($user->otp == $request->otp) {
                $user->verified = true;
                $user->otp = null; // Clear OTP after successful verification
                $user->otp_expires_at = null; // Clear expiration timestamp
                $user->save();

                // Log successful verification
                Log::info('User verified account successfully', ['user_id' => $user->id, 'email' => $user->email]);

                return redirect()->route('admin.dashboard')->with('success', 'Your account has been verified successfully!');
            }

            // Log failed attempt
            Log::warning('Failed OTP verification attempt', ['user_id' => $user->id, 'email' => $user->email]);

            return back()->with('error', 'The code you entered is incorrect. Please try again a new code has been resent.');
        } catch (\Throwable $th) {
            return back()->with('error', $th->getMessage());
        }
    }

    /**
     * Resend OTP
     */
    public function resendOtp()
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return redirect()->route('signin')->with('error', 'Session expired. Please login again.');
            }

            // Generate new OTP
            $otp = rand(100000, 999999);
            $user->otp = $otp;
            $user->otp_expires_at = now()->addMinutes(10); // OTP valid for 10 minutes
            $user->save();

            // Here, send OTP via email or SMS
            $messaging = \App\Models\MessagingSetting::current();
            if ($messaging->sms_enabled || $messaging->email_enabled) {
                app(\Modules\CustomerPortalApi\Services\Portal\PortalOtpNotifier::class)->sendVerification($user);
            } else {
                Mail::to($user->email)->send(new OTPMail($user->otp, $user->name));
            }

            // Never write the one-time code itself to application logs.
            Log::info('OTP resent to user', [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);

            if (request()->ajax()) {
                return response()->json(['success' => true, 'message' => 'A new verification code has been sent to your email.']);
            }

            return back()->with('success', 'A new verification code has been sent to ' . $user->email);
        } catch (\Throwable $th) {
            return back()->with('error', $th->getMessage());
        }
    }

    /**
     * Middleware to enforce OTP verification
     */
    public function verifyByOtp()
    {
        if (Auth::check() && Auth::user()->verified) {
            return true;
        }

        return redirect()->route('verify.otp')->with('info', 'Please verify your account to continue.');
    }
}
