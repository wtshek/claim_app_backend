
    {if isset($multiple) && $multiple && is_array($value)}

    {else}
    	{if $view_only}
    		<div class="input-text view">
                <span>
                    {$value|escape:'html'}
                </span>
            </div>
    	{else}
        <div class="calendar-input input input-append {$class|escape:'html'}">
            <input type="text" {if $placeholder}placeholder="{$title|escape:'html'}"{/if} name="{$name|escape:'html'}" id="{if $id}{$id|escape:'html'}{else}{$name|escape:'html'}{/if}"{if isset($value)} value="{$value|escape:'html'}"{/if} class="form-control" {implode(' ', $extra)}>
            <span class="add-on nopadding"><button type="button" class="calendar" id="{if $id}{$id|escape:'html'}{else}{$name|escape:'html'}{/if}_btn">&hellip;</button></span>
        </div>
        {/if}
    {/if}
    
	{if !$view_only}
    <script type="text/javascript">
        $(document).ready(function(){
            Calendar.setup( {ldelim}
                inputField: "{if $id}{$id|escape:'html'}{else}{$name|escape:'html'}{/if}",
                ifFormat: "{$format}",
                showsTime: {if $showsTime}true{else}false{/if},
                timeFormat: 24,
                button: "{if $id}{$id|escape:'html'}{else}{$name|escape:'html'}{/if}_btn",
                onUpdate: function( calendar )
                {ldelim}
                    $(calendar.params.inputField).trigger('change');
                    {rdelim}
                {rdelim} );
        });

    </script>
    {/if}