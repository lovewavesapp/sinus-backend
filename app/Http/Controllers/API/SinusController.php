<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Sinus;
use App\Models\SinusValue;
use App\Models\Following;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;

class SinusController extends Controller
{
	public function indexCreated()
	{
		$createdSinuses = Sinus::where('user_id', Auth::id())->get();
		foreach ($createdSinuses as $sinus) {
			$sinus->following = true;
		}
		return Response::json($createdSinuses, 200);
	}

	public function indexExplore()
	{
		$retrieveSine = Sinus::orWhere('archived', false)->orWhere('archived', null)->get();
		foreach ($retrieveSine as $sinus) {
			// Determine whether user is following the sinus already or not
			if (Auth::guest()) {
				$sinus->following = false;
			} else {
				if (Following::where('user_id', Auth::id())->where('following_user_id', $sinus->user_id)->first() || $sinus->user_id == Auth::id()) {
					$sinus->following = true;
				} else {
					$sinus->following = false;
				}
			}
		}

		return Response::json($retrieveSine, 200);
	}

	public function indexFollowing()
	{
		$retrieveFollowing = Following::where('user_id', Auth::id())->pluck('following_user_id')->toArray();
		if (!Auth::guest()) {
			array_push($retrieveFollowing, Auth::id()); // User always follows themselves
		}

		$retrieveSine = DB::table('sinuses')->where(function ($query) use ($retrieveFollowing) {
			$query->whereIn('user_id', $retrieveFollowing)->where('archived', false);
		})->orWhere(function ($query) use ($retrieveFollowing) {
			$query->whereIn('user_id', $retrieveFollowing)->where('archived', null);
		})->get();

		foreach ($retrieveSine as $sinus) {
			$sinus->following = true;
		}

		return Response::json($retrieveSine, 200);
	}

	public function store(Request $request)
    {
		$request->validate([
			'name' => 'required|max:30',
			'date_name' => 'required|max:30',
			'avatar' => 'sometimes|mimes:jpeg,png|max:4096',
		]);

		$avatar = null;
		if ($request->has('avatar')) {
			$avatar = Storage::putFile('avatars', $request->file('avatar'));
		}

		$newSinus = new Sinus([
			'name' => $request->get('name'),
			'user_id' => Auth::id(),
			'date_name' => $request->get('date_name'),
			'avatar' => $avatar,
        ]);

        $newSinus->save();

		return Response::json("Successfully added new wave", 200);
	}

	public function show($id)
    {
        $sinus = Sinus::findOrFail($id);
        if (Following::where('user_id', Auth::id())->where('following_user_id', $sinus->user_id)->first()) {
            $following = true;
        } else {
            $following = false;
        }

        $sinus->following = $following;
        return Response::json($sinus, 200);
	}

	public function update(Request $request)
	{
		$request->validate([
			'id' => 'required|integer',
			'date_name' => 'sometimes|max:30',
			'archived' => 'sometimes|boolean',
			'avatar' => 'sometimes|mimes:jpeg,png|max:4096',
		]);

		$sinus = Sinus::where('id', $request->get('id'));
		if ($request->has('date_name')) {
			$sinus->date_name = $request->has('date_name');
		}

		if ($request->has('archived')) {
			$sinus->archived = $request->has('archived');
		}

		if ($request->has('avatar')) {
			if ($sinus->avatar != null) {
                Storage::delete($sinus->avatar);
            }

			$sinus->avatar = Storage::putFile('avatars', $request->file('avatar'));
		}

		$sinus->save();
		return Response::json("Wave has been updated", 200);
	}

	public function delete(Request $request)
	{
		$request->validate([
			'id' => 'required|integer',
		]);

		$sinus = Sinus::where('id', $request->get('id'));
		Storage::delete($sinus->avatar);
		$sinusDeletion = $sinus->delete();
		if (!$sinusDeletion) {
			return Response::json($sinus, 200);
		}
		
		$sinusValues = SinusValue::where('sinus_id', $request->get('id'));
		if (!$sinusValues->delete() && $sinusDeletion) {
			// Rollback Sinus deletion if sinusValue deletion failed
			Sinus::onlyTrashed()->where('id', $request->get('id'))->restore();
		}

		return Response::json("Wave has been permanently deleted", 200);
	}
}
