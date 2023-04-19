<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Need;
use App\Notifications\RequestAcceptedNotification;
use App\Notifications\RequestDeclinedNotification;
use App\Notifications\RequsetNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use App\Models\UserActivitylog;
use App\Models\Types;
use Carbon\Carbon;

class RequestController extends Controller
{
    public function index(){
        $requests = $this->getallrequest()->original;
        if (isset($requests['Requests'])) {
            return response()->json($requests['Requests']); 
        } else {
            return response()->json(['message' => 'No requests found']);
        }
    }
    public function store(Request $request){
        $rules = [
            'types' => 'required',
            'model' => 'required',
            'requestreason' => 'required',
        ];
        $request->validate($rules);
        $user = auth()->user();

        $send= new Need();
        $oldAttributes = $send->getAttributes();

        $send->types = $request['types'];
        $send->model = $request['model'];
        $send->requestreason = $request['requestreason'];
        $send->user_id = $user->id;
        $send->status = '0';
        $send->user_id = $user->id;
        $send->save();

        $newAttributes = $send->getAttributes(); // Get the updated attributes
        $changes = array_diff_assoc($newAttributes, $oldAttributes); // Get the attributes that were changed

        $log = new UserActivitylog();
        $log->email = auth()->user()->email; // assuming the currently logged in user is the one making the modification
        $log->modifyuser = 'Created Request: ' . json_encode($changes); ; // modify this to reflect the specific action being logged
        $log->date_time = Carbon::now()->utc();
        $log->created_at = Carbon::now()->utc();
        $log->save();   
    
        $admin =Customer::where('role', '1')->first();
        Notification::send($admin, new RequsetNotification($send));
        return response()->json([
            'status' => true,
            'message' => 'Request Send successfully',
            'supplier' => $send,
        ], 200);
    }
    public function action(){
        $admin = Customer::where('role', '1')->first();
        $notifications = $admin->notifications()->where('type', 'App\Notifications\RequsetNotification')->get();
        return response()->json([
            'notifications' => $notifications,
        ], 200);
    }

    public function update(Request $request, $id) {
        $need = Need::findOrFail($id);
$oldAttributes = $need->getAttributes();

        if ($need->status != '0') {
            return response()->json([
                'status' => false,
                'message' => 'This request has already been accepted or rejected'
            ], 400);
        }

        if ($request->has('status')) {
            $status = strtolower($request->input('status'));
    
            if ($status == '1') {
                $need->status = '1';
                // Send notification for accepted request
                $user = $need->user;
                Notification::send($user, new RequestAcceptedNotification($need));
            } else if ($status == '-1') {
                $need->status = '-1';
    
                // Send notification for rejected request
                $user = $need->user;
                Notification::send($user, new RequestDeclinedNotification($need));
            } else {
                return response()->json([
                    'status' => false,
                    'message' => 'Invalid status provided'
                ], 400);
            }
    
            $need->save();
            $newAttributes = $need->getAttributes();
            $changes = array_diff_assoc($newAttributes, $oldAttributes);
            
            $log = new UserActivitylog();
            $log->email = auth()->user()->email;
            $log->modifyuser = 'Updated Request: ' . json_encode($changes);
            $log->date_time = Carbon::now()->utc();
            $log->created_at = Carbon::now()->utc();
            $log->save();
            return response()->json([
                'status' => true,
                'message' => 'Request updated successfully',
                'supplier' => $need,
            ], 200);
        } else 
        {
            return response()->json([
                'status' => false,
                'message' => 'Status is required'
            ], 400);
        }
    }
    public function requestData(){
        $requests = $this->getauthrequest()->original;
        if (isset($requests['Requests'])) {
            return response()->json($requests['Requests']); 
        } else {
            return response()->json(['message' => 'No requests found']);
        }
    }
    public function getTypeName($id)
    {
        $type = Types::find($id);
    
        if (!$type) {
            return response()->json(['error' => 'Type not found'], 404);
        }
    
        $models = $type->models; // retrieve all models associated with this type
    
        if (!$models) {
            return response()->json(['error' => 'No models found for this type'], 404);
        }
    
        return response()->json(['type' => $type], 200);
    }
    public function getneed($id)
    {
        $needs=Need::with('type','model')->find($id);
        if(!$needs){
            return response()->json(['error' => 'Request not found'], 404);
        }

        $type = $needs->type;
        if(!$type){
            return response()->json(['error' => 'Types not found'], 404);
        }
        return response()->json(['show' => $needs], 200);
        $data = compact('show');
        return $data;
  /*       $requests = $this->getneed($id)->original['Request'];
        return response()->json($requests, 200);  */
    }
    public function getallrequest(){
        $needs = Need::with('type','model')->get();  
    
        if (!$needs->count()) {
            return response()->json(['error' => 'No request found'], 404);
        }
        foreach ($needs as $need) {
            $type = $need->type; // assuming you have a "model" relationship defined in the Types model
    
            if (!$type) {
                return response()->json(['error' => 'Type not found'], 404);
            }
        }
        return response()->json(['Requests' => $needs], 200);
    }
    public function getauthrequest() {
        $user = auth()->user();
        $needs = Need::with(['type', 'model'])
            ->where('user_id', $user->id)
            ->get();
        if (!$needs->count()) {
            return response()->json(['error' => 'No request found'], 404);
        }
        foreach ($needs as $need) {
            $type = $need->type;
            $model = $need->model;
            if (!$type || !$model) {
                return response()->json(['error' => 'Type or model not found'], 404);
            }
        }
        return response()->json(['Requests' => $needs], 200);
    }
}