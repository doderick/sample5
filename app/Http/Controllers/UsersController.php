<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Auth;
use Mail;

class UsersController extends Controller
{
    /**
     * 构造中间件验证，过滤http请求
     */
    public function __construct()
    {
        // 允许except之后的动作被游客访问
        $this->middleware('auth', [
            'except' => ['create', 'store']
        ]);

        // 只允许only之后的动作被游客访问
        $this->middleware('guest', [
            'only' => ['create']
        ]);
    }

    /**
     * 用户注册的create动作
     *
     * @return 用户注册页面视图
     */
    public function create()
    {
        return view('users.create');
    }

    /**
     * 储存用户注册信息的方法
     *
     * @param Request $request 用户提交的注册请求
     * @return 注册成功后返回应用首页
     */
    public function store(Request $request)
    {
        // 对用户填写注册信息进行合法性验证
        $this->validate($request, [
            'name'     => 'required|max:50',
            'email'    => 'required|email|max:255',
            'password' => 'required|confirmed|min:6'
        ]);

        // 验证通过之后将信息写入对应的数据表users
        $user = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => bcrypt($request->password)
        ]);

        // 发送激活邮件
        $this->sendActivateEmailTo($user);

        // 提示用户查收激活邮件
        session()->flash('info', '激活邮件已发送到您的邮箱上，请注意查收～');

        // 重定向至应用首页
        return redirect()->route('home');
    }

    /**
     * 发送激活邮件的方法
     *
     * @param $user 新注册的用户
     * @return void
     */
    public function sendActivateEmailTo($user)
    {
        $view = 'emails.activate';
        $data = compact('user');
        $to = $user->email;
        $subject = "感谢您注册Sample Ⅴ！请确认您的邮箱地址。";

        Mail::send($view, $data, function($message) use ($to, $subject) {
            $message->to($to)->subject($subject);
        });
    }

    /**
     * 用户激活的方法
     *
     * @param integer $id   执行激活操作的用户的id
     * @param string $token 执行激活操作的用户的激活令牌
     * @return 激活用户的主页
     */
    public function activate($id, $token)
    {
        $user = User::find($id);
        if ($user->activation_token === $token) {
            $user->is_activated = true;
            $user->activation_token = null;
            $user->save();
        }

        // 用户激活后自动登录
        Auth::login($user);
        session()->flash('success', '恭喜您，您的账号已经激活。');
        return redirect()->route('users.show', compact('user'));
    }

    /**
     * 访问用户列表的方法
     *
     * @return 用户列表视图
     */
    public function index()
    {
        $users = User::paginate(20);
        return view('users.index', compact('users'));
    }

    /**
     * 显示用户主页的方法
     *
     * @param User $user 用户的一个实例
     * @return 用户的主页
     */
    public function show(User $user)
    {
        return view('users.show', compact('user'));
    }

    /**
     * 用户编辑个人资料的方法
     *
     * @param User $user 需要编辑资料的用户
     * @return 用户填写编辑资料的表单视图
     */
    public function edit(User $user)
    {
        // 增加权限验证
        $this->authorize('update', $user);

        return view('users.edit', compact('user'));
    }

    public function update(User $user, Request $request)
    {
        // 验证用户填写资料的合规性
        $this->validate($request, [
            'name'     => 'required|max:50',
            'password' => 'nullable|min:6|confirmed'
        ]);

        // 增加权限验证
        $this->authorize('update', $user);

        $data = [];
        if ($user->can_rename) {
            if ($request->name !== $user->name) {
                $data['name']       = $request->name;
                $data['can_rename'] = false;
            }
        }
        if ($request->password) {
            $data['password'] = bcrypt($request->password);
        }
        $user->update($data);

        // 对用户的修改请求及结果进行判断
        if (!empty($data['name'])) {
            $info = 'success';
            $msg  = '您的用户名已修改成功！';
        } elseif (!empty($data['password'])) {
            Auth::logout();
            $info = 'success';
            $msg  = '您的密码已修改成功，请重新登录！';
        } elseif (!$user->can_rename && !empty($request->name && $request !== $user->name)) {
            $info = 'warning';
            $msg  = '您的用户名未能修改，原因是您曾经修改过用户名！';
        } else {
            $info = 'info';
            $msg  = '您的资料未经修改！';
        }

        // 信息提示，根据结果的不同有不同的信息提示
        session()->flash($info, $msg);

        // 重定向至个人主页
        return redirect()->route('users.show', $user->id);
    }

    /**
     * 删除用户的方法
     *
     * @param User $user 要被删除的用户
     * @return 进行删除请求的页面
     */
    public function destroy(User $user)
    {
        // 加入权限验证
        $this->authorize('destroy', $user);

        $user->delete();
        session()->flash('success', '删除用户操作执行成功！');
        return redirect()->back();
    }
}
