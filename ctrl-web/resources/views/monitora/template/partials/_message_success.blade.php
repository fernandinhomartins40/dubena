
@if(Session::has('message_success'))
<div class="alert alert-success" style="width: 90%; margin-right: auto; margin-left: auto">
    <button type="button" class="close" data-dismiss="alert">&times;</button>
    <span class="glyphicon glyphicon-ok"></span>
    {{Session::get("message_success")}}
</div>
@endif