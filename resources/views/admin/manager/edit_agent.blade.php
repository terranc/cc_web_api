@extends('admin._layoutNew')
@section('page-head')

@stop
@section('page-content')
    <header class="larry-personal-tit">
        <span>代理 </span>
    </header><!-- /header -->
    <div class="larry-personal-body clearfix">
        <form class="layui-form col-lg-5">
            <div class="layui-form-item">
                <label class="layui-form-label">用户名</label>
                <div class="layui-input-block">
                    <input type="text" name="username" autocomplete="off" class="layui-input" value="{{ $admin_user->username }}" placeholder="">
                </div>
            </div>
            <div class="layui-form-item">
                <label class="layui-form-label">初始密码</label>
                <div class="layui-input-block">
                    <input type="password" name="password" autocomplete="off" class="layui-input" value="" placeholder="">
                </div>
            </div>
            
             <div class="layui-form-item">
                <label class="layui-form-label">前端账号id</label>
                <div class="layui-input-block">
                    <input type="text" name="user_id" autocomplete="off" class="layui-input" value="{{ $admin_user->user_id }}" placeholder="">
                </div>
            </div>
            
            <div class="layui-form-item">
            <div class="layui-inline">
                <label class="layui-form-label">账户状态</label>
                <div class="layui-input-block">
                <input type="radio" name="is_lock" class="sports" value="0" title="正常" {{ $admin_user->is_lock== 0 ? 'checked' : '' }}>
                <input type="radio" name="is_lock" class="sports" value="1" title="锁定" {{ $admin_user->is_lock== 1 ? 'checked' : '' }}>
                </div>
            </div>
        </div>
            
            <input type="hidden" name="id" value="{{ $admin_user->id}}">
            <div class="layui-form-item">
                <div class="layui-input-block">
                    <button class="layui-btn" lay-submit lay-filter="adminuser_submit">立即提交</button>
                    <button type="reset" class="layui-btn layui-btn-primary">重置</button>
                </div>
            </div>
        </form>
    </div>
@stop
@section('scripts')
    <script type="text/javascript">

        layui.use(['form','upload','layer'], function () {
            var layer = layui.layer;
            var form = layui.form;
            var $ = layui.$;
            form.on('submit(adminuser_submit)', function (data) {
                var data = data.field;
                $.ajax({
                    url: '/admin/manager/edit_agents',
                    type: 'post',
                    dataType: 'json',
                    data: data,
                    success: function (res) {
                        layer.msg(res.message);
                        if(res.type == 'ok') {
                            var index = parent.layer.getFrameIndex(window.name);
                            parent.layer.close(index);
                            parent.window.location.reload();
                        }else{
                            return false;
                        }
                    }
                });
                return false;
            });

        });


    </script>
@stop