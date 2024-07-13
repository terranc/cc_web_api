@extends('admin._layoutNew')
@section('page-head')
<link rel="stylesheet" type="text/css" href="{{URL("layui/css/layui.css")}}" media="all">
<link rel="stylesheet" type="text/css" href="{{URL("admin/common/bootstrap/css/bootstrap.css")}}" media="all">
<link rel="stylesheet" type="text/css" href="{{URL("admin/common/global.css")}}" media="all">
<link rel="stylesheet" type="text/css" href="{{URL("admin/css/personal.css")}}" media="all">

@endsection
@section('page-content')

	<form class="layui-form" method="POST">
		<input type="hidden" name="id" value="@if (isset($news['id'])){{ $news['id'] }}@endif">
		{{ csrf_field() }}
		<div class="layui-form-item">
			<label class="layui-form-label">新闻标题</label>
			<div class="layui-input-block">
				<input class="layui-input newsName" name="title" lay-verify="required" placeholder="请输入文章标题" type="text" value="@if (isset($news['title'])){{$news['title']}}@endif">
			</div>
		</div>
		

		<div class="layui-form-item">
			<label class="layui-form-label">用户账号</label>
			<div class="layui-input-block">
				<input class="layui-input newsName" name="uid" lay-verify="required" placeholder="请输入用户账号" type="text" value="@if (isset($news['uid'])){{$news['uid']}}@endif">
			</div>
		</div>
		<!--<div class="layui-form-item">-->
		
		<!--	<div class="layui-inline">-->
		<!--		<label class="layui-form-label">用户ID</label>-->
		<!--		<div class="layui-input-inline">-->
		<!--			<select name="uid" class="" lay-filter="uid" lay-verify="required">-->
		<!--				@foreach ($user as $k=>$v)-->
		<!--		        <option value="{{$v->id}}" {{isset($news['lang']) && $news['uid'] == $v['id'] ? 'selected' : ''}}>{{$v->account_number}}</option>-->
		<!--				@endforeach-->
		<!--		    </select>-->
		<!--		</div>-->
		<!--	</div>-->
		<!--	</div>-->


		<div class="layui-form-item">
		
			<div class="layui-inline">		
				<label class="layui-form-label">发布时间</label>
				<div class="layui-input-inline">
					<input class="layui-input newsTime" lay-verify="required|date" name="create_time" type="text" value="@if (isset($news['create_time'])){{ substr($news['create_time'], 0, 10) }}@else{{ date('Y-m-d') }}@endif" id="create_time">
				</div>
			</div>
		</div>
		
		<!--<div class="layui-form-item">-->
		<!--	<label class="layui-form-label">文章内容</label>-->
		<!--	<div class="layui-input-block">-->
		<!--		<input class="layui-input newsName" name="content" lay-verify="required" placeholder="请输入文章内容" type="text" value="@if (isset($news['content'])){{$news['content']}}@endif">-->
		<!--	</div>-->
		<!--</div>-->
	
		<div class="layui-form-item" style="">
		<div class="layui-form-item">
			<label class="layui-form-label">文章内容</label>
			<div class="layui-input-block">
				<script id="news_content" name="content" type="text/plain" style="width:100%; height:300px;">@if (isset($news['content'])){!! $news['content'] !!}@endif</script>
			</div>
		</div>
		<div class="layui-form-item">
			<div class="layui-input-block">
				<button class="layui-btn" lay-submit="" lay-filter="submits">立即提交</button>
				<button type="reset" class="layui-btn layui-btn-primary">重置</button>
		    </div>
		</div>
	</form>
@endsection
@section('scripts')
<script type="text/javascript" src="{{ URL('vendor/ueditor/1.4.3/ueditor.config.js') }}"></script>
<script type="text/javascript" src="{{ URL('vendor/ueditor/1.4.3/ueditor.all.js') }}"> </script>
<script type="text/javascript" src="{{ URL('vendor/ueditor/1.4.3/lang/zh-cn/zh-cn.js') }}"></script>
<script type="text/javascript" src="{{URL("/admin/js/newsFormSubmit.js?v=").time()}}"></script>
<link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.1.0-beta.1/css/select2.min.css" rel="stylesheet" />
<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.1.0-beta.1/js/select2.min.js"></script>

<script>
    

	layui.use('upload', function(){
		var upload = layui.upload;

		//执行实例
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

		//执行实例
		var uploadInst1 = upload.render({
			elem: '#img_cover_btn' //绑定元素
			,url: '{{URL("api/upload")}}?scene=admin' //上传接口
			,done: function(res) {
				console.log(res);
				//上传完毕回调
				if (res.type == "ok"){
					$("#cover").val(res.message)
					$("#img_cover").show()
					$("#img_cover").attr("src",res.message)
				} else{
					alert(res.message)
				}
			}
			,error: function(){
				//请求异常回调
			}
		});
	});
</script>
@endsection