{if isset($options)}
    <div class="checkbox-values{if $view_only} input-checkbox view{/if} form-control-plaintext">
        {foreach from=$options key=k item=v}
            {assign var=cb_id value=$name|cat:_|cat:$k}
            <dl>
                <dd>{if !$view_only}<input type="checkbox" id="{$cb_id|escape:'html'}" name="{$name|escape:'html'}[]" value="{$k|escape:'html'}"{if (is_array($selected) && in_array($k, $selected)) ||$selected == $k} checked{/if}{if isset($disabled) && $disabled} disabled="true"{/if}{if $style} style="{$style|escape:'html'}"{/if}>{else}{if $checked}&#10004;{/if}{/if}</dd>
                <dt>{if !$view_only}<label for="{$cb_id|escape:'html'}">{/if}{$v|escape:'html'}{if !$view_only}</label>{/if}</dt>
            </dl>
        {/foreach}
    </div>
{else}
    {if $view_only}
        {if $checked}&#10004;{/if}
    {else}
        <input type="checkbox" name="{$name|escape:'html'}[]"{if $id} id="{$id|escape:'html'}"{/if}{if isset($value)} value="{$value|escape:'html'}"{/if}{if $checked} checked{/if}{if isset($disabled) && $disabled} disabled="true"{/if}{if $style} style="{$style|escape:'html'}"{/if}>
    {/if}

{/if}