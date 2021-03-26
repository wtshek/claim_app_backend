{if !(isset($multiple) && $multiple && is_array($value))}
{if $view_only}
<div class="input-text view form-control-plaintext">
    <span>{$value|escape}</span>
</div>
{else}
<div class="form-group">
    <div class="input-group date" id="{$id|default:$name|escape}" data-target-input="nearest">
        <input type="text" name="{$name|escape}" value="{$value|escape}" class="form-control datetimepicker-input" data-target="#{$id|default:$name|escape}"{if $placeholder} placeholder="{$title|escape}"{/if}>
        <div class="input-group-append" data-target="#{$id|default:$name|escape}" data-toggle="datetimepicker">
            <div class="input-group-text"><i class="fa fa-calendar"></i></div>
        </div>
    </div>
</div>
<!--
<div id="{$id|default:$name|escape}" class="input-append calendar-input {*span6*} {$class|escape}">
  <input type="text" name="{$name|escape}"
    value="{$value|escape}"
    {if $placeholder}placeholder="{$title|escape}"{/if}
    data-format="yyyy-MM-dd{if $showsTime} hh:mm:ss{/if}" class="form-control">
  <span class="add-on nopadding"><button type="button" class="calendar">&hellip;</button></span>
</div>
-->
<script>
(function() {
    $( "#{$id|default:$name|escape:"javascript"}" ).datetimepicker( {
        format: "YYYY-MM-DD{if $showsTime} HH:mm:ss{/if}",
        useCurrent: false
    } );
} )();
</script>
{/if}
{/if}

{if !$view_only}
<script>
/*
$( "#{$id|default:$name|escape:"javascript"}" ).datetimepicker( {
    pickTime: {if $showsTime}true{else}false{/if},
    pick12HourFormat: false
} );
*/
</script>
{/if}