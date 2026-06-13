
@if(Session::has('message_info'))
<div class="alert alert-info" style="width: 90%; margin-right: auto; margin-left: auto">
    <button type="button" class="close" data-dismiss="alert">&times;</button>
    <span class="glyphicon glyphicon-alert"></span>
    {{Session::get("message_info")}}
    
</div>
@endif
