<?php

namespace App\Models;

use App\Notifications\ResetPassword;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable  //Authenticatable 授权相关功能的引用
{
    //消息通知相关功能引用
    use Notifiable;


    /**
     * The attributes that are mass assignable.
     *过滤用户提交的字段，只有包含在该属性中的字段才能够被正常更新
     * @var array
     */
    protected $fillable = [
        'name', 'email', 'password',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *对用户密码或其它敏感信息在用户实例通过数组或 JSON 显示时进行隐藏，则可使用 hidden 属性
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token',
    ];

//    使用Gravatar来生成用户头像
    public function gravatar($size = '100'){
        //通过 $this->attributes['email'] 获取到用户的邮箱；,使用 trim 方法剔除邮箱的前后空白内容；
        //用 strtolower 方法将邮箱转换为小写；将小写的邮箱使用 md5 方法进行转码；将转码后的邮箱与链接、尺寸拼接成完整的 URL 并返回；
        $hash = md5(strtolower(trim($this->attributes['email'])));
        return "http://www.gravatar.com/avatar/$hash?s=$size";
    }

    //发送邮件相关,boot 方法会在用户模型类完成初始化之后进行加载，因此我们对事件的监听需要放在该方法中。
    public static function boot(){
        parent::boot();

        static::creating(function($user){
            $user->activation_token = str_random(30);
        });
    }

    public function sendPasswordResetNotification($token)
    {
        $this->notify(new ResetPassword($token));
    }

    //一个用户对应多条微博
    public function statuses(){
        return $this->hasMany(Status::class);
    }

    //最新的微博在最上面
    public function feed()
    {
        /*
         * 通过 followings 方法取出所有关注用户的信息，再借助 pluck 方法将 id 进行分离并赋值给 user_ids；
            将当前用户的 id 加入到 user_ids 数组中；
            使用 Laravel 提供的 查询构造器 whereIn 方法取出所有用户的微博动态并进行倒序排序；
            我们使用了 Eloquent 关联的 预加载 with 方法，预加载避免了 N+1 查找的问题，大大提高了查询效率
         * */
        $user_ids = Auth::user()->followings->pluck('id')->toArray();
        array_push($user_ids, Auth::user()->id);
        return Status::whereIn('user_id', $user_ids)
            ->with('user')
            ->orderBy('created_at', 'desc');
    }

    //获取粉丝列表
    public function followers()
    {
        return $this->belongsToMany(User::Class, 'followers', 'user_id', 'follower_id');
    }

    //获取关注人列表
    public function followings()
    {
        return $this->belongsToMany(User::Class, 'followers', 'follower_id', 'user_id');
    }

    //关注
    public function follow($user_ids)
    {
        if (!is_array($user_ids)) {
            $user_ids = compact('user_ids');
        }
        $this->followings()->sync($user_ids, false);
    }

    //取消关注
    public function unfollow($user_ids)
    {
        if (!is_array($user_ids)) {
            $user_ids = compact('user_ids');
        }
        $this->followings()->detach($user_ids);
    }

    //是否关注
    public function isFollowing($user_id)
    {
        return $this->followings->contains($user_id);
    }

}
