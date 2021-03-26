{if $view_only}
<div class="input-select view form-control-plaintext" {if $style} style="{$style|escape:'html'}"{/if}>
    <span>
       	{if isset($options[$selected]) && $options[$selected]}
       		{if is_array($options[$selected])}
       			<div>
                    <img src="{$options[$selected].img|escape:'html'}">
                    <div>{$options[$selected].label|escape:'html'}</div>
                </div>
       		{else}
       			{$options[$selected]|escape:'html'}
       		{/if}
       	{else}--{/if}
    </span>
</div>
{else}
<div class="radio-values">
{foreach from=$options key=k item=v}
    {assign var=radio_id value=$name|cat:_|cat:$k}
    <dl>
        <dd><input type="radio" id="{$radio_id|escape:'html'}" name="{$name|escape:'html'}{if isset($multiple) && $multiple}[]{/if}" value="{$k|escape:'html'}"{if $selected == $k} checked{/if} {if (isset($disabled) && $disabled) || $view_only} disabled="true"{/if}{if $style} style="{$style|escape:'html'}"{/if}></dd>
        <dt><label for="{$radio_id|escape:'html'}">
                {if is_array($v)}
                    <div>
                        <img src="{$v.img|escape:'html'}">
                        <div>{$v.label|escape:'html'}</div>
                    </div>
                {else}
                    {$v|escape:'html'}
                {/if}</label></dt>
    </dl>
{/foreach}
</div>
{/if}