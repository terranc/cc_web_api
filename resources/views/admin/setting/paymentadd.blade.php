@extends('admin._layoutNew')

@section('page-head')

@endsection

@section('page-content')
    <form class="layui-form" action="">

        <div class="layui-form-item">
            <label class="layui-form-label">通道名称</label>
            <div class="layui-input-block">
                <input type="text" name="name" lay-verify="required" autocomplete="off" placeholder="" class="layui-input" value="{{$result->name}}">
            </div>
        </div>
        
        
        <div class="layui-form-item">
            <label class="layui-form-label">商户号</label>
            <div class="layui-input-block">
                <input type="text" name="root" lay-verify="required" autocomplete="off" placeholder="" class="layui-input" value="{{$result->root}}">
            </div>
        </div>
        
      <?php if($result->type == 1){?>
          <div class="layui-form-item layui-form-text">
                <label class="layui-form-label">收款码</label>
                <div class="layui-input-block">
                    <button class="layui-btn" type="button" id="upload_test">选择图片</button>
                    <br>
                    <img src="@if(!empty($result->image)){{$result->image}}@endif" id="img_thumbnail" class="thumbnail" style="display: @if(!empty($result->image)){{"block"}}@else{{"none"}}@endif;max-width: 200px;height: auto;margin-top: 5px;">
                    <input type="hidden" name="image" id="thumbnail" value="@if(!empty($result->image)){{$result->image}}@endif">
                </div>
            </div>
         <?php } ?>   
        <div class="layui-form-item">
            <label class="layui-form-label">
                 <?php if($result->type == 1){?>
                支付地址
                 <?php }else{echo "商户密钥";} ?>   
            </label>
            <div class="layui-input-block">
                <input type="text" name="address" lay-verify="required" autocomplete="off" placeholder="" class="layui-input" value="{{$result->address}}">
            </div>
        </div>
    
       
        <div class="layui-form-item">
            <label class="layui-form-label">排序</label>
            <div class="layui-input-block">
                <input type="text" name="sort" lay-verify="required" autocomplete="off" placeholder="" class="layui-input" value="{{$result->sort}}">
            </div>
        </div>
        
        <div class="layui-form-item">
            <label class="layui-form-label">汇率</label>
            <div class="layui-input-block">
                <input type="text" name="exchange_rate" lay-verify="required" autocomplete="off" placeholder="" class="layui-input" value="{{$result->exchange_rate}}">
            </div>
        </div>

       

        <input type="hidden" name="id" value="{{$result->id}}">
        <div class="layui-form-item">
            <div class="layui-input-block">
                <button class="layui-btn" lay-submit="" lay-filter="demo1">立即提交</button>
                <button type="reset" class="layui-btn layui-btn-primary">重置</button>
            </div>
        </div>
    </form>

@endsection

@section('scripts')
    <script>

        layui.use(['upload', 'form', 'laydate', 'element', 'layer'],function () {
            
            
            var upload = layui.upload 
            ,form = layui.form
            ,layer = layui.layer
            ,$ = layui.$
            ,laydate = layui.laydate
            ,index = parent.layer.getFrameIndex(window.name)
            ,element = layui.element
        var uploadInst = upload.render({
            elem: '#upload_test' //绑定元素
            ,url: '{{URL("api/upload")}}?scene=admin' //上传接口
            ,done: function(res){
                //上传完毕回调
                if (res.type == "ok"){
                    $("#thumbnail").val(res.message)
                    $("#img_thumbnail").show()
                    $("#img_thumbnail").attr("src",res.message)
                } else{
                    alert(res.message)
                }
            }
            ,error: function(){
                //请求异常回调
            }
        }); 
            
            
           
            //监听提交
            form.on('submit(demo1)', function(data){
                var data = data.field;
                $.ajax({
                    url:'{{url('admin/payment/adds')}}'
                    ,type:'post'
                    ,dataType:'json'
                    ,data : data
                    ,success:function(res){
                        if (res.type=='error') {
                            layer.msg(res.message);
                        } else {
                            parent.layer.close(index);
                            parent.window.location.reload();
                        }
                    }
                });
                return false;
            });
        });
    </script>

@endsection