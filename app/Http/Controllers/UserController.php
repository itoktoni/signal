<?php

namespace App\Http\Controllers;

use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Models\User;
use App\Traits\ContextHelper;
use App\Traits\ControllerHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    use ControllerHelper;

    protected $model;

    public function __construct(User $model)
    {
        $this->model = $model;
    }

    /**
     * Display a listing of the resource.
     */


    public function index()
    {
        return redirect()->route($this->module('getData'));
    }

    public function getData()
    {
        $perPage = request('perpage', 15);
        $data = User::filter(request())->paginate($perPage);

        return $this->views($this->module(),[
            'data' => $data,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function getCreate()
    {
        return $this->views($this->module());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function postCreate()
    {
        return $this->create(request()->all());
    }

    /**
     * Display the specified resource.
     */
    public function getShow(User $user)
    {
        return $this->views($this->module());
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function getUpdate($code)
    {
        $model = User::find($code);

        return $this->views($this->module(),$this->share([
            'model' => $model,
        ]));
    }

    /**
     * Update the specified resource in storage.
     */
    public function postUpdate(Request $request, User $user)
    {
        app(UpdateUserProfileInformation::class)->update($user, $request->only(['name', 'email']));

        return redirect()->route('user.index')->with('success', 'User updated successfully');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function getDelete(User $user)
    {
        $user->delete();

        return redirect()->route('user.index')->with('success', 'User deleted successfully');
    }

    public function postDelete(User $user)
    {
        $user->delete();

        return redirect()->route('user.index')->with('success', 'User deleted successfully');
    }

    public function getProfile()
    {
        return view('user.profile');
    }

    public function getSecurity()
    {
        return view('user.profile');
    }
}