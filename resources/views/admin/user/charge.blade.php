@extends('admin._layoutNew')

@section('page-head')

@endsection

@section('page-content')
<style>
    .status_bg_1{
        background: #1E9FFF;
    }
    .status_bg_2{
        background: #5fb878;
    }
    .status_bg_3{
        background: #ff5722;
    }
</style>
    <div style="margin-top: 10px;width: 100%;">
        

        <form class="layui-form layui-form-pane layui-inline" action="">
        
        <?php if(!$id){?>
            <div class="layui-inline">
                <label class="layui-form-label">用户名</label>
                <div class="layui-input-inline" style="margin-left: 10px">
                    <input type="text" id="account_number" name="account_number" autocomplete="off" class="layui-input">
                </div>
            </div>
       <?php } ?>    
       
        <div class="layui-input-inline date_time111" style="margin-left: 10px;">
           <input type="text" name="oid" id="oid" placeholder="订单号" autocomplete="off" class="layui-input" value="">
       </div>
       
             <div class="layui-input-inline date_time111" style="margin-left: 10px;">
           <input type="text" name="start_time" id="start_time" placeholder="请输入开始时间" autocomplete="off" class="layui-input" value="">
       </div>
       
       <div class="layui-input-inline" style="margin-left: 10px">
       </div>
       <div class="layui-input-inline date_time111">
           <input type="text" name="end_time" id="end_time" placeholder="请输入结束时间" autocomplete="off" class="layui-input" value="">
       </div>
       
       
       <div class="layui-inline" style="margin-left: 10px;">
            <label>充值状态</label>
            <div class="layui-input-inline" style="width: 90px">
                <select name="status" id="status" class="layui-input">
                    <option value="">所有</option>
                    <option value="1">申请充值</option>
                    <option value="2">充值完成</option>
                    <option value="3">充值驳回</option>
                </select>
            </div>
        </div>
        
         <?php if(!$id){?>
        <div class="layui-inline" style="margin-left: 10px;">
            <label>代理筛选</label>
            <div class="layui-input-inline" style="width: 90px">
                <select name="daili" id="daili" class="layui-input">
                    <option value="">所有</option>
                    <?php foreach($dailis as $vaue){?>
                    <option value="{{$vaue->user_id }}">{{$vaue->username}}</option>
                   <?php } ?>
                </select>
            </div>
        </div>
       <?php } ?>     
       
            <div class="layui-inline" style="margin-left: 10px">
                <div class="layui-input-inline">
                    <button class="layui-btn" lay-submit="" lay-filter="mobile_search"><i class="layui-icon">&#xe615;</i></button>
                </div>
            </div>
            



        </form>
       <br/>
       充值总数:<span id="suma" style="color:#d65140"></span>
    </div>

    <script type="text/html" id="switchTpl">
        <input type="checkbox" name="is_recommend" value="@{{d.id}}" lay-skin="switch" lay-text="是|否" lay-filter="sexDemo" @{{ d.is_recommend == 1 ? 'checked' : '' }}>
    </script>

    <table id="demo" lay-filter="test"></table>
    <script type="text/html" id="barDemo">
    
    <a class="layui-btn layui-btn-xs" lay-event="show">查看</a>
    
    </script>
    <script type="text/html" id="statustml">
        @{{d.status==1 ? '<span class="layui-badge status_bg_1">'+'申请充值'+'</span>' : '' }}
        @{{d.status==2 ? '<span class="layui-badge status_bg_2">'+'充值完成'+'</span>' : '' }}
        @{{d.status==3 ? '<span class="layui-badge status_bg_3">'+'申请失败'+'</span>' : '' }}

    </script>
	<script type="text/html" id="ophtml">
        @{{d.status==1 ? '<button type="button" onclick="pass('+d.id+')">通过</button> <button type="button" onclick="refuse('+d.id+')" data-id='+d.id+' class="btn-refuse">拒绝</button>' : '' }}
   

    </script>

    <script type="text/html" id="bindFiles">
         @{{(/\.(png|jpg|gif|jpeg|webp)$/).test(d.user_account) == true ? '<img src="'+d.user_account+'" alt="'+d.user_account+'" style="width:60px;cursor:pointer" onclick="showBigImage(this)">' : '<img src="'+d.user_account+'" alt="'+d.user_account+'" style="width:60px;cursor:pointer" onclick="showBigImage(this)">' }}
    </script>

@endsection

@section('scripts')
   
    <script>

        layui.use(['table','form','laydate','element',"layer"], function(){
            var table = layui.table;
            var $ = layui.jquery;
            var form = layui.form;
            var layer = layui.layer;
             var laydate = layui.laydate;
            
            laydate.render({
                            elem: '#start_time'
                        });
                        laydate.render({
                            elem: '#end_time'
                        });
              
               
                  form.on('submit(mobile_search)',function(obj){
                            var start_time =  $("#start_time").val()
                            var end_time =  $("#end_time").val()
                            var account_number =  $("#account_number").val()
                            
                            var status = $('#status').val()
                            var daili = $('#daili').val()
                            
                            var oid = $("#oid").val();
                            tbRend("{{url('admin/user/charge_list')}}?account_number="+account_number
                                +'&start_time='+start_time
                                +'&end_time='+end_time
                                +'&daili='+daili
                                +'&status='+status
                                +'&oid='+oid
                                +"&id=<?php echo $id;?>"
                            );
                            return false;
              
                        });     
                        
            function tbRend(url){
                 //第一个实例
            table.render({
                elem: '#demo'
                ,url: url //数据接口
                ,page: true //开启分页
                ,id:'mobileSearch'
                ,limits: [10,  30,  50,80,100,500,1000,5000], // 可选的每页显示数据量
                done: function(res, curr, count) {
                // 数据加载完成后的回调函数
              // console.log(res); // 输出返回的数据
                $("#suma").html(res.extra_data);
              
              }
                ,cols: [[ //表头
                    {field: 'oid', title: '订单id', width:180, sort: true}
                    ,{field: 'account_number', title: '用户名', width:120}
                    ,{field: 'name', title: '虚拟币', width:80}
                    ,{field: 'paymentsName', title: '充值通道', width:80}
                    // ,{field: 'user_account', title: '支付账号', minWidth:110}
                    // ,{field: 'user_account', title: '支付凭证', minWidth:110,templet:"#acc"}
                    ,{field: 'user_account', title: '封面图', templet: "#bindFiles"}
                    // ,{field: 'bank_account', title: '银行卡号', minWidth:80,templet:function(d){
                    //     if(d.type){
                    //         return d.bank_account;
                    //     }else{
                    //         return '';
                    //     }
                    // }}
                    // ,{field: 'address', title: '提币地址', minWidth:100}
                    ,{field: 'amount', title: '数量', minWidth:80}
                    ,{field: 'give', title: '赠送数量', minWidth:80}
                    // ,{field: 'amount', title: '充值金额￥', minWidth:80,templet:function(d){
                    //     let give = 0;
                    //     if(d.give) give = d.give;
                    //     return (d.amount*d.rmb_relation*d.price) + (give*d.rmb_relation*d.price) +"元";
                    // }}
                    // ,{field: 'hes_account', title: '承兑商交易账号', minWidth:180}
                    // ,{field: 'money', title: '交易额度', minWidth:100}
                    ,{field: 'status', title: '交易状态', minWidth:100, templet: '#statustml'}
                    ,{field: 'created_at', title: '提币时间', minWidth:180}
                   
                    ,{title:'操作',minWidth:120,templet: '#ophtml'}

                ]]
            });
          
            }            
           tbRend("{{url('admin/user/charge_list')}}?id=<?php echo $id;?>");
            //监听热卖操作
            // form.on('switch(sexDemo)', function(obj){
            //     var id = this.value;
            //     $.ajax({
            //         url:'{{url('admin/product_hot')}}',
            //         type:'post',
            //         dataType:'json',
            //         data:{id:id},
            //         success:function (res) {
            //             if(res.error != 0){
            //                 layer.msg(res.msg);
            //             }
            //         }
            //     });
            // });
		})
		function pass(id){
        
          $.ajax({
				url:'{{url('admin/user/pass_req')}}',
				type:'post',
				dataType:'json',
				data:{id:id},
				success:function (res) {
                     if(res.type == 'ok'){
                         layer.msg(res.message);
                         setTimeout(function(){
                             window.location.reload(); 
                         },1200)
                     }
                 }
		   })
		}
		   function refuse(id){
          $.ajax({
				url:'{{url('admin/user/refuse_req')}}',
				type:'post',
				dataType:'json',
				data:{id:id},
				success:function (res) {
                   if(res.type == 'ok'){
                         layer.msg(res.message);
                         setTimeout(function(){
                             window.location.reload(); 
                         },1200)
                     }
                 }
		   })
		  }
		   
            //监听提交
            
    </script>
     <script type="text/javascript">
        //显示大图片
        function showBigImage(e) {
            parent.layer.open({
                type: 1,
                title: false,
                closeBtn: 0,
                shadeClose: true, //点击阴影关闭
                area: [$(e).width + 'px', $(e).height + 'px'], //宽高
                content: "<img style='max-width:1400px;max-height:800px' src=" + $(e).attr('src') + " />"
            });
        }
         function showBigVideo(e) {
            parent.layer.open({
                type: 1,
                title: $(e).attr('alt'),
                closeBtn: 0,
                shadeClose: true, //点击阴影关闭
                offset: 'auto',
                area: 'auto',
                content: "<video controls='controls' style='max-width:1400px;max-height:780px' src=" + $(e).attr('src') + " />"
            });
        }
    </script>
@endsection