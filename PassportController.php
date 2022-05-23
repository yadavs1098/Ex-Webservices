<?php
namespace App\Http\Controllers\API;
use Illuminate\Http\Request;
use App\Http\Controllers\API\ApiBaseController as ApiBaseController;
use DB;
use Intervention\Image\ImageManagerStatic as ImageResize;
use App\User;
use App\RegistrationPayment;
use App\EventType;
use Illuminate\Support\Facades\Auth;
use Validator;
use Hash;
use App\Nationality;
use App\Order;
use App\Category;
use App\Msg;
use App\CommentHistory;
use App\UserAttribute;
use App\Country;
use App\Campaign;
use App\Product;
use App\Image;
use App\HouseRule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Log;
use App\CampaignInfluncer;
use App\ContentReviewProcess;
use App\ProductInfluencerDocument;
use App\ProductNotification;
use App\BrandAlert;
use App\Setting;
use Mail;
use Dirape\Token\Token;
use URL;
use App\Brand;
use App\City;
use App\UserPaymentDetail;
use App\HashtagInsta;
use App\HashtagWeibo;
use App\AdminPayment;
use App\PostContentUrl;
use App\Http\Controllers\Web\HomeController as homeController;


class PassportController extends ApiBaseController {

    /**
     * Register api 
     *
     * @return \Illuminate\Http\Response **/


	// Update Influencers profile Image
    public function uploadProfileImage(Request $request){
        try{
            $validator = Validator::make($request->all(), [            
                'profile_image' => 'required'
            ]);
            if($validator->fails()){
                Log::channel('mobileapi')->info('validation error occured for upload-profile-image api.');
                Log::channel('mobileapi')->info($validator->errors());
                return $this->sendError('Validation Error', $validator->errors());       
            }
			$user_id = Auth::user()->id;
        if($request->hasFile('profile_image')){
          $validator = \Validator::make($request->all(), [
                'profile_image' => 'image|mimes:jpeg,png,jpg|max:500000',
            ]);
            if ($validator->fails()){
                return $this->sendError('Validation Error', $validator->errors());
            }
			$image = $request->file('profile_image');
			$fileNameToStore    = $user_id.'-'.rand().time() . '.' . $image->getClientOriginalExtension();
			$fileNameToStoreResized   = 'resized-'.$fileNameToStore;
			$fileNameProfileToStoreResized   = 'profile-resized-'.$fileNameToStore;
			$filePath = base_path(config('constants.INFLUENCER_IMAGE_FOLDER_PATH'));
			request()->profile_image->move($filePath, $fileNameToStore);

			$image_resize = ImageResize::make($filePath.$fileNameToStore);
			$image_resize->fit(150, 150, function ($constraint) {
				$constraint->upsize();
			});
			$image_resize->orientate();
			$filePath = base_path(config('constants.INFLUENCER_IMAGE_FOLDER_PATH'));
			$image_resize->save($filePath.$fileNameToStoreResized,100);
			
			$image_resize = ImageResize::make($filePath.$fileNameToStore);
			$image_resize->fit(210, 210, function ($constraint) {
				$constraint->upsize();
			});
			$image_resize->orientate();
			$filePath = base_path(config('constants.INFLUENCER_IMAGE_FOLDER_PATH'));
			$image_resize->save($filePath.$fileNameProfileToStoreResized,100);
		}else{
			Log::channel('mobileapi')->info('profile image not uploaded for upload-profile-image api');     
			return $this->sendError('Please select file as jpg,jpeg or png',null); 
		}
		$user = User::find($user_id);
		$user->profile_image = $fileNameToStore;
		$user->save();
		if($user){
                $result = [];
                $result['profile_image'] =  asset(config('constants.INFLUENCER_IMAGE_FOLDER_PATH') . $fileNameToStore);
                return $this->sendResponse($result, 'Influencer image uploaded successfully.');
            }else{
                return $this->sendError('Influencer image not uploaded',null); 
            }
        }catch (\Laracasts\Validation\FormValidationException $e) {
                Log::channel('mobileapi')->info('Got error upload influencer image.');
                Log::channel('mobileapi')->info($e);
            return $this->sendError('Something went wrong.',null); 
        }   
    }
	
	 public function register(Request $request){
        $validator = Validator::make($request->all(), [
            'firstname' => 'required',
            'email' => 'required|email',
            'password' => 'required',
            'device_token' => 'required',
        ]);
        if($validator->fails()){
            Log::channel('mobileapi')->info('Register api with local db');
            Log::channel('mobileapi')->info($request->all());
            Log::channel('mobileapi')->info('validation error send back to mobile app');
            return $this->sendError('Validation Error', $validator->errors()); 
        }

        $input = $request->all();
        $input['password'] = Hash::make($input['password']);
       $checkUserExist = User::where('email', $input['email'])->where('role',2)->count();
        if($checkUserExist==1){
            Log::channel('mobileapi')->info('User already registered with us');
            return $this->sendError('User already registered with us',null); 
        }
		
        try{ 
            $user = new User;
            $user->firstname =  $input['firstname'];
            $user->lastname = (isset($input['lastname']) && $input['lastname']!='') ? $input['lastname'] : '';
            $user->email =  $input['email'];
            $user->password =  $input['password'];

            if(isset($input['gender'])){
                $user->gender =  $input['gender'];
            }
			
			if(isset($input['address'])){
				$user->address =  $input['address'];
			}
			
			if(isset($input['city'])){
                $user->city  =  $input['city'];
            }
			
			if(isset($input['biodata'])){
                $user->biodata  =  $input['biodata'];
                $user->bio  =  $input['biodata'];
            }
			
			if(isset($input['state'])){
                $user->state =  $input['state'];
            }
			
			if(isset($input['postalcode'])){
                $user->postalcode  =  $input['postalcode'];
            }
			
			if(isset($input['mobile'])){
                $user->contact =  $input['mobile'];
            }
			
			if(isset($input['dob'])){
                $user->dob =  date('Y-m-d',strtotime($input['dob'])); 
            }
			
			if(isset($input['nationality'])){
                $user->nationality_id =  $input['nationality'];
            }
			
			if(isset($input['weixin'])){
                $user->weixin =  $input['weixin'];
            }
            $user->country_id =  $input['country'];
            $user->status =  2;
            $user->role =  2;
            $user->device_token = $input['device_token'];
            $user->inf_type = $input['inf_type'];
            $user->created_at 	= date('Y-m-d h:i:s');
			
			if(isset($request->profile_pic)){
				$data = $request->profile_pic;
				$filePath = base_path(config('constants.INFLUENCER_IMAGE_FOLDER_PATH'));
				$image=$this->saveBase64ToImage($data,$filePath);
				
				$filePath = base_path(config('constants.INFLUENCER_IMAGE_FOLDER_PATH'));
				$fileNameToStoreResized   = 'resized-'.$image;
				$fileNameProfileToStoreResized   = 'profile-resized-'.$image;
				
				$image_resize = ImageResize::make($filePath.$image);
				$image_resize->fit(150, 150, function ($constraint) {
					$constraint->upsize();
				});
				$image_resize->orientate();
				$filePath = base_path(config('constants.INFLUENCER_IMAGE_FOLDER_PATH'));
				$image_resize->save($filePath.$fileNameToStoreResized,100);
				
				$image_resize = ImageResize::make($filePath.$image);
				$image_resize->fit(210, 210, function ($constraint) {
					$constraint->upsize();
				});
				$image_resize->orientate();
				$filePath = base_path(config('constants.INFLUENCER_IMAGE_FOLDER_PATH'));
				$image_resize->save($filePath.$fileNameProfileToStoreResized,100);
				$user->profile_image = $image;
			}
			
            $user->save();
			
            /* push notification */
			$device_token =  $input['device_token'];
            $title = 'Gaibo Influencers';
            $msg = 'Your account has been created.';
            $page = 'register';
            $notification_data = $user->id;
            $this->pushNotification($device_token,$title,$msg,$page,$notification_data);
            /* push notification */
			
            //Mail Funtinality Will Be Implmented Here  
            $setting = Setting::first();
            $siteEmail =      $setting->robot_email;
			$recipientName  = $input['firstname'];
			$recipient      = $input['email'];
			$officeaddress  = $setting->office_address;
			$supportemail    = $setting->support_email;
			$adminemail    = $setting->admin_email;

            $data = array('name'=>$input['firstname'],'email'=>$input['email'],'title'=>'Influencer Registration','officeaddress'=>$officeaddress,'supportemail'=>$supportemail);

            Mail::send('emails.influencers.registration', $data, function($message) use($recipient, $siteEmail, $recipientName){
				$message->to($recipient, $recipientName)->subject('Your Gaibo Influencers application is under review.');
				$message->from($siteEmail,'Gaibo Influencers'); 
			});
			$recipientNamezz='Admin';
			
			$datazz = array('title'=>'You have a new influencer that has registered on the platform. Please log in and review their information.');
			Mail::send('emails.commonmail', $datazz, function($message) use($adminemail, $siteEmail,$recipientNamezz){
				$message->to($adminemail,$recipientNamezz)->subject('A new influencer has registered on Gaibo Influencers.');
				$message->from($siteEmail,'Gaibo Influencers'); 
			});
			
			
            /*******************************************************/
            // Code for notification. Admin will get this notification
            $adminNotification = new homeController;
            $notificationType = 2; // 1 = alert , 2 = notification
            $notificationText = 'New Influencer has been registered with us. email - '.$input['email'];
            // function to save notification data

            $adminNotification->addNotification($notificationType,$notificationText);
            /*******************************************************/
			
            $success['user_id'] =  $user->id;
            $success['token'] =  $user->createToken($input['email'])->accessToken;
			$success['category'] =  Category::select('id','name')->whereRaw('FIND_IN_SET(0,type)')->get();
        } catch (\Laracasts\Validation\FormValidationException $e) {
            Log::channel('mobileapi')->info('Got error while register with local db.');
            Log::channel('mobileapi')->info($e);
            return $this->sendError('Server not responding.',null); 
        } 
        Log::channel('mobileapi')->info('information send back to mobile app');
        Log::channel('mobileapi')->info($success);     
        return $this->sendResponse($success, 'User registered successfully');	
    }
	
	public function saveBase64ToImage($image,$path) {
        $base = $image;
        $binary = base64_decode($base);
        header('Content-Type: bitmap; charset=utf-8');

        $f = finfo_open();
        $mime_type = finfo_buffer($f, $binary, FILEINFO_MIME_TYPE);
        $mime_type = str_ireplace('image/', '', $mime_type);

        $filename = md5(\Carbon\Carbon::now()) . '.' . $mime_type;
        $file = fopen($path . $filename, 'wb');
        if (fwrite($file, $binary)) {
            return $filename;
        } else {
            return FALSE;
        }
        fclose($file);
    }
	
	// Influencer Login
	public function login(Request $request){
        $validator = Validator::make($request->all(), [ 
            'email' => 'required',
            'password' => 'required',
        ]);
        if($validator->fails()){
            Log::channel('mobileapi')->info('Validation error');
            Log::channel('mobileapi')->info($request->all());
            Log::channel('mobileapi')->info($validator->errors()); 
            return $this->sendError('Validation Error', $validator->errors()); 
        }

        $credentials = [
            'email' => $request->email,
            'password' => $request->password
        ];
		
        if (auth()->attempt($credentials)) {
            $user_id =  auth()->user()->id;
            $user_status = User::where('id',$user_id)->pluck('status');
            if($user_status[0]=='2' || $user_status[0]=='3' || $user_status[0]=='4'){
                Log::channel('mobileapi')->info('Please contact to administrator.');
                return $this->sendError('Access Denied. Please contact to Administrator.', ['error' => 'AccessDenied'],200);
            }else{
				$scl_detil_obj=UserSocialDetails::where('user_id',$user_id)->get();
				if($scl_detil_obj){
					$scl_detil = $scl_detil_obj->toArray();
				}
				if(!empty($scl_detil)){
					$success['sociamediapaltform'] =  1;
				}else{
					$success['sociamediapaltform'] =  0;
				}
                $success['token'] =  auth()->user()->createToken($credentials['email'])->accessToken;
                $success['id'] =  $user_id;
				$success['category'] =  Category::select('id','name')->whereRaw('FIND_IN_SET(0,type)')->get();
				return $this->sendResponse($success, 'User has loggedIn successfully.');
            }
        } else {
            Log::channel('mobileapi')->info('Authentication failed. Invalid Credentials');
            return $this->sendError('Invalid Credentials', ['error' => 'UnAuthorised'],200);
        }
    }
	
	// Update inflcuner profile password
	public function changepassword(Request $request){
        try {
           $validator = Validator::make($request->all(), [            
                'password' => 'required|confirmed',
                'password_confirmation' => 'required'
            ]);

            if($validator->fails()){
                Log::channel('mobileapi')->info('validation error for change password api.');
                Log::channel('mobileapi')->info($request->all());
                Log::channel('mobileapi')->info($validator->errors());
                return $this->sendError('Validation Error', $validator->errors());       
            }
            $password = Hash::make($request->password);
            $update = User::find(Auth::user()->id)->update(['password' => $password]);
			
            if($update){
                /* push notification */
                $title = 'Gaibo Influencers';
                $msg = 'Password update successfully.';
                $device_token = auth()->user()->device_token;
                $page = 'change password';
                $notification_data = Auth::user()->id;
                $this->pushNotification($device_token,$title,$msg,$page,$notification_data);
                /* push notification */
               return $this->sendResponse(array(), 'Password update successfully.'); 
            }else{
              return $this->sendError('Password not update',null);
            }
        }catch (\Laracasts\Validation\FormValidationException $e) {
                Log::channel('mobileapi')->info('Got error when ifluencer change password.');
                Log::channel('mobileapi')->info($e);
                dd($e);
            return $this->sendError('Something went wrong.',null); 
        }
    }
	
	
} 
?>
