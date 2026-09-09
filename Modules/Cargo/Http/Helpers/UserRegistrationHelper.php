<?php

namespace Modules\Cargo\Http\Helpers;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserRegistrationHelper{
	private $user;
	public function __construct($id = null) {

		if($id == null)
		{
			$this->user = new User();
		}else{
			$this->user = User::find($id);

		}
	}

	public function NewUser($data ,$roles = null, $permissions = null){
        $user = $this->user;

        if (!empty($data['password'])) {
            $data['password'] = bcrypt($data['password']);
        } else {
            // Keep an existing account's password when an edit form leaves the
            // optional password field blank.
            unset($data['password']);
        }
        $token = Str::random(60);
        $data['remember_token'] = hash('sha256', $token);
        $user->fill($data);

        $response = array();
		$response['success'] = true;
		$response['error_msg'] = '';
		try{
			if(!$user->save()){
				throw new \Exception();
			}
			$user->remember_token = hash('sha256', $token);
			if(!$user->save()){
				throw new \Exception();
			}
			$response['user'] = $user;


            if(isset($data['image']) && $data['image'] != null){
                if($user->role == 0  ){
                    $user->syncFromMediaLibraryRequest($data['image'])->toMediaCollection('avatar');
                }
            }

		}catch(\Exception $e){
			$response['success'] = false;
			$response['error_msg'] = "UserRegestirationHelper: ".$e->getMessage();
		}

		return $response;
	}

	public function setApiTokenGenerator(){
		$token = Str::random(60);
		$this->user->remember_token = hash('sha256', $token);
		if($this->user->save())
		{
			return $this->user->remember_token;
		}
	}

}
