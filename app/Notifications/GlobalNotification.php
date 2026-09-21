<?php

namespace App\Notifications;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

use \App\Http\Controllers\Api\SendNotification;
use App\Channels\SmsChannel;
use App\Channels\PushChannel;
use App\Channels\WhatsappChannel;

use Exception;
use Twilio\Rest\Client;

use \App\Models\NotificationsSettings;

class GlobalNotification extends Notification
{
    public $data;
    public $gateway;

    public function __construct($data = [], $gateway = [])
    {
        $this->data = $data;
        $this->gateway = $gateway;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param mixed $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        
        if (!$this->gateway){
            return [\App\Models\MessagingSetting::current()->email_enabled ? \App\Channels\QueuedNotificationMailChannel::class : 'mail', 'database'];
        }else{
            $arr = [];
            foreach ($this->gateway as $gateway){
                if ($gateway == 'mail' || $gateway == 'email'){
                    $arr[] = \App\Models\MessagingSetting::current()->email_enabled ? \App\Channels\QueuedNotificationMailChannel::class : 'mail';
                } elseif ($gateway == 'system' || $gateway == 'database'){
                    $arr[] = 'database';
                } elseif ($gateway == 'fcm'){
                    $arr[] = PushChannel::class;
                } elseif ($gateway == 'sms'){
                    $arr[] = SmsChannel::class;
                } elseif ($gateway == 'whatsapp'){
                    $arr[] = WhatsappChannel::class;
                } else {
                    // before type any gateway, check the "to" method if exist or NOT firstly
                    $arr[] = $gateway;
                }
            }
            return $arr;
        }
    }

    public function toMail($notifiable)
    {
        $params = $this->data;
        $url = $params['message']['url'];
        return (new MailMessage)
            ->subject($params['message']['subject'])
            ->action('View', $url)
            ->line($params['message']['content']);
    }

    public function toSms($notifiable)
    {
        $params = $this->data;
        if (\App\Models\MessagingSetting::current()->sms_enabled) {
            $purpose = (int) $notifiable->role === 4 ? 'customer_notifications' : 'staff_notifications';
            $phone = (string) ($params['phone'] ?? '');
            if (!\App\Services\Messaging\Outbox::phone($phone)) $phone = ($params['country_code'] ?? '') . $phone;
            return app(\App\Services\Messaging\Outbox::class)->enqueue('sms', $purpose, $phone,
                ['body' => $params['message']['subject'] ?? 'New World Cargo update'],
                'notification:' . ($this->id ?: Str::uuid()) . ':' . $notifiable->id);
        }
      
       	$NotificationSettings = resolve(NotificationsSettings::class)->toArray();
      	$notifications = json_decode($NotificationSettings['sms'], true);

      if($notifications['sms_provider'] == 'twilio' ) {
            
       		// return sendSMS($notifiable->phone, $params['message']['subject']);
       		$account_sid = $notifications['twilio_sid'];
            $auth_token = $notifications['twilio_auth_token'];
            $twilio_number = $notifications['twilio_sender_number'];
  
            $client = new Client($account_sid, $auth_token);
      
            $client->messages->create($params['country_code'] .$params['phone'], [
                'from' => $twilio_number, 
                'body' => $params['message']['subject']]);
          
        } else if($notifications['sms_provider'] == 'nexmo' ) {
        
        	$nexmo_key = $notifications['nexmo_key'];
            $nexmo_secret = $notifications['nexmo_secret'];
            $nexmo_sender_number = $notifications['nexmo_sender_number'];
        
        	$basic  = new \Nexmo\Client\Credentials\Basic($nexmo_key, $nexmo_secret);
            $client = new \Nexmo\Client($basic);
  
            $receiverNumber = $params['country_code'] .$params['phone'];
            $message = $params['message']['subject'];
  
            $message = $client->message()->send([
                'to' => $receiverNumber,
                'from' => $nexmo_sender_number,
                'text' => $message
            ]);
        	
      } else if($notifications['sms_provider'] == 'ssl' ) {
        
        $ssl_api_token = $notifications['ssl_api_token'];
        $ssl_sid = $notifications['ssl_sid'];
        $ssl_url = $notifications['ssl_url'];
            
            // username, password, sid provided by sslwireless
            $SslWirelessSms = new SslWirelessSms($ssl_sid, $ssl_api_token);
            // You can change the api url if needed. i.e.
            $SslWirelessSms->setUrl($ssl_url);
            $output = $SslWirelessSms->send($receiverNumber, $message);
        
        } else if ($notifications['sms_provider'] == 'fast2sms') {
        	$fast2sms_auth_key = $notifications['fast2sms_auth_key'];
            $fast2sms_route = $notifications['fast2sms_route'];
            $fast2sms_language = $notifications['fast2sms_language'];	
            $fast2sms_sender_id = $notifications['fast2sms_sender_id'];	
        
        	$request ="";
            $param['authorization']=$fast2sms_auth_key;
            $param['sender_id']=$fast2sms_sender_id;
            $param['message']=$message;
            $param['numbers']=$receiverNumber;
            $param['language']=$fast2sms_language;
            $param['route']=$fast2sms_route;

            foreach($param as $key => $val){
                $request.=$key."=".urlencode($val);
                $request.="&";
            }
            $request =substr($request,0,strlen($request)-1);

            $url ="https://www.fast2sms.com/dev/bulkV2?".$request;
            $ch =curl_init($url);
            curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
            $curl_scaped_page=curl_exec($ch);
            curl_close($ch);
      } 
    }

    public function toWhatsapp($notifiable)
    {
        $params = $this->data;
        $NotificationSettings = resolve(NotificationsSettings::class)->toArray();
        $notifications = json_decode($NotificationSettings['whatsapp'], true);

        // return sendSMS($notifiable->phone, $params['message']['subject']);
        $account_sid = $notifications['twilio_sid'];
        $auth_token = $notifications['twilio_auth_token'];
        $twilio_number = $notifications['twilio_sender_number'];

        $client = new Client($account_sid, $auth_token);


        $client->messages->create('whatsapp:' .$params['country_code'] .$params['phone'], [
            'from' => 'whatsapp:' . $twilio_number,
            'body' => $params['message']['subject']]);
    }

    /**
     * Get the array representation of the notification.
     *
     * @param mixed $notifiable
     * @return array
     */
    public function toDatabase($notifiable)
    {
        return $this->data;
    }

    public function toPush($notifiable)
    {
        $params = $this->data;
        $notification = new SendNotification();
      	
        $push = $notification
            ->withKey(env('FCM_SERVER_KEY'))
            ->withTitleAndBody($params['message']['subject'],$params['message']['content'])
            ->withData($params['message'])
            ->to($params['to'])
            ->send();
      	
        return $this;
    }
}
