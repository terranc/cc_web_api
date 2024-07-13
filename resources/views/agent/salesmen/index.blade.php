@extends('admin._layoutNew')
@section('page_head')

@stop

@section('page-content')

    <button class="layui-btn layui-btn-normal layui-btn-radius" id="add_agent">添加代理</button>

    <table class="layui-hide" id="adminUsers" lay-filter="adminList"></table>


    <script type="text/html" id="barDemo">
        <a class="layui-btn layui-btn-xs" lay-event="edit">修改</a>
        <a class="layui-btn layui-btn-danger layui-btn-xs" lay-event="del">删除</a>
    </script>
@stop
@section('scripts')
    <script type="text/javascript">
        window.onload = function () {
            layui.use(['layer', 'table'], function () { //独立版的layer无需执行这一句
                var $ = layui.jquery;
                var layer = layui.layer; //独立版的layer无需执行这一句
                var table = layui.table;
                var form = layui.form;
                $('#add_agent').click(function(){layer_show('添加代理', '/admin/manager/add_agent?dailis=2&id='+"{{$id}}");});
                table.render({
                    elem: '#adminUsers',
                    url: '/agent/huodaixiaj',
                    page: false,
                    cols: [[
                    //    {field: 'id', title: 'ID', minWidth: 100, sort: true},
                        {field: 'username', title: '代理', minWidth: 150},
                        {field: 'user_id', title: '前端id', minWidth: 60},
                        {field: 'user_count', title: '用户数量', minWidth: 80},
                        {field: 'dailimoney1', title: '代理金额调整', minWidth: 80},
                        {field: 'dailimoney2', title: '系统金额调整', minWidth: 80},
                        {field: 'qdmoney', title: '前台充值金额', minWidth: 80},
                        {field: 'tixian', title: '提现金额', minWidth: 80},
                        {field: 'user_codes', title: '邀请码', minWidth: 80},
                        {field: 'is_lock', title: '代理状态', minWidth: 60},
                        
                        
                        {fixed: 'right', title: '操作', minWidth: 150, align: 'center', toolbar: '#barDemo'}
                    ]]
                });



                //监听工具条
                table.on('tool(adminList)', function(obj){ //注：tool是工具条事件名，test是table原始容器的属性 lay-filter="对应的值"
                    var data = obj.data; //获得当前行数据
                    var layEvent = obj.event; //获得 lay-event 对应的值（也可以是表头的 event 参数对应的值）
                    var tr = obj.tr; //获得当前行 tr 的DOM对象

                    if(layEvent === 'del'){ //删除
                        layer.confirm('真的要删除吗？', function(index){
                            //向服务端发送删除指令
                            $.ajax({
                                url:'/admin/manager/delete_agent',
                                type:'post',
                                dataType:'json',
                                data:{id:data.id},
                                success:function(res){
                                    if(res.type=='ok'){
                                        obj.del(); //删除对应行（tr）的DOM结构，并更新缓存
                                        layer.msg(res.message);
                                        layer.close(index);
                                    }else{
                                        layer.close(index);
                                        layer.alert(res.message);
                                    }
                                }
                            });
                        });
                    } else if(layEvent === 'edit'){ //编辑
                        //do something
                            layer_show('修改代理', '/admin/manager/edit_agent?id=' + data.id+"&admin_id={{$id}}");

                    }
                });


            });


        }

    </script>

@stop