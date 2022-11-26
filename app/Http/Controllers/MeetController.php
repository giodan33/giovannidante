<?php

namespace App\Http\Controllers;

use App\Events\MovementEvent;
use App\Events\PeopleSeeingMeeting;
use App\Events\UserMeetAccess;
use App\Meet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use function PHPUnit\Framework\isNull;

class MeetController extends Controller
{

    /**
     * Store a newly meet createby first time.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function storeAsHost(Request $request)
    {
        try {
            $inviteController = new InviteController();
            $code = $inviteController->store($request->max, $request->fecha);

            $backupController = new BackupController();
            $bakcup = $backupController->store();


            $meet = Meet::create([
                'invite_id' => $code->id,
                'backup_id' => $bakcup->id,
                'name' => $request->name,
                'description' => $request->description,
            ]);

            event(new UserMeetAccess($meet, Auth::user(), 1));
            broadcast(new PeopleSeeingMeeting($meet))->toOthers();
            return redirect()->route('board', ['invite_code' => $code->code,'meet_id'=> $meet->id]);
        }catch (\Exception $e){
            return redirect()->route('home')->with(['message' => "Asegurese de completar todos los campos requeridos"]);
        }
    }

    /**
     * Join to an already created meet as a guests
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse|void
     */
    public function storeAsGuest(Request $request)
    {
        try {
            $inviteController = new InviteController();

            $invite = $inviteController->getInvitation($request->invite_code);
            $meet = $this->searchMeetByInvitationCode($invite->id);

            $exist = $invite != null;
            $canJoin = $inviteController->canJoin($invite);
            if($canJoin && $exist)
            {

                $backupController = new BackupController();
                $backup = $backupController->getIdByMeetId($meet->id);
                $json = $backup->backup;

                event(new UserMeetAccess($meet, Auth::user(),2));
                broadcast(new PeopleSeeingMeeting($meet))->toOthers();

                if(isNull($json)){
                    return redirect()->route('board',['invite_code'=> $request->invite_code,'meet_id'=> $meet->id, 'json'=>$json]) ;
                }
                return redirect()->route('board',['invite_code'=> $request->invite_code,'meet_id'=> $meet->id]) ;

            }
        }catch (\Exception $e){
            return redirect()->route('home')->with('message','El codigo de invitacion ya no esta disponible o no existe.');
        }
    }

    /**
     * get the Meet Object by its ID
     * @param $id
     * @return mixed
     */
    public function getMeetById($id){
        return Meet::where('id', $id)->first();
    }

    /**
     * Update the attrs of a meet and its code invitation
     * @param $id
     * @param $newName
     * @param $newDescription
     * @return mixed
     */
    public function update($id, $newName, $newDescription){
        $meet = $this->getMeetById($id);
        return $meet->update([
            'name' => $newName,
            'description' => $newDescription,
        ]);
    }

    private function searchMeetByInvitationCode($inviteId)
    {
        return Meet::where('invite_id', $inviteId)->first();
    }

    /**
     * Main function to join to a meet
     * @param Request $code
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function joinToMeet(Request $code){
        if($code->has('json')){
            return view('board',['invite_code'=>$code->invite_code,'meet_id'=>$code->meet_id, 'json'=> $code->json]);
        }else{
            return view('board',['invite_code'=>$code->invite_code,'meet_id'=>$code->meet_id]);
        }

    }



}