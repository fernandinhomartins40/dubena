
@if(Session::has('message_danger'))
<div class="alert alert-danger" style="width: 90%; margin-right: auto; margin-left: auto">
    <button type="button" class="close" data-dismiss="alert">&times;</button>
    <span class="glyphicon glyphicon-remove"></span>
    {{Session::get("message_danger")}}
</div>
@endif
