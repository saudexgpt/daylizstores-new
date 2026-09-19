<?php

namespace App\Http\Controllers\Location;

use App\Http\Controllers\Controller;
use App\Laravue\Models\User;
use App\Location;
use Illuminate\Http\Request;

class LocationsController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function fetchAllLocations()
    {
        //
        $locations = Location::get();

        return response()->json(compact('locations'));
    }
    public function index()
    {
        //
        $user = $this->getUser();

        if ($user->isAdmin()) {
            $locations = Location::with('users')->get();
        } else {

            $locations = $user->locations;
        }

        return response()->json(compact('locations'));
    }
    public function assignableUsers()
    {
        $staff_users = User::get();
        $assignable_users = [];
        foreach ($staff_users as $user) {
            if (!$user->isAdmin() && !$user->isAssistantAdmin()) {
                $assignable_users[] = $user;
            }
        }

        return response()->json(compact('assignable_users'));
    }
    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function addUserToLocation(Request $request)
    {
        //
        $user_ids = $request->user_ids;
        $location_id = $request->location_id;
        $location = Location::find($location_id);
        $location->users()->syncWithoutDetaching($user_ids);
        $location_users = $location->users;
        // $location_user = UserLocation::where(['user_id'=> $user_id, 'location_id' => $location_id])->first();

        // if (!$location_user) {
        //     $location_user = new UserLocation();
        //     $location_user->user_id = $user_id;
        //     $location_user->location_id = $location_id;
        //     $location_user->save();
        // }
        $actor = $this->getUser();
        $title = "Staff assigned to $location->name";
        $description = "Staff assigned to $location->name by $actor->name ($actor->email)";
        //log this activity
        $roles = ['assistant admin', 'location manager'];
        $this->logUserActivity($title, $description, $roles);
        return response()->json(compact('location_users'), 200);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Location\Location  $location
     * @return \Illuminate\Http\Response
     */
    public function show(Location $location)
    {
        //
        return response()->json(compact('location'), 200);
    }
    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */

    public function store(Request $request, Location $location)
    {
        //
        $name = $request->name;
        $address = $request->address;
        $location = Location::where('name', $name)->first();

        if (!$location) {
            $location = new Location();
            $location->name = $name;
            $location->address = $address;
            $location->save();
        }
        $actor = $this->getUser();
        $title = "Created new location";
        $description = "$actor->name ($actor->email) created $location->name";
        //log this activity
        $roles = ['assistant admin', 'location manager'];
        $this->logUserActivity($title, $description, $roles);
        return $this->show($location);
    }



    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Location\Location  $location
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Location $location)
    {
        //
        $location->name = $request->name;
        $location->address = $request->address;
        $location->enabled = $request->enabled;
        $location->save();

        $actor = $this->getUser();
        $title = "Updated location information";
        $description = "$actor->name ($actor->email) updated $location->name information";
        //log this activity
        $roles = ['assistant admin', 'location manager'];
        $this->logUserActivity($title, $description, $roles);
        return $this->show($location);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Location\Location  $location
     * @return \Illuminate\Http\Response
     */
    public function destroy(Location $location)
    {
        //
        $actor = $this->getUser();
        $title = "Deleted $location->name";
        $description = "$actor->name ($actor->email) deleted $location->name information";
        //log this activity
        $roles = ['assistant admin', 'location manager'];
        $this->logUserActivity($title, $description, $roles);
        $location->delete();
        return response()->json(null, 204);
    }
}
