<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Auth;

class UsersController extends Controller
{

    public function __construct(){
        //第一个参数是中间件名称，第二个是要过滤进行的动作, 这里表示除了下面的几个的动作必须登录才能访问
        $this->middleware('auth',[
            'except' => ['show', 'create', 'store','index','confirmEmail']
        ]);

        //只让未登录用户访问注册页面,登录用户无法访问注册页面
        $this->middleware('guest',[
            'only' => ['create']
        ]);
    }

    public function index(){
        $users = User::paginate(10);
        return view('users.index',compact('users'));
    }

    //
    public function create(){
        return view('users.create');
    }

    public function show(User $user){
        return view('users.show',compact('user'));
    }

    public function store(Request $request){
        //验证
        $this->validate($request,[
            'name' => 'required|max:50',
            'email' => 'required|email|unique:users|max:255',
            'password' => 'required|confirmed|min:6'
        ]);
        //添加到数据库
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => bcrypt($request->password),
        ]);
        //注册后的用户会自动进行登录
//        Auth::login($user);
        //注册时发送邮件
        $this->sendEmailConfirmationTo($user);
        //访问会话实例，flash表示下次请求时有效
        session()->flash('success','验证邮件已发送到你的注册邮箱上，请注意查收。');

        return redirect('/');
    }

    public function edit(User $user){

        //authorize 方法，它可以被用于快速授权一个指定的行为，当无权限运行该行为时会抛出
        //authorize 方法接收两个参数，第一个为授权策略的名称，第二个为进行授权验证的数据, 这里是调用了UserPolicy中的update方法
        //$user 对应传参 update 授权方法的第二个参数,默认不需要传递第一个参数
        $this->authorize('update',$user);
        return view('users.edit',compact('user'));
    }

    public function update(User $user,Request $request){
        $this->validate($request,[
            'name' => 'required|max:50',
            'password' => 'nullable|confirmed|min:6'
        ]);
        $this->authorize('update',$user);

        $data = [];
        $data['name'] = $request->name;
        if($request->password){
            $data['password'] = bcrypt($request->password);
        }
        $user->update($data);

        session()->flash('success','个人资料更新成功');

        return redirect()->route('users.show',$user->id);
    }

    public function destroy(User $user){
        $this->authorize('destroy',$user);
        $user->delete();
        session()->flash('success','成功删除用户！');
        return back();
    }

    //发送邮件
    public function sendEmailConfirmationTo($user){
        $view = 'emails.confirm';
        $data = compact('user');
        $from = 'aufree@yousails.com';
        $name = 'Aufree';
        $to = $user->email;
        $subject = "感谢注册 简易微博 应用！请确认你的邮箱。";

        \Mail::send($view,$data,function ($message) use ($from,$name,$to,$subject){
            $message->from($from,$name)->to($to)->subject($subject);
        });
    }

    //激活帐号
    public function confirmEmail($token){
        $user = User::where('activation_token',$token)->firstOrFail();

        $user->activated = true;
        $user->activation_token = null;
        $user->save();

        Auth::login($user);
        session()->flash('success','恭喜您，激活成功！');
        return redirect()->route('users.show',[$user]);
    }
}
