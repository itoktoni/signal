<?php

namespace App\Http\Controllers;

use App\Enums\ActorType;
use App\Enums\RoleType;
use App\Models\Group;
use App\Models\User;
use App\Traits\ControllerHelper;
use Illuminate\Http\Request;

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
        $perPage = request('perpage', 10);
        $data = User::filter(request())->paginate($perPage);
        $data->appends(request()->query());

        return $this->views($this->module(), [
            'data' => $data,
        ]);
    }

    public function share($data = [])
    {
        $role = RoleType::getOptions();
        $group = Group::getOptions();

        return array_merge($data, [
            'model' => false,
            'role' => $role,
            'group' => $group,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function getCreate()
    {
        return $this->views('user.form');
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

        return $this->views($this->module('form'), $this->share([
            'model' => $model,
        ]));
    }

    /**
     * Update the specified resource in storage.
     */
    public function postUpdate(Request $request, User $user)
    {
        return $this->update($request->all(), $user);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function getDelete(User $user)
    {
        $user->delete();

        return redirect()->route($this->module('getData'))->with('success', 'deleted successfully');
    }

    public function postDelete(User $user)
    {
        $user->delete();

        return redirect()->route($this->module('getData'))->with('success', 'deleted successfully');
    }

    public function getProfile()
    {
        return view($this->module());
    }

    public function getSecurity()
    {
        return view($this->module());
    }

    public function postBulkDelete(Request $request)
    {
        $ids = explode(',', $request->ids);
        User::whereIn('id', $ids)->delete();

        return redirect()->route($this->module('getData'))->with('success', 'deleted successfully');
    }
}
